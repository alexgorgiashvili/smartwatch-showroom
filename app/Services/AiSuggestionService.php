<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ContactSetting;
use App\Models\Message;
use App\Models\SocialComment;
use App\Services\Chatbot\ConversationMemoryService;
use App\Services\Chatbot\EmbeddingService;
use App\Services\Chatbot\PineconeService;
use App\Services\Chatbot\UnifiedAiPolicyService;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiSuggestionService
{
    protected ?string $openaiApiKey;
    protected ?string $openaiOrgId;
    protected string $openaiModel;
    protected string $openaiBaseUrl;
    protected UnifiedAiPolicyService $policy;
    protected ConversationMemoryService $memoryService;
    protected EmbeddingService $embeddingService;
    protected PineconeService $pineconeService;

    public function __construct(
        UnifiedAiPolicyService $policy,
        ConversationMemoryService $memoryService,
        EmbeddingService $embeddingService,
        PineconeService $pineconeService
    )
    {
        $this->policy = $policy;
        $this->memoryService = $memoryService;
        $this->embeddingService = $embeddingService;
        $this->pineconeService = $pineconeService;
        $this->openaiApiKey = config('services.openai.key');
        $this->openaiOrgId = config('services.openai.org_id');
        $this->openaiModel = config('services.openai.model', 'gpt-4.1-mini');
        $this->openaiBaseUrl = rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
    }

    /**
     * Generate AI-powered suggestion responses for a customer message
     *
     * @param Conversation $conversation
     * @param Message $message Customer message to reply to
     * @param int $maxSuggestions Number of suggestions to generate
     * @return array|null Array of suggestion strings or null if generation fails
     */
    public function generateSuggestions(
        Conversation $conversation,
        Message $message,
        int $maxSuggestions = 3
    ): ?array {
        try {
            Log::info('AI Suggestion: Starting generation', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'customer_id' => $conversation->customer_id,
            ]);

            // Validate inputs
            if (!$message->isFromCustomer()) {
                Log::warning('AI Suggestion: Not a customer message', [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                ]);
                return [];
            }

            // Get conversation context (last 3 messages)
            $context = $this->getConversationContext($conversation);
            $memoryContext = $this->memoryService->getContext($conversation->id);
            $preferences = is_array($memoryContext['preferences'] ?? null) ? $memoryContext['preferences'] : [];

            // Get product knowledge from Pinecone
            $normalizedMessage = $this->policy->normalizeIncomingMessage($message->content);

            $productInfo = $this->getProductContext($conversation->customer_id, $normalizedMessage);
            $contactInfo = $this->getContactContext();

            // Build the prompt
            $prompt = $this->buildPrompt($normalizedMessage, $context, $productInfo, $contactInfo, $preferences);

            // Call OpenAI API
            $openaiResponse = $this->callOpenAiApi($prompt, $maxSuggestions);

            if (!$openaiResponse) {
                Log::warning('AI Suggestion: OpenAI API returned no response');
                return [];
            }

            // Parse and format suggestions
            $suggestions = $this->formatSuggestions($openaiResponse, $maxSuggestions);

            // Log token usage for cost monitoring
            $this->logTokenUsage($openaiResponse);

            Log::info('AI Suggestion: Successfully generated', [
                'conversation_id' => $conversation->id,
                'suggestion_count' => count($suggestions),
            ]);

            return $suggestions;
        } catch (Exception $e) {
            Log::error('AI Suggestion: Failed to generate', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
            ]);
            return [];
        }
    }

    /**
     * Get product context from Pinecone for customer
     *
     * @param int $customerId
     * @param string $searchQuery
     * @return string Formatted product information
     */
    public function getProductContext(int $customerId, string $searchQuery): string
    {
        try {
            // Validate API credentials
            if (!$this->embeddingService->isConfigured() || !$this->pineconeService->isConfigured()) {
                Log::debug('Pinecone/Embedding services are not configured');
                return '';
            }

            // Cache key includes query hash for query-specific caching
            $queryHash = md5($searchQuery);
            $contextVersion = (int) Cache::get('product_context_version', 1);
            $cacheKey = "product_context_{$contextVersion}_{$customerId}_{$queryHash}";
            $cachedContext = Cache::get($cacheKey);
            if ($cachedContext !== null) {
                Log::debug('Using cached product context', ['customer_id' => $customerId]);
                return $cachedContext;
            }

            Log::debug('Querying Pinecone for product context', [
                'customer_id' => $customerId,
                'query' => $searchQuery
            ]);

            // Call Pinecone API to get relevant documents
            $documents = $this->queryPinecone($searchQuery);

            // Format documents into readable context
            $context = $this->formatProductDocuments($documents);

            Log::debug('Product context retrieved', [
                'customer_id' => $customerId,
                'document_count' => count($documents),
                'context_length' => strlen($context)
            ]);

            // Cache for 5 minutes (shorter cache for better relevance)
            Cache::put($cacheKey, $context, 300);

            return $context;
        } catch (Exception $e) {
            Log::warning('Failed to retrieve product context from Pinecone', [
                'exception' => $e->getMessage(),
                'customer_id' => $customerId,
            ]);
            return '';
        }
    }

    /**
     * Query Pinecone for relevant product documents
     *
     * @param string $query Search query
     * @return array Array of document results
     */
    protected function queryPinecone(string $query): array
    {
        try {
            // Get embedding for the query
            $embedding = $this->getEmbedding($query);

            if (!$embedding) {
                return [];
            }

            return $this->pineconeService->query($embedding, 5);
        } catch (Exception $e) {
            Log::warning('Exception querying Pinecone', [
                'exception' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get embedding vector for text using OpenAI
     *
     * @param string $text
     * @return array|null Embedding vector or null
     */
    protected function getEmbedding(string $text): ?array
    {
        try {
            $embedding = $this->embeddingService->embed($text);

            return $embedding !== [] ? $embedding : null;
        } catch (Exception $e) {
            Log::warning('Exception getting embedding', [
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Format Pinecone documents into readable context
     *
     * @param array $documents
     * @return string
     */
    protected function formatProductDocuments(array $documents): string
    {
        if (empty($documents)) {
            return '';
        }

        $formatted = "**რელევანტური პროდუქტის ინფორმაცია:**\n";
        $count = 0;

        foreach ($documents as $doc) {
            if ($count >= 5) {
                break;
            }

            $metadata = $doc['metadata'] ?? [];
            $text = '';

            if (isset($metadata['text'])) {
                $text = $metadata['text'];
            } elseif (isset($metadata['content'])) {
                $text = $metadata['content'];
            } elseif (isset($metadata['description'])) {
                $text = $metadata['description'];
            }

            if ($text) {
                $formatted .= "- " . substr($text, 0, 200) . "...\n";
                $count++;
            }
        }

        return $formatted;
    }

    /**
     * Get conversation context (last 3 messages)
     *
     * @param Conversation $conversation
     * @return string Formatted conversation context
     */
    protected function getConversationContext(Conversation $conversation): string
    {
        $recentMessages = $conversation->messages()
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->filter(function ($msg) {
                return !data_get($msg->metadata, 'ai_generated', false);
            });

        $lastMessages = $recentMessages
            ->take(3)
            ->reverse();

        $context = "**ბოლო საუბრის კონტექსტი:**\n";

        foreach ($lastMessages as $msg) {
            $sender = $msg->isFromCustomer() ? $conversation->customer->name : 'კონსულტანტი';
            $context .= "{$sender}: " . substr($msg->content, 0, 150) . "\n";
        }

        return $context;
    }

    /**
     * Build the prompt for OpenAI
     *
     * @param string $messageText Customer message text
     * @param string $context Conversation context
     * @param string $productInfo Product knowledge
     * @return string Complete prompt
     */
    public function buildPrompt(string $messageText, string $context, string $productInfo, string $contactInfo, array $preferences = []): string
    {
        $preferenceLines = [];

        if (isset($preferences['budget_max_gel'])) {
            $preferenceLines[] = '- ბიუჯეტი: ' . $preferences['budget_max_gel'] . ' ₾-მდე';
        }

        if (!empty($preferences['color'])) {
            $preferenceLines[] = '- სასურველი ფერი: ' . $preferences['color'];
        }

        if (!empty($preferences['size'])) {
            $preferenceLines[] = '- სასურველი ზომა: ' . $preferences['size'];
        }

        if (!empty($preferences['features']) && is_array($preferences['features'])) {
            $preferenceLines[] = '- საინტერესო ფუნქციები: ' . implode(', ', $preferences['features']);
        }

        $preferencesSection = $preferenceLines !== []
            ? implode("\n", $preferenceLines)
            : '- არ არის აღმოჩენილი';

        $systemPrompt = <<<PROMPT
პროდუქტის ცოდნა (Knowledge Base):
{$productInfo}

საკონტაქტო ინფორმაცია:
{$contactInfo}

საუბრის კონტექსტი:
{$context}

მომხმარებლის პრეფერენციები:
{$preferencesSection}

მომხმარებლის ბოლო შეტყობინება:
{$messageText}

---

შექმენი ზუსტად 3 პროფესიონალური, მოკლე ქართული პასუხის ვარიანტი. გააკეთე მრავალფეროვანი (1. თანამგრძნობი, 2. გადაწყვეტაზე ორიენტირებული, 3. ინფორმაციული). თითოეული ახალ ხაზზე, ნუმერაციით (1. 2. 3.).

PROMPT;

        return $systemPrompt;
    }

    protected function getContactContext(): string
    {
        $settings = ContactSetting::allKeyed();

        $lines = array_filter([
            'ტელეფონი: ' . ($settings['phone_display'] ?? ''),
            'WhatsApp: ' . ($settings['whatsapp_url'] ?? ''),
            'ელფოსტა: ' . ($settings['email'] ?? ''),
            'მისამართი: ' . ($settings['location'] ?? ''),
            'სამუშაო საათები: ' . ($settings['hours'] ?? ''),
        ]);

        return implode("\n", $lines);
    }

    /**
     * Call OpenAI API to generate suggestions
     *
     * @param string $prompt
     * @param int $maxSuggestions
     * @return array|null Response data or null if failed
     */
    protected function callOpenAiApi(string $prompt, int $maxSuggestions): ?array
    {
        try {
            Log::info('AI Suggestion: Calling OpenAI API', [
                'model' => $this->openaiModel,
                'has_api_key' => !empty($this->openaiApiKey),
                'has_org_id' => !empty($this->openaiOrgId),
            ]);

            if (!$this->openaiApiKey) {
                Log::error('AI Suggestion: OpenAI API key not configured');
                return null;
            }

            $headers = [
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ];

            // Only add org header if it's actually set and not empty
            if ($this->openaiOrgId && trim($this->openaiOrgId) !== '') {
                $headers['OpenAI-Organization'] = $this->openaiOrgId;
                Log::debug('AI Suggestion: Using OpenAI organization', ['org_id' => substr($this->openaiOrgId, 0, 10) . '...']);
            }

            $response = Http::withHeaders($headers)->timeout(30)->post(
                $this->openaiBaseUrl . '/chat/completions',
                [
                    'model' => $this->openaiModel,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->policy->omnichannelSystemPrompt(),
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500,
                    'n' => 1,
                ]
            );

            if ($response->successful()) {
                Log::info('AI Suggestion: OpenAI API call successful');
                return $response->json();
            }

            Log::error('AI Suggestion: OpenAI API error', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::warning('Exception calling OpenAI API', [
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Format OpenAI response into suggestions array
     *
     * @param array $response OpenAI API response
     * @param int $maxSuggestions Maximum suggestions to return
     * @return array Array of suggestion strings
     */
    public function formatSuggestions(array $response, int $maxSuggestions = 3): array
    {
        try {
            $content = $response['choices'][0]['message']['content'] ?? '';

            if (empty($content)) {
                return [];
            }

            // Split by newlines and filter out empty lines
            $lines = array_filter(
                explode("\n", $content),
                fn($line) => !empty(trim($line))
            );

            $suggestions = [];
            foreach ($lines as $line) {
                // Remove numbering (1. 2. 3. etc)
                $cleaned = preg_replace('/^\d+\.\s*/', '', trim($line));

                // Remove markdown formatting
                $cleaned = trim($cleaned, '*_`');

                if (!empty($cleaned) && strlen($cleaned) > 5) {
                    $suggestions[] = $cleaned;

                    if (count($suggestions) >= $maxSuggestions) {
                        break;
                    }
                }
            }

            return $suggestions;
        } catch (Exception $e) {
            Log::warning('Failed to format suggestions', [
                'exception' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Log token usage for cost monitoring
     *
     * @param array $response OpenAI response
     */
    protected function logTokenUsage(array $response): void
    {
        try {
            $usage = $response['usage'] ?? null;

            if ($usage) {
                Log::info('OpenAI token usage', [
                    'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                    'completion_tokens' => $usage['completion_tokens'] ?? 0,
                    'total_tokens' => $usage['total_tokens'] ?? 0,
                ]);
            }
        } catch (Exception $e) {
            Log::debug('Could not log token usage', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate an AI reply suggestion for a social media comment.
     *
     * @param SocialComment $comment
     * @param string $tonePreference warm|formal|neutral
     * @return array{success: bool, reply?: string, detected_tone?: string, suggested_tone?: string, error?: string}
     */
    public function generateCommentReply(SocialComment $comment, string $tonePreference = 'warm'): array
    {
        try {
            $postText = $comment->facebookPost?->message ?? '';
            $commentText = $comment->message;

            // Get product context via Pinecone if available
            $searchQuery = $commentText . ' ' . $postText;
            $productInfo = '';

            if ($this->embeddingService->isConfigured() && $this->pineconeService->isConfigured()) {
                $productInfo = $this->getProductContext(0, $searchQuery);
            }

            $contactInfo = $this->getContactContext();

            $toneLabel = match ($tonePreference) {
                'warm' => 'თბილი, მეგობრული',
                'formal' => 'ოფიციალური, პროფესიონალური',
                default => 'ნეიტრალური',
            };

            $prompt = <<<PROMPT
სოციალური მედიის კომენტარზე პასუხის გენერაცია.

ორიგინალი პოსტი:
{$postText}

მომხმარებლის კომენტარი:
{$commentText}

პროდუქტის ცოდნა:
{$productInfo}

საკონტაქტო ინფორმაცია:
{$contactInfo}

ინსტრუქცია:
1. გააანალიზე კომენტარის ტონი (positive, negative, neutral, question)
2. შექმენი ერთი პასუხი {$toneLabel} ტონით
3. პასუხი უნდა იყოს ქართულ ენაზე, მოკლე (1-3 წინადადება)
4. თუ კომენტარი პროდუქტის შესახებ შეკითხვაა, გამოიყენე პროდუქტის ცოდნა
5. თუ კომენტარი უარყოფითია, იყავი თანამგრძნობი და გადაწყვეტაზე ორიენტირებული
6. დაამატე call-to-action თუ შესაბამისია

დააბრუნე JSON ფორმატით (მხოლოდ JSON):
{"detected_tone": "positive|negative|neutral|question", "reply": "..."}
PROMPT;

            if (!$this->openaiApiKey) {
                return ['success' => false, 'error' => 'OpenAI API key not configured'];
            }

            $headers = [
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ];

            if ($this->openaiOrgId && trim($this->openaiOrgId) !== '') {
                $headers['OpenAI-Organization'] = $this->openaiOrgId;
            }

            $response = Http::withHeaders($headers)->timeout(30)->post(
                $this->openaiBaseUrl . '/chat/completions',
                [
                    'model' => $this->openaiModel,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'შენ ხარ MyTechnic.ge-ს სოციალური მედიის მენეჯერი. პასუხობ კომენტარებს პროფესიონალურად. ყოველთვის დააბრუნე ვალიდური JSON.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 300,
                ]
            );

            if (!$response->successful()) {
                Log::warning('Comment reply generation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'error' => 'AI service unavailable'];
            }

            $this->logTokenUsage($response->json());

            $content = trim($response->json('choices.0.message.content', ''));
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);

            $parsed = json_decode($content, true);

            if (!is_array($parsed) || !isset($parsed['reply'])) {
                Log::warning('Comment reply: unexpected AI format', ['content' => $content]);
                return ['success' => false, 'error' => 'Unexpected AI response format'];
            }

            // Save suggestion on the comment
            $comment->update(['ai_suggested_reply' => $parsed['reply']]);

            return [
                'success' => true,
                'reply' => $parsed['reply'],
                'detected_tone' => $parsed['detected_tone'] ?? 'neutral',
                'suggested_tone' => $tonePreference,
            ];
        } catch (Exception $e) {
            Log::error('Comment reply generation exception', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Failed to generate reply'];
        }
    }

    public function generateAutoReplyFromTemplate(SocialComment $comment, string $template): array
    {
        try {
            if (!$this->openaiApiKey) {
                return ['success' => false, 'error' => 'OpenAI API key not configured'];
            }

            $postText = $comment->facebookPost?->message ?? '';
            $commentText = $comment->message;

            $prompt = <<<PROMPT
შენ უნდა უპასუხო სოციალურ მედიის კომენტარს.

კონტექსტი:
პოსტი:
{$postText}

კომენტარი:
{$commentText}

ადმინის ინსტრუქცია/შაბლონი:
{$template}

მოთხოვნები:
- პასუხი ქართულად
- 1-3 წინადადება
- არ ჩაწერო JSON/კოდი/markdown, მხოლოდ საბოლოო ტექსტი
PROMPT;

            $headers = [
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ];

            if ($this->openaiOrgId && trim($this->openaiOrgId) !== '') {
                $headers['OpenAI-Organization'] = $this->openaiOrgId;
            }

            $response = Http::withHeaders($headers)->timeout(30)->post(
                $this->openaiBaseUrl . '/chat/completions',
                [
                    'model' => $this->openaiModel,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'შენ ხარ MyTechnic.ge-ს სოციალური მედიის მენეჯერი. წერ მოკლე, პროფესიონალურ პასუხებს.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.5,
                    'max_tokens' => 200,
                ]
            );

            if (!$response->successful()) {
                Log::warning('Auto reply generation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'error' => 'AI service unavailable'];
            }

            $this->logTokenUsage($response->json());

            $content = trim($response->json('choices.0.message.content', ''));
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);

            return [
                'success' => true,
                'reply' => trim((string) $content),
            ];
        } catch (Exception $e) {
            Log::error('Auto reply generation exception', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Failed to generate auto reply'];
        }
    }
}
