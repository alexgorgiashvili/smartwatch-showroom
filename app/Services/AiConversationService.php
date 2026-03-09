<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Services\Chatbot\ChatbotFallbackResolution;
use App\Services\Chatbot\ChatbotOutcomeReason;
use App\Services\Chatbot\ChatbotQualityMetricsService;
use App\Services\Chatbot\ChatbotFallbackStrategyService;
use App\Services\Chatbot\ChatbotProductSelectionService;
use App\Services\Chatbot\CarouselBuilderService;
use App\Services\Chatbot\ConversationMemoryService;
use App\Services\Chatbot\HybridSearchService;
use App\Services\Chatbot\InputGuardService;
use App\Services\Chatbot\ResponseValidatorService;
use App\Services\Chatbot\UnifiedAiPolicyService;
use Illuminate\Support\Facades\Log;

class AiConversationService
{
    protected $aiSuggestionService;
    protected UnifiedAiPolicyService $policy;
    protected ChatbotQualityMetricsService $metrics;
    protected ConversationMemoryService $memoryService;
    protected InputGuardService $inputGuard;
    protected ResponseValidatorService $responseValidator;
    protected CarouselBuilderService $carouselBuilder;
    protected HybridSearchService $hybridSearch;
    protected MetaApiService $metaApiService;
    protected WhatsAppService $whatsAppService;
    protected ChatbotFallbackStrategyService $fallbackStrategy;
    protected ChatbotProductSelectionService $productSelection;

    public function __construct(
        AiSuggestionService $aiSuggestionService,
        UnifiedAiPolicyService $policy,
        ChatbotQualityMetricsService $metrics,
        ConversationMemoryService $memoryService,
        InputGuardService $inputGuard,
        ResponseValidatorService $responseValidator,
        CarouselBuilderService $carouselBuilder,
        HybridSearchService $hybridSearch,
        MetaApiService $metaApiService,
        WhatsAppService $whatsAppService,
        ChatbotFallbackStrategyService $fallbackStrategy,
        ChatbotProductSelectionService $productSelection
    )
    {
        $this->aiSuggestionService = $aiSuggestionService;
        $this->policy = $policy;
        $this->metrics = $metrics;
        $this->memoryService = $memoryService;
        $this->inputGuard = $inputGuard;
        $this->responseValidator = $responseValidator;
        $this->carouselBuilder = $carouselBuilder;
        $this->hybridSearch = $hybridSearch;
        $this->metaApiService = $metaApiService;
        $this->whatsAppService = $whatsAppService;
        $this->fallbackStrategy = $fallbackStrategy;
        $this->productSelection = $productSelection;
    }

    /**
     * Generate a single AI response for a conversation
     *
     * @param Conversation $conversation
     * @return string|null The AI-generated response
     */
    public function generateResponse(Conversation $conversation): ?string
    {
        $outcome = $this->generateResponseOutcome($conversation);

        return $outcome['reply'] ?? null;
    }

    /**
     * @return array{reply:string,resolution:ChatbotFallbackResolution,response_time_ms:int}|null
     */
    protected function generateResponseOutcome(Conversation $conversation): ?array
    {
        $startedAt = microtime(true);

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

            $guardResult = $this->inputGuard->inspect((string) $lastCustomerMessage->content);
            $customerInput = trim($guardResult->sanitizedInput());

            if ($customerInput === '') {
                $customerInput = trim((string) $lastCustomerMessage->content);
            }

            if (!$guardResult->allowed()) {
                $this->memoryService->appendMessage($conversation->id, 'user', $customerInput);
                $resolution = $this->fallbackStrategy->resolveGuardOutcome($guardResult);
                $this->memoryService->appendMessage($conversation->id, 'assistant', $resolution->reply());

                Log::info('Input guard blocked omnichannel request', [
                    'conversation_id' => $conversation->id,
                    'reason' => $guardResult->reason(),
                ]);

                return $this->finalizeOutcome($conversation, $resolution, $startedAt);
            }

            $lastCustomerMessage->content = $customerInput;

            // Generate suggestions using existing service
            $this->memoryService->appendMessage(
                $conversation->id,
                'user',
                $customerInput
            );

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

                $this->metrics->recordProviderIncident($conversation->id, 'omnichannel', 'no_suggestions');
                $resolution = $this->fallbackStrategy->resolveStaticReason(ChatbotOutcomeReason::PROVIDER_UNAVAILABLE);
                $this->memoryService->appendMessage($conversation->id, 'assistant', $resolution->reply());

                return $this->finalizeOutcome($conversation, $resolution, $startedAt);
            }

            $response = trim($suggestions[0]);

            $lastAiMessage = $conversation->messages()
                ->where('sender_type', 'admin')
                ->whereJsonContains('metadata->ai_generated', true)
                ->orderBy('created_at', 'desc')
                ->first();

            $validationContext = $this->buildValidationContext();

            if ($this->isGenericOrRepeated($response, $lastAiMessage?->content)) {
                Log::info('AI response was generic or repeated, using fallback', [
                    'conversation_id' => $conversation->id,
                    'response' => $response
                ]);

                $resolution = $this->resolveGenericRepeatedOutcome(
                    $lastCustomerMessage->content,
                    $lastAiMessage?->content
                );
            } else {
                $resolution = $this->fallbackStrategy->resolveModelOutcome(
                    $response,
                    null,
                    $validationContext,
                    null,
                    function (array $violations) use ($conversation, $lastCustomerMessage, $lastAiMessage): array {
                        return $this->attemptSuggestionRegeneration(
                            $conversation,
                            $lastCustomerMessage,
                            $lastAiMessage?->content,
                            $violations
                        );
                    },
                    $conversation->id
                );
            }

            $this->memoryService->appendMessage($conversation->id, 'assistant', $resolution->reply());

            return $this->finalizeOutcome($conversation, $resolution, $startedAt);

        } catch (\Exception $e) {
            Log::error('Failed to generate AI response', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->metrics->recordProviderIncident($conversation->id, 'omnichannel', 'exception');
            $resolution = $this->fallbackStrategy->resolveStaticReason(ChatbotOutcomeReason::PROVIDER_EXCEPTION);
            $this->memoryService->appendMessage($conversation->id, 'assistant', $resolution->reply());

            return $this->finalizeOutcome($conversation, $resolution, $startedAt);
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
            $lastCustomerMessage = $conversation->messages()
                ->where('sender_type', 'customer')
                ->latest('created_at')
                ->first();

            $discoveryDecision = [
                'sent' => false,
                'products_found' => 0,
                'products_attached' => 0,
                'carousel_suppressed' => false,
            ];

            if ($lastCustomerMessage) {
                $discoveryDecision = $this->trySendDiscoveryCarousel($conversation, (string) $lastCustomerMessage->content);
            }

            if ($discoveryDecision['sent']) {
                return true;
            }

            $outcome = $this->generateResponseOutcome($conversation);
            $response = $outcome['reply'] ?? null;
            $resolution = $outcome['resolution'] ?? null;

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
                    'generated_at' => now()->toIso8601String(),
                    'fallback_reason' => $resolution?->fallbackReason(),
                    'validation_passed' => $resolution?->validationPassed(),
                    'validation_violations' => $resolution?->validationViolations() ?? [],
                    'georgian_passed' => $resolution?->georgianPassed(),
                    'regeneration_attempted' => $resolution?->regenerationAttempted() ?? false,
                    'regeneration_succeeded' => $resolution?->regenerationSucceeded() ?? false,
                    'products_found' => $discoveryDecision['products_found'],
                    'products_attached' => $discoveryDecision['products_attached'],
                    'carousel_suppressed' => $discoveryDecision['carousel_suppressed'],
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
                $messengerUserId = $platformUserIds['facebook'] ?? $platformUserIds['messenger'] ?? null;

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

    /**
     * @return array{sent:bool,products_found:int,products_attached:int,carousel_suppressed:bool}
     */
    protected function trySendDiscoveryCarousel(Conversation $conversation, string $query): array
    {
        if (!$this->isDiscoveryQuery($query)) {
            return [
                'sent' => false,
                'products_found' => 0,
                'products_attached' => 0,
                'carousel_suppressed' => false,
            ];
        }

        if (!in_array($conversation->platform, ['facebook', 'instagram', 'whatsapp', 'messenger'], true)) {
            return [
                'sent' => false,
                'products_found' => 0,
                'products_attached' => 0,
                'carousel_suppressed' => false,
            ];
        }

        $normalizedQuery = $this->policy->normalizeIncomingMessage($query);
        $matches = $this->hybridSearch->hybridSearch($normalizedQuery !== '' ? $normalizedQuery : $query, 10);
        $products = $this->carouselBuilder->productsFromMatches($matches, 2);
        $selectedProducts = $this->productSelection->selectDiscoveryProductsForCarousel($products, $query);

        $productsFound = count($products);
        $productsAttached = count($selectedProducts);
        $carouselSuppressed = $productsFound > 0 && $productsAttached === 0;

        if ($productsAttached < 2) {
            return [
                'sent' => false,
                'products_found' => $productsFound,
                'products_attached' => $productsAttached,
                'carousel_suppressed' => $carouselSuppressed,
            ];
        }

        $customer = $conversation->customer;
        $platform = $conversation->platform === 'messenger' ? 'facebook' : $conversation->platform;
        $platformIds = is_array($customer->platform_user_ids) ? $customer->platform_user_ids : [];
        $senderId = (string) ($platformIds[$platform] ?? $platformIds['messenger'] ?? '');

        if ($senderId === '') {
            return [
                'sent' => false,
                'products_found' => $productsFound,
                'products_attached' => 0,
                'carousel_suppressed' => false,
            ];
        }

        $result = $platform === 'whatsapp'
            ? $this->whatsAppService->sendCarousel($senderId, (string) $conversation->platform_conversation_id, $selectedProducts)
            : $this->metaApiService->sendCarousel($senderId, $selectedProducts, $platform);

        if (!(bool) ($result['success'] ?? false)) {
            Log::warning('Carousel send failed; fallback to text reply', [
                'conversation_id' => $conversation->id,
                'platform' => $platform,
                'error' => $result['error'] ?? 'unknown',
            ]);

            return [
                'sent' => false,
                'products_found' => $productsFound,
                'products_attached' => 0,
                'carousel_suppressed' => false,
            ];
        }

        $replyText = 'გიზიარებთ შესაბამის მოდელებს 👇';

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $conversation->customer_id,
            'sender_type' => 'admin',
            'sender_id' => 0,
            'sender_name' => 'AI Assistant',
            'content' => $replyText,
            'platform_message_id' => 'ai_carousel_' . uniqid(),
            'metadata' => [
                'ai_generated' => true,
                'auto_reply' => true,
                'type' => 'carousel',
                'cards_count' => $productsAttached,
                'fallback_reason' => null,
                'validation_passed' => true,
                'validation_violations' => [],
                'georgian_passed' => true,
                'regeneration_attempted' => false,
                'regeneration_succeeded' => false,
                'products_found' => $productsFound,
                'products_attached' => $productsAttached,
                'carousel_suppressed' => false,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);

        $conversation->update([
            'last_message_at' => now(),
        ]);

        $this->memoryService->appendMessage($conversation->id, 'assistant', $replyText);

        Log::info('Discovery carousel sent', [
            'conversation_id' => $conversation->id,
            'platform' => $platform,
            'cards_count' => $productsAttached,
        ]);

        return [
            'sent' => true,
            'products_found' => $productsFound,
            'products_attached' => $productsAttached,
            'carousel_suppressed' => false,
        ];
    }

    protected function isDiscoveryQuery(string $query): bool
    {
        $normalized = mb_strtolower($this->policy->normalizeIncomingMessage($query));

        $patterns = [
            '/\b(show me|what do you have|options|catalog|recommend)\b/u',
            '/(მაჩვენე|რა გაქვთ|ვარიანტ|კატალოგ|მირჩიე|რეკომენდ)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
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

    protected function buildValidationContext(): array
    {
        $products = Product::active()
            ->withSum('variants as total_stock', 'quantity')
            ->orderByDesc('updated_at')
            ->take(12)
            ->get();

        $productRows = $products->map(function (Product $product): array {
            return [
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => is_numeric($product->price) ? (float) $product->price : null,
                'sale_price' => is_numeric($product->sale_price) ? (float) $product->sale_price : null,
                'is_in_stock' => (int) ($product->total_stock ?? 0) > 0,
                'url' => url('/products/' . $product->slug),
            ];
        })->values()->all();

        $allowedUrls = [
            rtrim(route('home'), '/'),
            rtrim(route('products.index'), '/'),
            rtrim(route('contact'), '/'),
        ];

        foreach ($productRows as $productRow) {
            if (!empty($productRow['url'])) {
                $allowedUrls[] = rtrim((string) $productRow['url'], '/');
            }
        }

        return [
            'products' => $productRows,
            'allowed_urls' => array_values(array_unique(array_filter($allowedUrls))),
        ];
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
                $response = 'ამჟამად გვაქვს MyTechnic-ის რამდენიმე მოდელი. რომელი ფუნქციები გაინტერესებთ (GPS, SOS, ზარები, კამერა) და შევარჩევთ შესაბამისს.';
            } elseif ($looksLikeStockQuestion) {
                $response = 'მარაგების დასაზუსტებლად გვითხარით სასურველი მოდელი/ფერი და დაგიდასტურებთ.';
            } elseif ($looksLikeDeliveryQuestion) {
                $response = 'მიწოდება უფასოა მთელი ქვეყნის მასშტაბით. მითხარით ქალაქი და სასურველი ვადა.';
            } elseif ($looksLikePriceQuestion) {
                $response = 'ფასი დამოკიდებულია მოდელზე და ფუნქციებზე. რომელი მოდელი ან ფუნქცია გაინტერესებთ?';
            } else {
                $response = 'გმადლობთ მომართვისთვის! გვითხარით რომელი ფუნქცია ან მოდელი გაინტერესებთ (GPS, SOS, ზარები, კამერა) და დაგეხმარებით.';
            }
        } else {
            if ($looksLikeModelQuestion) {
                $response = 'We have multiple MyTechnic models available. Which features are most important to you (GPS, SOS, calling, or camera)?';
            } elseif ($looksLikeStockQuestion) {
                $response = 'To confirm stock, please tell us the model and color you want.';
            } elseif ($looksLikeDeliveryQuestion) {
                $response = 'Delivery is free nationwide across Georgia. What city are you in and when do you need it?';
            } elseif ($looksLikePriceQuestion) {
                $response = 'Prices depend on the model and features. Which MyTechnic model or features are you interested in?';
            } else {
                $response = 'Thanks for reaching out! Which MyTechnic model or features are you interested in (GPS, SOS, battery, or calling)?';
            }
        }

        if ($lastAiContent && $this->normalizeText($response) === $this->normalizeText($lastAiContent)) {
            $response .= $isGeorgian
                ? ' ასევე თუ შეგიძლიათ, მითხარით სასურველი ბიუჯეტი.'
                : ' Also, if you have a budget in mind, please share it.';
        }

        return $response;
    }

    /**
     * @param array<int, array<string, mixed>> $violations
     * @return array{reply:string,reason:?string}
     */
    protected function attemptSuggestionRegeneration(
        Conversation $conversation,
        Message $lastCustomerMessage,
        ?string $lastAiContent,
        array $violations
    ): array {
        $regenerationMessage = new Message([
            'conversation_id' => $conversation->id,
            'customer_id' => $conversation->customer_id,
            'sender_type' => 'customer',
            'sender_id' => $lastCustomerMessage->sender_id,
            'sender_name' => $lastCustomerMessage->sender_name,
            'content' => $this->buildSuggestionRegenerationPrompt(
                (string) $lastCustomerMessage->content,
                $lastAiContent,
                $violations
            ),
            'platform_message_id' => (string) ($lastCustomerMessage->platform_message_id ?? 'omni_regen'),
        ]);

        $suggestions = $this->aiSuggestionService->generateSuggestions($conversation, $regenerationMessage, 1);
        $reply = trim((string) ($suggestions[0] ?? ''));

        if ($reply === '') {
            return [
                'reply' => '',
                'reason' => ChatbotOutcomeReason::VALIDATOR_RETRY_FAILED,
            ];
        }

        return [
            'reply' => $reply,
            'reason' => null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $violations
     */
    protected function buildSuggestionRegenerationPrompt(string $messageText, ?string $lastAiContent, array $violations): string
    {
        $issueLines = collect($violations)
            ->map(function (array $violation): string {
                $type = (string) ($violation['type'] ?? 'unknown');
                $details = collect($violation)
                    ->reject(fn ($value, $key): bool => $key === 'type' || $value === null || $value === '')
                    ->map(fn ($value, $key): string => $key . '=' . (is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)))
                    ->implode(', ');

                return $details !== '' ? '- ' . $type . ' (' . $details . ')' : '- ' . $type;
            })
            ->implode("\n");

        $parts = [
            trim($messageText),
            'უპასუხე თავიდან მხოლოდ ქართულად.',
            'არ გაიმეორო წინა ბუნდოვანი ან დაუდასტურებელი პასუხი.',
            'ფასი, მარაგი და ბმულები დააფუძნე მხოლოდ რეალურად ხელმისაწვდომ კონტექსტზე.',
            'Validation issues to fix:',
            $issueLines !== '' ? $issueLines : '- unknown',
        ];

        if ($lastAiContent !== null && trim($lastAiContent) !== '') {
            $parts[] = 'წინა პასუხი იყო: ' . trim($lastAiContent);
        }

        return implode("\n\n", array_filter($parts, fn (?string $part): bool => $part !== null && trim($part) !== ''));
    }

    protected function resolveGenericRepeatedOutcome(string $messageText, ?string $lastAiContent = null): ChatbotFallbackResolution
    {
        $reply = $this->buildFallbackResponse($messageText, $lastAiContent);

        return new ChatbotFallbackResolution(
            $reply,
            ChatbotOutcomeReason::GENERIC_REPEATED,
            true,
            [],
            $this->policy->passesStrictGeorgianQa($reply),
            false,
            false
        );
    }

    /**
     * @return array{reply:string,resolution:ChatbotFallbackResolution,response_time_ms:int}
     */
    protected function finalizeOutcome(Conversation $conversation, ChatbotFallbackResolution $resolution, float $startedAt): array
    {
        $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->metrics->recordOmnichannelResponseQuality(
            $conversation->id,
            ChatbotOutcomeReason::isFallbackReason($resolution->fallbackReason()),
            $resolution->georgianPassed(),
            $resolution->fallbackReason(),
            $resolution->regenerationAttempted(),
            $resolution->regenerationSucceeded(),
            $responseTimeMs
        );

        return [
            'reply' => $resolution->reply(),
            'resolution' => $resolution,
            'response_time_ms' => $responseTimeMs,
        ];
    }
}
