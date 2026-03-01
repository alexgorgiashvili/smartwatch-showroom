<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ContactSetting;
use App\Models\Message;
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
    protected ?string $pineconeApiKey;
    protected ?string $pineconeHost;
    protected ?string $pineconeNamespace;
    protected string $pineconeIndex;
    protected UnifiedAiPolicyService $policy;

    public function __construct(UnifiedAiPolicyService $policy)
    {
        $this->policy = $policy;
        $this->openaiApiKey = config('ai.openai.api_key');
        $this->openaiOrgId = config('ai.openai.org_id');
        $this->openaiModel = config('ai.openai.model', 'gpt-4-turbo');
        $this->pineconeApiKey = config('ai.pinecone.api_key');
        $this->pineconeHost = config('ai.pinecone.host');
        $this->pineconeNamespace = config('ai.pinecone.namespace', 'mytechnic');
        $this->pineconeIndex = config('ai.pinecone.index', 'products');
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
            // Validate inputs
            if (!$message->isFromCustomer()) {
                Log::warning('Attempted to generate suggestions for non-customer message', [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                ]);
                return null;
            }

            // Get conversation context (last 3 messages)
            $context = $this->getConversationContext($conversation);

            // Get product knowledge from Pinecone
            $normalizedMessage = $this->policy->normalizeIncomingMessage($message->content);

            $productInfo = $this->getProductContext($conversation->customer_id, $normalizedMessage);
            $contactInfo = $this->getContactContext();

            // Build the prompt
            $prompt = $this->buildPrompt($normalizedMessage, $context, $productInfo, $contactInfo);

            // Call OpenAI API
            $openaiResponse = $this->callOpenAiApi($prompt, $maxSuggestions);

            if (!$openaiResponse) {
                return null;
            }

            // Parse and format suggestions
            $suggestions = $this->formatSuggestions($openaiResponse, $maxSuggestions);

            // Log token usage for cost monitoring
            $this->logTokenUsage($openaiResponse);

            return $suggestions;
        } catch (Exception $e) {
            Log::warning('Failed to generate AI suggestions', [
                'exception' => $e->getMessage(),
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
            ]);
            return null;
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
            if (!$this->pineconeApiKey || !$this->pineconeHost) {
                Log::debug('Pinecone credentials not configured', [
                    'has_api_key' => !empty($this->pineconeApiKey),
                    'has_host' => !empty($this->pineconeHost)
                ]);
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

            // Query Pinecone
            $queryData = [
                'vector' => $embedding,
                'topK' => 5,
                'includeMetadata' => true,
            ];

            // Add namespace if configured
            if ($this->pineconeNamespace) {
                $queryData['namespace'] = $this->pineconeNamespace;
            }

            $response = Http::withHeaders([
                'Api-Key' => $this->pineconeApiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->pineconeHost}/query", $queryData);

            if ($response->successful()) {
                return $response->json('matches', []);
            }

            Log::warning('Pinecone query failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [];
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
            if (!$this->openaiApiKey) {
                return null;
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/embeddings', [
                'model' => config('ai.openai.embedding_model', 'text-embedding-3-small'),
                'input' => $text,
            ]);

            if ($response->successful()) {
                return $response->json('data.0.embedding');
            }

            Log::warning('Failed to get embedding from OpenAI', [
                'status' => $response->status(),
            ]);

            return null;
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

        $formatted = "**Relevant Product Information:**\n";
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

        $context = "**Recent Conversation Context:**\n";

        foreach ($lastMessages as $msg) {
            $sender = $msg->isFromCustomer() ? $conversation->customer->name : 'Support Agent';
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
    public function buildPrompt(string $messageText, string $context, string $productInfo, string $contactInfo): string
    {
        $systemPrompt = <<<PROMPT
You are a helpful and professional customer support representative for MyTechnic, a premium smartwatch for kids.

Your role is to:
- Provide courteous, professional responses
- Use the product knowledge provided to answer questions accurately
- Reply in natural Georgian language
- If the input uses Latin transliteration for Georgian words, interpret it correctly and still answer in Georgian script
- Be concise (1-3 sentences max per response)
- Maintain a friendly, helpful tone
- Offer solutions or next steps when appropriate
- Answer the customer's latest message directly; avoid generic greetings
- Do not repeat previous assistant messages verbatim

Product Knowledge:
{$productInfo}

Contact Information:
{$contactInfo}

Conversation Context:
{$context}

Customer's Latest Message:
{$messageText}

---

Generate exactly 3 professional, concise Georgian response suggestions that the support agent could send. Make them diverse in approach (e.g., one empathetic, one solution-focused, one informational). Format each on a new line starting with a number (1. 2. 3.).

PROMPT;

        return $systemPrompt;
    }

    protected function getContactContext(): string
    {
        $settings = ContactSetting::allKeyed();

        $lines = array_filter([
            'Phone: ' . ($settings['phone_display'] ?? ''),
            'WhatsApp: ' . ($settings['whatsapp_url'] ?? ''),
            'Email: ' . ($settings['email'] ?? ''),
            'Location: ' . ($settings['location'] ?? ''),
            'Working Hours: ' . ($settings['hours'] ?? ''),
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
            if (!$this->openaiApiKey) {
                Log::warning('OpenAI API key not configured');
                return null;
            }

            $headers = [
                'Authorization' => "Bearer {$this->openaiApiKey}",
                'Content-Type' => 'application/json',
            ];

            if ($this->openaiOrgId) {
                $headers['OpenAI-Organization'] = $this->openaiOrgId;
            }

            $response = Http::withHeaders($headers)->timeout(30)->post(
                'https://api.openai.com/v1/chat/completions',
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
                return $response->json();
            }

            Log::warning('OpenAI API error', [
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
}
