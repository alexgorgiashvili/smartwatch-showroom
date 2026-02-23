<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Chatbot\ChatbotQualityMetricsService;
use App\Services\Chatbot\UnifiedAiPolicyService;
use Illuminate\Support\Facades\Log;

class AiConversationService
{
    protected $aiSuggestionService;
    protected UnifiedAiPolicyService $policy;
    protected ChatbotQualityMetricsService $metrics;

    public function __construct(
        AiSuggestionService $aiSuggestionService,
        UnifiedAiPolicyService $policy,
        ChatbotQualityMetricsService $metrics
    )
    {
        $this->aiSuggestionService = $aiSuggestionService;
        $this->policy = $policy;
        $this->metrics = $metrics;
    }

    /**
     * Generate a single AI response for a conversation
     *
     * @param Conversation $conversation
     * @return string|null The AI-generated response
     */
    public function generateResponse(Conversation $conversation): ?string
    {
        try {
            // Get the last customer message
            $lastCustomerMessage = $conversation->messages()
                ->where('sender_type', 'customer')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastCustomerMessage) {
                Log::warning('No customer message found for AI response generation', [
                    'conversation_id' => $conversation->id
                ]);
                return null;
            }

            // Generate suggestions using existing service
            $suggestions = $this->aiSuggestionService->generateSuggestions(
                $conversation,
                $lastCustomerMessage,
                1 // Just need one response
            );

            if (!$suggestions || empty($suggestions)) {
                Log::warning('AI service returned no suggestions', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $lastCustomerMessage->id
                ]);
                return null;
            }

            $response = trim($suggestions[0]);

            $lastAiMessage = $conversation->messages()
                ->where('sender_type', 'admin')
                ->whereJsonContains('metadata->ai_generated', true)
                ->orderBy('created_at', 'desc')
                ->first();

            $fallbackUsed = false;

            if ($this->isGenericOrRepeated($response, $lastAiMessage?->content)) {
                Log::info('AI response was generic or repeated, using fallback', [
                    'conversation_id' => $conversation->id,
                    'response' => $response
                ]);

                $response = $this->buildFallbackResponse(
                    $lastCustomerMessage->content,
                    $lastAiMessage?->content
                );
                $fallbackUsed = true;
            }

            $strictQaPassed = $this->policy->passesStrictGeorgianQa($response);

            if (!$strictQaPassed) {
                $response = $this->buildFallbackResponse(
                    $lastCustomerMessage->content,
                    $lastAiMessage?->content
                );
                $fallbackUsed = true;
            }

            $this->metrics->recordOmnichannelResponseQuality(
                $conversation->id,
                $fallbackUsed,
                $strictQaPassed
            );

            return $response;

        } catch (\Exception $e) {
            Log::error('Failed to generate AI response', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Generate and send AI response automatically
     *
     * @param Conversation $conversation
     * @return bool Success status
     */
    public function autoReply(Conversation $conversation): bool
    {
        try {
            $response = $this->generateResponse($conversation);

            if (!$response) {
                return false;
            }

            // Create the message from AI/admin
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'customer_id' => $conversation->customer_id,
                'sender_type' => 'admin',
                'sender_id' => 0, // System/AI sender
                'sender_name' => 'AI Assistant',
                'content' => $response,
                'platform_message_id' => 'ai_' . uniqid(),
                'metadata' => [
                    'ai_generated' => true,
                    'auto_reply' => true,
                    'generated_at' => now()->toIso8601String()
                ]
            ]);

            // Update conversation timestamp
            $conversation->update([
                'last_message_at' => now()
            ]);

            // Send via platform API if it's Facebook Messenger
            if (in_array($conversation->platform, ['messenger', 'facebook'])) {
                $customer = $conversation->customer;
                $platformUserIds = $customer->platform_user_ids ?? [];
                $messengerUserId = $platformUserIds['messenger'] ?? null;

                if ($messengerUserId) {
                    $fbService = new FacebookMessengerService();

                    // Send typing indicator
                    $fbService->sendTypingIndicator($messengerUserId, 'typing_on');

                    // Small delay to seem more human
                    usleep(500000); // 0.5 seconds

                    // Send the message
                    $result = $fbService->sendMessage($messengerUserId, $response);

                    if (!$result['success']) {
                        Log::error('Failed to send AI auto-reply via Facebook', [
                            'conversation_id' => $conversation->id,
                            'error' => $result['error']
                        ]);
                        return false;
                    }

                    Log::info('AI auto-reply sent successfully', [
                        'conversation_id' => $conversation->id,
                        'message_id' => $message->id,
                        'platform' => 'messenger'
                    ]);
                }
            }

            // Broadcast the message to admin inbox
            event(new \App\Events\MessageReceived(
                $message,
                $conversation,
                $conversation->customer,
                $conversation->platform
            ));

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send AI auto-reply', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function shouldAutoReplyToConversation(Conversation $conversation, ?Message $message = null): bool
    {
        $sourceMessage = $message;

        if (!$sourceMessage) {
            $sourceMessage = $conversation->messages()
                ->where('sender_type', 'customer')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (!$sourceMessage) {
            return false;
        }

        $hasAttachments = (bool) data_get($sourceMessage->metadata, 'has_attachments', false)
            || str_contains($sourceMessage->content ?? '', 'Attachments:');

        $accepted = $this->policy->shouldAutoReplySelectively(
            $sourceMessage->content ?? '',
            $hasAttachments
        );

        $reason = $accepted ? 'intent_or_question_match' : 'below_selective_threshold';

        $this->metrics->recordAutoReplyDecision(
            $conversation->id,
            $sourceMessage->id,
            $accepted,
            $reason
        );

        return $accepted;
    }

    protected function isGenericOrRepeated(string $response, ?string $lastAiContent): bool
    {
        $normalized = $this->normalizeText($response);

        if ($lastAiContent) {
            $lastNormalized = $this->normalizeText($lastAiContent);
            if ($normalized === $lastNormalized) {
                return true;
            }
        }

        $genericPatterns = [
            '/^hi\b/i',
            '/^hello\b/i',
            '/how can i assist/i',
            '/how can i help/i',
            '/assist you today/i',
            '/kid(s)?sim watch today/i'
        ];

        foreach ($genericPatterns as $pattern) {
            if (preg_match($pattern, $response)) {
                return true;
            }
        }

        return strlen($normalized) < 12;
    }

    protected function normalizeText(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return $text ?? '';
    }

    protected function buildFallbackResponse(string $messageText, ?string $lastAiContent = null): string
    {
        $messageText = $this->policy->normalizeIncomingMessage($messageText);
        $text = strtolower($messageText);
        $isGeorgian = $this->policy->looksGeorgianOrTransliterated($messageText);

        $looksLikePriceQuestion = preg_match('/\b(price|cost|how much|pricing|fee)\b/i', $text) === 1
            || preg_match('/(ფასი|ღირს|ღირებულ|რამდენი|რა ღირს)/u', $messageText) === 1;
        $looksLikeModelQuestion = preg_match('/(მოდელ|მოდელები)/u', $messageText) === 1;
        $looksLikeStockQuestion = preg_match('/(მარაგ|საწყობ|დარჩენილი)/u', $messageText) === 1;
        $looksLikeDeliveryQuestion = preg_match('/(მიწოდ|მიტან|კურიერ|ჩამოტანა)/u', $messageText) === 1;

        if ($isGeorgian) {
            if ($looksLikeModelQuestion) {
                $response = 'ამჟამად გვაქვს KidSIM-ის რამდენიმე მოდელი. რომელი ფუნქციები გაინტერესებთ (GPS, SOS, ზარები, კამერა) და შევარჩევთ შესაბამისს.';
            } elseif ($looksLikeStockQuestion) {
                $response = 'მარაგების დასაზუსტებლად გვითხარით სასურველი მოდელი/ფერი და დაგიდასტურებთ.';
            } elseif ($looksLikeDeliveryQuestion) {
                $response = 'მიწოდება გვაქვს თბილისში და რეგიონებში. მითხარით ქალაქი და სასურველი ვადა.';
            } elseif ($looksLikePriceQuestion) {
                $response = 'ფასი დამოკიდებულია მოდელზე და ფუნქციებზე. რომელი მოდელი ან ფუნქცია გაინტერესებთ?';
            } else {
                $response = 'გმადლობთ მომართვისთვის! გვითხარით რომელი ფუნქცია ან მოდელი გაინტერესებთ (GPS, SOS, ზარები, კამერა) და დაგეხმარებით.';
            }
        } else {
            if ($looksLikeModelQuestion) {
                $response = 'We have multiple KidSIM models available. Which features are most important to you (GPS, SOS, calling, or camera)?';
            } elseif ($looksLikeStockQuestion) {
                $response = 'To confirm stock, please tell us the model and color you want.';
            } elseif ($looksLikeDeliveryQuestion) {
                $response = 'We do offer delivery. What city are you in and when do you need it?';
            } elseif ($looksLikePriceQuestion) {
                $response = 'Prices depend on the model and features. Which KidSIM model or features are you interested in?';
            } else {
                $response = 'Thanks for reaching out! Which KidSIM model or features are you interested in (GPS, SOS, battery, or calling)?';
            }
        }

        if ($lastAiContent && $this->normalizeText($response) === $this->normalizeText($lastAiContent)) {
            $response .= $isGeorgian
                ? ' ასევე თუ შეგიძლიათ, მითხარით სასურველი ბიუჯეტი.'
                : ' Also, if you have a budget in mind, please share it.';
        }

        return $response;
    }
}
