<?php

namespace App\Services\Chatbot;

use App\Models\ContactSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Chatbot\ChatbotFallbackResolution;
use App\Services\Chatbot\ChatbotFallbackStrategyService;
use App\Services\Chatbot\WidgetTraceLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatPipelineService
{
    private const MODEL_HISTORY_LIMIT = 4;

    private const MODEL_HISTORY_MAX_CHARS = 160;

    private const RAG_CONTEXT_MAX_CHARS = 1200;

    private const PRODUCT_CONTEXT_LIMIT = 4;

    private const MIN_REALISTIC_PRODUCT_PRICE = 0.5;

    public function __construct(
        private IntentAnalyzerService $intentAnalyzer,
        private SmartSearchOrchestrator $smartSearch,
        private InputGuardService $inputGuard,
        private ConversationMemoryService $memoryService,
        private UnifiedAiPolicyService $policy,
        private AdaptiveLearningService $adaptiveLearning,
        private ResponseValidatorService $responseValidator,
        private ChatbotQualityMetricsService $metrics,
        private ChatbotFallbackStrategyService $fallbackStrategy,
        private WidgetTraceLogger $widgetTrace
    ) {
    }

    public function process(string $message, int $conversationId, array $options = []): PipelineResult
    {
        $startedAt = microtime(true);
        $incomingMessage = trim($message);
        $customerId = is_numeric($options['customer_id'] ?? null) ? (int) $options['customer_id'] : 0;
        $trace = $this->buildTraceContext($conversationId, $customerId, $options);

        $this->traceWidget('pipeline.received', [
            'incoming_message' => $incomingMessage,
            'next_step' => 'run_input_guard',
        ], $trace);

        $guardResult = $this->inputGuard->inspect($incomingMessage);
        $safeIncomingMessage = trim($guardResult->sanitizedInput());

        if ($safeIncomingMessage === '') {
            $safeIncomingMessage = $incomingMessage;
        }

        $normalizedMessage = $this->policy->normalizeIncomingMessage($safeIncomingMessage);

        $this->traceWidget('pipeline.guard_evaluated', [
            'guard_allowed' => $guardResult->allowed(),
            'guard_reason' => $guardResult->reason(),
            'sanitized_message' => $safeIncomingMessage,
            'normalized_message' => $normalizedMessage,
            'next_step' => $guardResult->allowed() ? 'append_message_to_memory' : 'return_guard_fallback',
        ], $trace);

        if (!$guardResult->allowed()) {
            $resolution = $this->fallbackStrategy->resolveGuardOutcome($guardResult);

            $this->traceWidget('pipeline.guard_blocked', [
                'reply' => $resolution->reply(),
                'fallback_reason' => $resolution->fallbackReason(),
            ], $trace);

            $this->memoryService->appendMessage($conversationId, 'user', $normalizedMessage);
            $this->memoryService->appendMessage($conversationId, 'assistant', $resolution->reply());

            return $this->finalizeResult(
                $resolution,
                $conversationId,
                '',
                null,
                ['products' => [], 'allowed_urls' => []],
                false,
                $guardResult->reason(),
                $startedAt,
                $customerId,
                false,
                $trace
            );
        }

        $this->memoryService->appendMessage($conversationId, 'user', $normalizedMessage);
        $memoryContext = $this->memoryService->getContext($conversationId);
        $storedHistory = $this->trimConversationHistory($memoryContext['history'] ?? []);
        $storedPreferences = is_array($memoryContext['preferences'] ?? null) ? $memoryContext['preferences'] : [];
        $useConversationContext = $this->memoryService->shouldUseConversationContext($normalizedMessage);
        $history = $useConversationContext ? $storedHistory : [];
        $preferences = $this->memoryService->scopePreferencesForMessage($storedPreferences, $normalizedMessage);

        $this->traceWidget('pipeline.memory_loaded', [
            'use_conversation_context' => $useConversationContext,
            'history_count' => count($history),
            'preferences' => $preferences,
            'next_step' => 'check_greeting_or_analyze_intent',
        ], $trace);

        if ($this->policy->isGreetingOnly($normalizedMessage)) {
            $resolution = $this->fallbackStrategy->resolveGreetingOutcome();
            $this->memoryService->appendMessage($conversationId, 'assistant', $resolution->reply());

            $this->traceWidget('pipeline.greeting_shortcut', [
                'reply' => $resolution->reply(),
                'fallback_reason' => $resolution->fallbackReason(),
            ], $trace);

            return $this->finalizeResult(
                $resolution,
                $conversationId,
                '',
                IntentResult::fallback($normalizedMessage),
                ['products' => [], 'allowed_urls' => []],
                true,
                null,
                $startedAt,
                $customerId,
                false,
                $trace
            );
        }

        $intentResult = $this->intentAnalyzer->analyze($normalizedMessage, $history, $preferences, $trace);

        $this->traceWidget('pipeline.intent_resolved', [
            'intent' => $intentResult->intent(),
            'confidence' => $intentResult->confidence(),
            'standalone_query' => $intentResult->standaloneQuery(),
            'needs_product_data' => $intentResult->needsProductData(),
            'search_keywords' => $intentResult->searchKeywords(),
            'intent_fallback' => $intentResult->isFallback(),
            'next_step' => 'route_shortcuts_or_search',
        ], $trace);

        $routedReply = $this->routeNonSearchIntent($intentResult);
        if ($routedReply !== null) {
            $resolution = $this->fallbackStrategy->resolveIntentOutcome($intentResult, $routedReply);
            $this->memoryService->appendMessage($conversationId, 'assistant', $resolution->reply());

            $this->traceWidget('pipeline.intent_shortcut_used', [
                'intent' => $intentResult->intent(),
                'reply' => $resolution->reply(),
                'fallback_reason' => $resolution->fallbackReason(),
            ], $trace);

            return $this->finalizeResult(
                $resolution,
                $conversationId,
                '',
                $intentResult,
                ['products' => [], 'allowed_urls' => []],
                true,
                null,
                $startedAt,
                $customerId,
                false,
                $trace
            );
        }

        $this->traceWidget('pipeline.search_started', [
            'requires_search' => $intentResult->requiresSearch(),
            'standalone_query' => $intentResult->standaloneQuery(),
            'intent' => $intentResult->intent(),
            'next_step' => $intentResult->requiresSearch() ? 'build_rag_context_and_product_matches' : 'skip_search_and_prepare_model_prompt',
        ], $trace);

        $searchContext = $intentResult->requiresSearch()
            ? $this->smartSearch->search($intentResult)
            : new SearchContext('', collect(), null, null);

        $products = $this->filterProductsForResponseContext($searchContext->products());
        $contactSettings = ContactSetting::allKeyed();
        $validationContext = $this->buildValidationContext($products, $contactSettings);
        $requestedProduct = $this->resolveRequestedProductForContext($normalizedMessage, $intentResult, $searchContext, $products);
        $effectiveRagContextText = $this->resolvePromptRagContext($intentResult, $searchContext, $products, $requestedProduct);

        $this->traceWidget('pipeline.search_completed', [
            'rag_context_present' => $searchContext->ragContext() !== '',
            'rag_context_chars' => mb_strlen($searchContext->ragContext()),
            'prompt_rag_context_chars' => mb_strlen($effectiveRagContextText),
            'product_count' => $products->count(),
            'requested_product' => $requestedProduct?->slug,
            'product_not_found_message' => $searchContext->productNotFoundMessage(),
            'matched_products' => $products->map(fn (Product $product): array => [
                'name' => $product->name,
                'slug' => $product->slug,
                'stock' => (int) ($product->total_stock ?? 0),
            ])->values()->all(),
            'next_step' => 'prepare_main_model_request',
        ], $trace);

        $apiKey = (string) config('services.openai.key');
        $model = (string) config('services.openai.model', 'gpt-4.1-mini');
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        if ($apiKey === '') {
            $this->traceWidget('pipeline.model_skipped', [
                'reason' => 'missing_openai_key',
            ], $trace);

            return $this->finalizeResult(
                $this->fallbackStrategy->resolveStaticReason(ChatbotOutcomeReason::CHATBOT_DISABLED),
                $conversationId,
                $searchContext->ragContext(),
                $intentResult,
                $validationContext,
                true,
                null,
                $startedAt,
                $customerId,
                false,
                $trace
            );
        }

        $systemPrompt = $this->buildSystemPrompt($preferences, $intentResult);
        $context = $this->buildContext(
            $normalizedMessage,
            $intentResult,
            $searchContext,
            $contactSettings,
            $products,
            $requestedProduct,
            $effectiveRagContextText,
            $preferences
        );

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($history as $historyEntry) {
            $role = data_get($historyEntry, 'role');
            $content = trim((string) data_get($historyEntry, 'content', ''));

            if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        $userQuestion = trim($intentResult->standaloneQuery()) !== ''
            ? $intentResult->standaloneQuery()
            : $normalizedMessage;

        $messages[] = [
            'role' => 'user',
            'content' => $context . "\n\nUser question: " . $userQuestion,
        ];

        $completion = $this->requestModelCompletion($apiKey, $baseUrl, $model, $messages, $conversationId, 'main_response', $trace);
        $modelOutput = $this->normalizeExcludedFeatureWording((string) $completion['reply'], $preferences);
        $nonGeorgianModelOutput = preg_match('/\p{Georgian}/u', $modelOutput) !== 1;
        $resolution = $this->fallbackStrategy->resolveModelOutcome(
            $modelOutput,
            $completion['reason'],
            $validationContext,
            $intentResult,
            function (array $violations) use ($apiKey, $baseUrl, $model, $messages, $modelOutput, $conversationId, $trace): array {
                return $this->attemptValidatedRegeneration(
                    $apiKey,
                    $baseUrl,
                    $model,
                    $messages,
                    $modelOutput,
                    $violations,
                    $conversationId,
                    $trace
                );
            },
            $conversationId
        );

        $this->traceWidget('pipeline.model_resolved', [
            'reply' => $resolution->reply(),
            'fallback_reason' => $resolution->fallbackReason(),
            'validation_passed' => $resolution->validationPassed(),
            'validation_violations' => $resolution->validationViolations(),
            'georgian_passed' => $resolution->georgianPassed(),
            'regeneration_attempted' => $resolution->regenerationAttempted(),
            'regeneration_succeeded' => $resolution->regenerationSucceeded(),
            'next_step' => 'persist_assistant_memory_and_finalize',
        ], $trace);

        $this->memoryService->appendMessage($conversationId, 'assistant', $resolution->reply());

        return $this->finalizeResult(
            $resolution,
            $conversationId,
            $effectiveRagContextText,
            $intentResult,
            $validationContext,
            true,
            null,
            $startedAt,
            $customerId,
            $nonGeorgianModelOutput,
            $trace
        );
    }

    private function normalizeExcludedFeatureWording(string $reply, array $preferences): string
    {
        $excludedFeatures = is_array($preferences['excluded_features'] ?? null)
            ? array_values(array_unique(array_filter($preferences['excluded_features'])))
            : [];

        if ($reply === '' || $excludedFeatures === []) {
            return $reply;
        }

        $normalized = $reply;

        if (in_array('camera', $excludedFeatures, true)) {
            $normalized = preg_replace('/კამერ(?:ა|ის)?\s+გარეშე/u', 'კამერა თქვენთვის პრიორიტეტული არ არის', $normalized) ?? $normalized;
            $normalized = preg_replace('/და\s+კამერა\s+თქვენთვის\s+პრიორიტეტული\s+არ\s+არის\s+მოდელი/u', 'და მოდელი', $normalized) ?? $normalized;
            $normalized = preg_replace('/კამერ(?:ას|ა)?\s+არ\s+(?:აქვს|მოიცავს)/u', 'კამერა თქვენთვის პრიორიტეტული არ არის', $normalized) ?? $normalized;
        }

        if (in_array('calls', $excludedFeatures, true)) {
            $normalized = preg_replace('/ზარ(?:ი|ები|ების)?\s+გარეშე/u', 'ზარის ფუნქცია თქვენთვის პრიორიტეტული არ არის', $normalized) ?? $normalized;
            $normalized = preg_replace('/და\s+ზარის\s+ფუნქცია\s+თქვენთვის\s+პრიორიტეტული\s+არ\s+არის\s+მოდელი/u', 'და მოდელი', $normalized) ?? $normalized;
            $normalized = preg_replace('/თქვენი\s+მოთხოვნის\s+შესაბამისად,\s*GPS-იანი\s+და\s+მოდელი\s+ჩვენს\s+კატალოგში\s+არ\s+არის,\s+რადგან\s+([^\.]+)\./u', 'თქვენთვის GPS პრიორიტეტულია, ხოლო ზარის ფუნქცია აუცილებელი არაა. კატალოგში GPS-იანი ვარიანტებიდან შეგვიძლია გირჩიოთ $1.', $normalized) ?? $normalized;
            $normalized = preg_replace('/ზარ(?:ებს|ი)?\s+არ\s+(?:აქვს|მოიცავს)/u', 'ზარის ფუნქცია თქვენთვის პრიორიტეტული არ არის', $normalized) ?? $normalized;
        }

        if (in_array('camera', $excludedFeatures, true) && in_array('calls', $excludedFeatures, true)) {
            $normalized = preg_replace('/კამერ(?:ას|ა)?\s+და\s+ზარ(?:ებს|ი)?\s+არ\s+(?:აქვს|მოიცავს)/u', 'კამერა და ზარის ფუნქცია თქვენთვის პრიორიტეტული არ არის', $normalized) ?? $normalized;
        }

        return $normalized;
    }

    private function requestModelCompletion(
        string $apiKey,
        string $baseUrl,
        string $model,
        array $messages,
        int $conversationId,
        string $requestStage = 'main_response',
        array $trace = []
    ): array {
        $requestContext = [
            'request_stage' => $requestStage,
            'model' => $model,
            'base_url' => $baseUrl,
            'message_count' => count($messages),
            'next_step' => 'call_openai_chat_completions',
        ];

        if ($this->widgetTrace->payloadsEnabled()) {
            $requestContext['messages'] = $this->formatMessagesForTrace($messages);
        }

        $this->traceWidget('pipeline.model_request_sent', $requestContext, $trace);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post($baseUrl . '/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.4,
                    'max_tokens' => 400,
                ]);

            if (!$response->successful()) {
                $this->traceWidget('pipeline.model_request_failed', [
                    'request_stage' => $requestStage,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ], $trace);

                Log::warning('Chat pipeline OpenAI request failed', [
                    'conversation_id' => $conversationId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'reply' => '',
                    'reason' => ChatbotOutcomeReason::PROVIDER_UNAVAILABLE,
                ];
            }

            $reply = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

            if ($reply === '') {
                $this->traceWidget('pipeline.model_request_failed', [
                    'request_stage' => $requestStage,
                    'reason' => 'empty_model_output',
                    'status' => $response->status(),
                ], $trace);

                return [
                    'reply' => '',
                    'reason' => ChatbotOutcomeReason::EMPTY_MODEL_OUTPUT,
                ];
            }

            $this->traceWidget('pipeline.model_response_received', [
                'request_stage' => $requestStage,
                'status' => $response->status(),
                'finish_reason' => data_get($response->json(), 'choices.0.finish_reason'),
                'usage' => data_get($response->json(), 'usage', []),
                'reply' => $reply,
                'next_step' => 'validate_and_resolve_model_output',
            ], $trace);

            return [
                'reply' => $reply,
                'reason' => null,
            ];
        } catch (\Throwable $exception) {
            $this->traceWidget('pipeline.model_request_failed', [
                'request_stage' => $requestStage,
                'reason' => 'provider_exception',
                'error' => $exception->getMessage(),
            ], $trace);

            Log::warning('Chat pipeline OpenAI exception', [
                'conversation_id' => $conversationId,
                'error' => $exception->getMessage(),
            ]);

            return [
                'reply' => '',
                'reason' => ChatbotOutcomeReason::PROVIDER_EXCEPTION,
            ];
        }
    }

    private function attemptValidatedRegeneration(
        string $apiKey,
        string $baseUrl,
        string $model,
        array $messages,
        string $invalidReply,
        array $violations,
        int $conversationId,
        array $trace = []
    ): array {
        $regenerationMessages = $messages;
        $regenerationMessages[] = [
            'role' => 'assistant',
            'content' => $invalidReply,
        ];
        $regenerationMessages[] = [
            'role' => 'user',
            'content' => $this->buildValidationRegenerationInstruction($violations),
        ];

        $this->traceWidget('pipeline.regeneration_requested', [
            'violations' => $violations,
            'invalid_reply' => $invalidReply,
            'next_step' => 'retry_main_model_with_validation_feedback',
        ], $trace);

        return $this->requestModelCompletion($apiKey, $baseUrl, $model, $regenerationMessages, $conversationId, 'validation_regeneration', $trace);
    }

    private function buildValidationRegenerationInstruction(array $violations): string
    {
        $violationLines = collect($violations)
            ->map(function (array $violation): string {
                $type = (string) ($violation['type'] ?? 'unknown');
                $details = collect($violation)
                    ->reject(fn ($value, $key): bool => $key === 'type' || $value === null || $value === '')
                    ->map(fn ($value, $key): string => $key . '=' . (is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)))
                    ->implode(', ');

                return $details !== '' ? '- ' . $type . ' (' . $details . ')' : '- ' . $type;
            })
            ->implode("\n");

        return implode("\n", [
            'Re-answer the same user request in Georgian.',
            'Your previous reply violated response integrity checks. Fix the answer and keep it concise.',
            'Do not invent prices, stock claims, or URLs that are not supported by the provided context.',
            'Validation issues to fix:',
            $violationLines !== '' ? $violationLines : '- unknown',
        ]);
    }

    private function makeResult(
        string $reply,
        int $conversationId,
        string $ragContextText,
        ?IntentResult $intentResult,
        array $validationContext,
        bool $guardAllowed,
        ?string $guardReason,
        bool $validationPassed,
        array $validationViolations,
        bool $georgianPassed,
        int $responseTimeMs,
        ?string $fallbackReason = null,
        bool $regenerationAttempted = false,
        bool $regenerationSucceeded = false
    ): PipelineResult {
        return new PipelineResult(
            $reply,
            $conversationId,
            $ragContextText,
            $intentResult,
            $validationContext,
            $guardAllowed,
            $guardReason,
            $validationPassed,
            $validationViolations,
            $georgianPassed,
            $responseTimeMs,
            $fallbackReason,
            $regenerationAttempted,
            $regenerationSucceeded
        );
    }

    private function finalizeResult(
        ChatbotFallbackResolution $resolution,
        int $conversationId,
        string $ragContextText,
        ?IntentResult $intentResult,
        array $validationContext,
        bool $guardAllowed,
        ?string $guardReason,
        float $startedAt,
        int $customerId,
        bool $nonGeorgianModelOutput = false,
        array $trace = []
    ): PipelineResult {
        $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->metrics->recordWidgetResponseQuality(
            $conversationId,
            $customerId,
            ChatbotOutcomeReason::isFallbackReason($resolution->fallbackReason()),
            $nonGeorgianModelOutput,
            $resolution->fallbackReason(),
            $resolution->regenerationAttempted(),
            $resolution->regenerationSucceeded(),
            $responseTimeMs
        );

        $this->traceWidget('pipeline.completed', [
            'reply' => $resolution->reply(),
            'fallback_reason' => $resolution->fallbackReason(),
            'validation_passed' => $resolution->validationPassed(),
            'validation_violations' => $resolution->validationViolations(),
            'georgian_passed' => $resolution->georgianPassed(),
            'regeneration_attempted' => $resolution->regenerationAttempted(),
            'regeneration_succeeded' => $resolution->regenerationSucceeded(),
            'response_time_ms' => $responseTimeMs,
            'rag_context_chars' => mb_strlen($ragContextText),
            'next_step' => 'return_pipeline_result_to_controller',
        ], $trace);

        return $this->makeResult(
            $resolution->reply(),
            $conversationId,
            $ragContextText,
            $intentResult,
            $validationContext,
            $guardAllowed,
            $guardReason,
            $resolution->validationPassed(),
            $resolution->validationViolations(),
            $resolution->georgianPassed(),
            $responseTimeMs,
            $resolution->fallbackReason(),
            $resolution->regenerationAttempted(),
            $resolution->regenerationSucceeded()
        );
    }

    private function buildTraceContext(int $conversationId, int $customerId, array $options): array
    {
        return array_filter([
            'trace_id' => ($options['widget_trace_id'] ?? null) ?: null,
            'conversation_id' => $conversationId,
            'customer_id' => $customerId > 0 ? $customerId : null,
        ], fn ($value) => $value !== null);
    }

    private function traceWidget(string $step, array $context, array $trace): void
    {
        if (!$this->widgetTrace->enabled()) {
            return;
        }

        $this->widgetTrace->logStep($step, array_merge($trace, $context));
    }

    private function formatMessagesForTrace(array $messages): array
    {
        return collect($messages)
            ->map(function (array $message): array {
                return [
                    'role' => $message['role'] ?? null,
                    'content' => $message['content'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function routeNonSearchIntent(IntentResult $intentResult): ?string
    {
        return match ($intentResult->intent()) {
            'out_of_domain' => $intentResult->category() === 'adult_smartwatch'
                ? 'ჩვენი კატალოგი ამ ეტაპზე ძირითადად საბავშვო სმარტსაათებზეა ფოკუსირებული, ამიტომ ზრდასრულის მოდელი არ გვაქვს. თუ ბავშვისთვის ეძებთ, სიამოვნებით შეგირჩევთ შესაბამის ვარიანტებს.'
                : 'მე მხოლოდ MyTechnic-ის სმარტსაათებთან დაკავშირებულ კითხვებზე გეხმარებით. თუ გსურთ, სიამოვნებით შეგირჩევთ თქვენთვის შესაფერის მოდელს.',
            'clarification_needed' => 'რომელი კონკრეტული მოდელი გაინტერესებთ? თუ სახელწოდებას დამიწერთ, ზუსტ ფასს, მარაგსა და მახასიათებლებს მოგაწვდით.',
            default => null,
        };
    }

    private function buildSystemPrompt(array $preferences, IntentResult $intentResult): string
    {
        $systemPrompt = $this->policy->websiteSystemPrompt();

        $learningLessons = $this->adaptiveLearning->buildLessonsText();
        if ($learningLessons !== '') {
            $systemPrompt .= "\n\n" . $learningLessons;
        }

        if ($preferences !== []) {
            $preferenceParts = [];

            if (isset($preferences['budget_max_gel'])) {
                $preferenceParts[] = 'ბიუჯეტი: ' . $preferences['budget_max_gel'] . ' ₾-მდე';
            }

            if (!empty($preferences['color'])) {
                $preferenceParts[] = 'სასურველი ფერი: ' . $preferences['color'];
            }

            if (!empty($preferences['size'])) {
                $preferenceParts[] = 'სასურველი ზომა: ' . $preferences['size'];
            }

            if (!empty($preferences['features']) && is_array($preferences['features'])) {
                $preferenceParts[] = 'საინტერესო ფუნქციები: ' . implode(', ', $preferences['features']);
            }

            if (!empty($preferences['excluded_features']) && is_array($preferences['excluded_features'])) {
                $preferenceParts[] = 'არასასურველი ფუნქციები: ' . implode(', ', $preferences['excluded_features']);
                $preferenceParts[] = 'არ თქვა, რომ კონკრეტულ მოდელს ეს ფუნქცია არ აქვს, თუ ეს ლაივ კონტექსტში აშკარად არ ჩანს';
                $preferenceParts[] = 'არ გამოიყენო ფორმულირებები "არ აქვს", "არ მოიცავს" ან "გარეშეა", თუ კონტექსტი ამას პირდაპირ არ ამტკიცებს';
            }

            if (isset($preferences['budget_max_gel'])) {
                $preferenceParts[] = 'თუ ბიუჯეტში მოთავსებული მოდელი არსებობს, პირველად ის ახსენე; ბიუჯეტს ზემოთ მყოფი ვარიანტი მხოლოდ მეორად ალტერნატივად შესთავაზე';
            }

            if ($preferenceParts !== []) {
                $systemPrompt .= "\n\nUSER PREFERENCES (მომხმარებლის პრეფერენციები):\n" . implode("\n", array_map(fn ($p) => '• ' . $p, $preferenceParts));
            }
        }

        $summaryLines = [
            'standalone_query: ' . (trim($intentResult->standaloneQuery()) !== '' ? $intentResult->standaloneQuery() : '-'),
            'intent: ' . $intentResult->intent(),
            'brand: ' . ($intentResult->brand() ?? '-'),
            'model: ' . ($intentResult->model() ?? '-'),
            'confidence: ' . $intentResult->confidence(),
        ];

        $systemPrompt .= "\n\nINTENT SUMMARY:\n" . implode("\n", array_map(fn ($line) => '- ' . $line, $summaryLines));

        return $systemPrompt;
    }

    private function buildContext(
        string $normalizedMessage,
        IntentResult $intentResult,
        SearchContext $searchContext,
        array $contactSettings,
        $products,
        ?Product $requestedProduct,
        string $effectiveRagContextText,
        array $preferences = []
    ): string {
        $contextSections = [
            'საიტის ბმულები:',
            '- მთავარი: ' . route('home'),
            '- კატალოგი: ' . route('products.index'),
            '- კონტაქტი: ' . route('contact'),
            'საკონტაქტო ინფორმაცია (ადმინისტრატორის ლაივ პარამეტრები):',
            '- ტელეფონი: ' . ($contactSettings['phone_display'] ?? ''),
            '- WhatsApp: ' . ($contactSettings['whatsapp_url'] ?? ''),
            '- ელფოსტა: ' . ($contactSettings['email'] ?? ''),
            '- მისამართი: ' . ($contactSettings['location'] ?? ''),
            '- სამუშაო საათები: ' . ($contactSettings['hours'] ?? ''),
            'Intent analysis:',
            '- standalone_query: ' . (trim($intentResult->standaloneQuery()) !== '' ? $intentResult->standaloneQuery() : '-'),
            '- intent: ' . $intentResult->intent(),
            '- confidence: ' . $intentResult->confidence(),
        ];

        if ($effectiveRagContextText !== '') {
            $contextSections[] = 'ცოდნის ბაზა:';
            $contextSections[] = $effectiveRagContextText;
        }

        if ($searchContext->productNotFoundMessage()) {
            $contextSections[] = 'მნიშვნელოვანი კონტექსტი:';
            $contextSections[] = $searchContext->productNotFoundMessage();
        }

        if ($requestedProduct instanceof Product && ($intentResult->hasSpecificProduct() || $this->messageMentionsProduct($normalizedMessage, $requestedProduct))) {
            $requestedProductContext = $this->buildRequestedProductContext($normalizedMessage, $requestedProduct);
            if ($requestedProductContext !== '') {
                $contextSections[] = 'მოთხოვნილი პროდუქტი (ზუსტი დამთხვევა ლაივ ბაზიდან):';
                $contextSections[] = $requestedProductContext;
            }
        }

        $productLines = $this->selectProductsForPromptContext($products, $intentResult, $requestedProduct, $preferences)
            ->take(self::PRODUCT_CONTEXT_LIMIT)
            ->map(function (Product $product): string {
                $price = $product->sale_price
                    ? $product->sale_price . ' ₾ (ფასდაკლება, ძველი ფასი ' . $product->price . ' ₾)'
                    : $product->price . ' ₾';

                $stockTotal = max(0, (int) ($product->total_stock ?? 0));
                $stockStatus = $stockTotal > 0 ? 'მარაგშია' : 'ამოწურულია';

                return '- ' . $product->name
                    . ' | ბმული იდენტიფიკატორი: ' . $product->slug
                    . ' | ფასი: ' . $price
                    . ' | მარაგი: ' . $stockStatus . ' (' . $stockTotal . ' ცალი)';
            })
            ->implode("\n");

        $contextSections[] = 'პროდუქტები (ლაივ მარაგი ბაზიდან):';
        $contextSections[] = $productLines !== '' ? $productLines : 'პროდუქტები ვერ მოიძებნა.';

        return implode("\n", $contextSections);
    }

    private function resolveRequestedProductForContext(string $normalizedMessage, IntentResult $intentResult, SearchContext $searchContext, $products): ?Product
    {
        $requestedProduct = $searchContext->requestedProduct();

        if ($requestedProduct instanceof Product && !$products->contains(fn (Product $product): bool => $product->id === $requestedProduct->id)) {
            $requestedProduct = null;
        }

        if (!($requestedProduct instanceof Product) && $intentResult->hasSpecificProduct()) {
            $requestedProduct = collect($products)->first(
                fn (Product $product): bool => $this->messageMentionsProduct($normalizedMessage, $product)
            );
        }

        return $requestedProduct instanceof Product ? $requestedProduct : null;
    }

    private function buildValidationContext($products, array $contactSettings): array
    {
        $productRows = collect($products)
            ->map(function (Product $product): array {
                return [
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => is_numeric($product->price) ? (float) $product->price : null,
                    'sale_price' => is_numeric($product->sale_price) ? (float) $product->sale_price : null,
                    'is_in_stock' => (int) ($product->total_stock ?? 0) > 0,
                    'url' => url('/products/' . $product->slug),
                    'image' => $product->primaryImage?->thumbnail_url ?: $product->primaryImage?->url ?: '',
                ];
            })
            ->values()
            ->all();

        $allowedUrls = [
            rtrim(route('home'), '/'),
            rtrim(route('products.index'), '/'),
            rtrim(route('contact'), '/'),
        ];

        if (!empty($contactSettings['whatsapp_url'])) {
            $allowedUrls[] = rtrim((string) $contactSettings['whatsapp_url'], '/');
        }

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

    private function trimConversationHistory(array $history): array
    {
        return collect($history)
            ->filter(fn ($item): bool => is_array($item))
            ->take(-self::MODEL_HISTORY_LIMIT)
            ->map(function (array $entry): array {
                $content = trim((string) ($entry['content'] ?? ''));

                return [
                    'role' => $entry['role'] ?? null,
                    'content' => mb_strlen($content) > self::MODEL_HISTORY_MAX_CHARS
                        ? mb_substr($content, 0, self::MODEL_HISTORY_MAX_CHARS) . '...'
                        : $content,
                ];
            })
            ->values()
            ->all();
    }

    private function compactRagContext(string $ragContext): string
    {
        $trimmed = trim($ragContext);

        if (mb_strlen($trimmed) <= self::RAG_CONTEXT_MAX_CHARS) {
            return $trimmed;
        }

        return mb_substr($trimmed, 0, self::RAG_CONTEXT_MAX_CHARS) . "\n[truncated]";
    }

    private function resolvePromptRagContext(IntentResult $intentResult, SearchContext $searchContext, $products, ?Product $requestedProduct): string
    {
        $rawRagContext = trim($searchContext->ragContext());

        if ($rawRagContext === '') {
            return '';
        }

        if ($this->shouldIncludeRagContext($intentResult, $searchContext, $products, $requestedProduct)) {
            return $this->compactRagContext($rawRagContext);
        }

        return '';
    }

    private function shouldIncludeRagContext(IntentResult $intentResult, SearchContext $searchContext, $products, ?Product $requestedProduct): bool
    {
        if ($searchContext->productNotFoundMessage()) {
            return true;
        }

        if (collect($products)->isEmpty()) {
            return true;
        }

        if (in_array($intentResult->intent(), ['general', 'comparison'], true)) {
            return true;
        }

        if ($intentResult->hasSpecificProduct()) {
            return !($requestedProduct instanceof Product);
        }

        return false;
    }

    private function selectProductsForPromptContext($products, IntentResult $intentResult, ?Product $requestedProduct, array $preferences = [])
    {
        $productCollection = collect($products)->values();

        if (isset($preferences['budget_max_gel']) && is_numeric($preferences['budget_max_gel'])) {
            $budget = (float) $preferences['budget_max_gel'];
            $productCollection = $productCollection
                ->sortBy(function (Product $product) use ($budget): array {
                    $effectivePrice = is_numeric($product->sale_price) && (float) $product->sale_price > 0
                        ? (float) $product->sale_price
                        : (is_numeric($product->price) ? (float) $product->price : INF);

                    $withinBudget = $effectivePrice <= $budget;
                    $distance = abs($effectivePrice - $budget);

                    return [$withinBudget ? 0 : 1, $distance, $effectivePrice];
                })
                ->values();
        }

        if ($requestedProduct instanceof Product) {
            return collect([$requestedProduct])
                ->merge($productCollection->reject(fn (Product $product): bool => $product->id === $requestedProduct->id))
                ->unique(fn (Product $product): int => (int) $product->id)
                ->values();
        }

        if (in_array($intentResult->intent(), ['price_query', 'stock_query'], true)) {
            return $productCollection->take(1)->values();
        }

        return $productCollection;
    }

    private function filterProductsForResponseContext($products)
    {
        return collect($products)
            ->filter(fn (Product $product): bool => $this->productHasRealisticPrice($product->sale_price, $product->price))
            ->values();
    }

    private function productHasRealisticPrice(mixed $salePrice, mixed $price): bool
    {
        $effectivePrice = is_numeric($salePrice) && (float) $salePrice > 0
            ? (float) $salePrice
            : (is_numeric($price) ? (float) $price : null);

        return $effectivePrice !== null && $effectivePrice >= self::MIN_REALISTIC_PRODUCT_PRICE;
    }

    private function requestedProductSlugForTrace(SearchContext $searchContext, $products): ?string
    {
        $requestedProduct = $searchContext->requestedProduct();

        if (!($requestedProduct instanceof Product)) {
            return null;
        }

        return collect($products)->contains(fn (Product $product): bool => $product->id === $requestedProduct->id)
            ? $requestedProduct->slug
            : null;
    }

    private function messageMentionsProduct(string $message, Product $product): bool
    {
        $haystack = Str::lower($message);

        $candidates = array_filter([
            Str::lower((string) $product->name_en),
            Str::lower((string) $product->name_ka),
            Str::lower((string) $product->slug),
            Str::replace('-', ' ', Str::lower((string) $product->slug)),
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && Str::contains($haystack, $candidate)) {
                return true;
            }
        }

        $slugTokens = collect(preg_split('/[-\s]+/u', (string) $product->slug))
            ->filter(fn ($token) => is_string($token) && mb_strlen($token) >= 4)
            ->map(fn ($token) => Str::lower($token))
            ->values();

        if ($slugTokens->isEmpty()) {
            return false;
        }

        $matchedTokens = $slugTokens
            ->filter(fn (string $token): bool => Str::contains($haystack, $token))
            ->count();

        return $slugTokens->count() === 1
            ? $matchedTokens === 1
            : $matchedTokens >= 2;
    }

    private function buildRequestedProductContext(string $message, Product $product): string
    {
        $stockTotal = max(0, (int) ($product->total_stock ?? 0));
        $stockStatus = $stockTotal > 0 ? 'მარაგშია' : 'ამოწურულია';
        $price = $product->sale_price
            ? $product->sale_price . ' ₾ (ფასდაკლება, ძველი ფასი ' . $product->price . ' ₾)'
            : $product->price . ' ₾';

        $lines = [
            '- პროდუქტი: ' . $product->name . ' | ბმული იდენტიფიკატორი: ' . $product->slug,
            '- ფასი: ' . $price,
            '- საერთო მარაგი: ' . $stockStatus . ' (' . $stockTotal . ' ცალი)',
        ];

        $matchedVariant = $this->matchVariantFromMessage($message, $product);
        if ($matchedVariant instanceof ProductVariant) {
            $variantQty = max(0, (int) $matchedVariant->quantity);
            $variantStatus = $variantQty > 0 ? 'მარაგშია' : 'ამოწურულია';

            $lines[] = '- მოთხოვნილი ვარიანტი: ' . $matchedVariant->name;
            $lines[] = '- ვარიანტის მარაგი: ' . $variantStatus . ' (' . $variantQty . ' ცალი)';
        }

        $variantLines = $product->variants
            ->map(function (ProductVariant $variant): string {
                $qty = max(0, (int) $variant->quantity);
                $status = $qty > 0 ? 'მარაგშია' : 'ამოწურულია';

                return $variant->name . ' => ' . $status . ' (' . $qty . ' ცალი)';
            })
            ->values();

        if ($variantLines->isNotEmpty()) {
            $lines[] = '- ვარიანტები:';
            foreach ($variantLines as $variantLine) {
                $lines[] = '  - ' . $variantLine;
            }
        }

        return implode("\n", $lines);
    }

    private function matchVariantFromMessage(string $message, Product $product): ?ProductVariant
    {
        $haystack = Str::lower($message);
        $searchPool = collect([$haystack, ...$this->colorAliasExpansions($haystack)])->unique()->values();

        foreach ($product->variants as $variant) {
            $fullVariantName = Str::lower((string) $variant->name);
            if ($fullVariantName === '') {
                continue;
            }

            $matched = $searchPool->contains(
                fn (string $searchText): bool => Str::contains($searchText, $fullVariantName)
            );

            if ($matched) {
                return $variant;
            }
        }

        foreach ($product->variants as $variant) {
            $tokens = collect(preg_split('/[\s,:;\/\-]+/u', (string) $variant->name))
                ->filter(fn ($token) => is_string($token) && mb_strlen($token) >= 3)
                ->map(fn ($token) => Str::lower($token))
                ->reject(fn (string $token) => in_array($token, ['color', 'size'], true))
                ->values();

            if ($tokens->isEmpty()) {
                continue;
            }

            $matched = $tokens
                ->filter(function (string $token) use ($searchPool): bool {
                    return $searchPool->contains(
                        fn (string $searchText): bool => Str::contains($searchText, $token)
                    );
                })
                ->count();

            if ($matched >= 1) {
                return $variant;
            }
        }

        return null;
    }

    private function colorAliasExpansions(string $message): array
    {
        $aliasGroups = [
            ['blue', 'ლურჯი'],
            ['black', 'შავი'],
            ['white', 'თეთრი'],
            ['pink', 'ვარდისფერი'],
            ['green', 'მწვანე'],
            ['red', 'წითელი'],
            ['gold', 'ოქროსფერი'],
            ['silver', 'ვერცხლისფერი'],
            ['gray', 'grey', 'ნაცრისფერი'],
        ];

        $expansions = [];

        foreach ($aliasGroups as $group) {
            $present = false;
            foreach ($group as $alias) {
                if (Str::contains($message, $alias)) {
                    $present = true;
                    break;
                }
            }

            if (!$present) {
                continue;
            }

            foreach ($group as $alias) {
                $expansions[] = $message . ' ' . $alias;
            }
        }

        return $expansions;
}

    }
