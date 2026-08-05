<?php

namespace App\Http\Controllers;

use App\Events\MessageReceived;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ProductVariant;
use App\Services\Chatbot\ChatbotOutcomeReason;
use App\Services\Chatbot\ChatPipelineService;
use App\Services\Chatbot\Agents\SupervisorAgent;
use App\Services\Chatbot\ChatbotProductSelectionService;
use App\Services\Chatbot\IntentAnalyzerService;
use App\Services\Chatbot\BifurcatedMemoryService;
use App\Services\Chatbot\ChatbotFallbackStrategyService;
use App\Services\Chatbot\ChatbotQualityMetricsService;
use App\Services\Chatbot\InputGuardService;
use App\Services\Chatbot\LangfuseService;
use App\Services\Chatbot\PipelineResult;
use App\Services\Chatbot\UnifiedAiPolicyService;
use App\Services\Chatbot\WidgetTraceLogger;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Get or create a session-based customer for the widget
     */
    private function getWidgetCustomer(Request $request): Customer
    {
        $sessionId = session()->getId();
        $identifier = 'widget_' . $sessionId;

        return Customer::firstOrCreate(
            ['platform_user_ids->home' => $identifier],
            [
                'name' => 'Widget User (' . substr($identifier, 0, 8) . ')',
                'platform_user_ids' => ['home' => $identifier],
                'avatar_url' => null,
                'metadata' => [
                    'widget_session_id' => $sessionId,
                    'first_interaction' => now()->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Get or create conversation for widget session
     */
    private function getWidgetConversation(Customer $customer): Conversation
    {
        $conversation = $this->findCurrentWidgetConversation($customer);

        if (!$conversation) {
            $conversation = Conversation::create([
                'customer_id' => $customer->id,
                'platform' => 'home',
                'platform_conversation_id' => 'widget_' . Str::uuid(),
                'subject' => 'Widget Chat',
                'status' => 'active',
                'unread_count' => 0,
                'last_message_at' => now(),
                'metadata' => [
                    'widget_version' => $this->currentWidgetConversationVersion(),
                ],
            ]);
        }

        return $conversation;
    }

    private function findCurrentWidgetConversation(Customer $customer): ?Conversation
    {
        $currentVersion = $this->currentWidgetConversationVersion();

        $conversation = $customer->conversations()
            ->where('platform', 'home')
            ->where('status', 'active')
            ->latest('last_message_at')
            ->first();

        if ($conversation && data_get($conversation->metadata, 'widget_version') === $currentVersion) {
            return $conversation;
        }

        return $customer->conversations()
            ->where('platform', 'home')
            ->where('status', 'active')
            ->get()
            ->first(fn (Conversation $item): bool => data_get($item->metadata, 'widget_version') === $currentVersion);
    }

    private function currentWidgetConversationVersion(): string
    {
        return 'chatbot-mvp-2026-07-05-' . app()->getLocale();
    }

    public function respond(
        Request $request,
        InputGuardService $inputGuard,
        SupervisorAgent $supervisor,
        IntentAnalyzerService $intentAnalyzer,
        BifurcatedMemoryService $memory,
        ChatPipelineService $chatPipeline,
        ChatbotProductSelectionService $productSelection,
        WidgetTraceLogger $widgetTrace,
        LangfuseService $langfuse,
        ChatbotQualityMetricsService $qualityMetrics,
        ChatbotFallbackStrategyService $fallbackStrategy,
        UnifiedAiPolicyService $policy
    ) {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $traceId = $widgetTrace->enabled() ? $widgetTrace->newTraceId() : null;
        $incomingMessage = (string) $request->input('message');
        $guardResult = $inputGuard->inspect($incomingMessage);
        $safeIncomingMessage = $guardResult->sanitizedInput();

        $widgetTrace->logStep('widget.respond.request_received', array_filter([
            'trace_id' => $traceId,
            'session_id' => substr(session()->getId(), 0, 16),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'incoming_message' => $incomingMessage,
            'next_step' => 'sanitize_input_and_resolve_customer',
        ], fn ($value) => $value !== null));

        if ($safeIncomingMessage === '') {
            $safeIncomingMessage = trim($incomingMessage);
        }

        $customer = $this->getWidgetCustomer($request);
        $conversation = $this->getWidgetConversation($customer);

        $widgetTrace->logStep('widget.respond.customer_resolved', array_filter([
            'trace_id' => $traceId,
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'sanitized_message' => $safeIncomingMessage,
            'next_step' => 'persist_customer_message',
        ], fn ($value) => $value !== null));

        $langfuse->startTrace(
            'chatbot.widget.response',
            ['message' => $safeIncomingMessage],
            [
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'channel' => 'widget',
            ],
            (string) $customer->id,
            (string) $conversation->id,
            $traceId,
            ['chatbot', 'widget']
        );

        $customerMessage = DB::transaction(function () use ($conversation, $customer, $safeIncomingMessage) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'sender_type' => 'customer',
                'sender_id' => $customer->id,
                'sender_name' => $customer->name,
                'content' => $safeIncomingMessage,
                'platform_message_id' => 'home_' . Str::uuid(),
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'unread_count' => $conversation->unread_count + 1,
            ]);

            return $message;
        });

        $widgetTrace->logStep('widget.respond.customer_message_persisted', array_filter([
            'trace_id' => $traceId,
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'message_id' => $customerMessage->id,
            'content' => $customerMessage->content,
            'next_step' => 'broadcast_and_notify_admins',
        ], fn ($value) => $value !== null));

        event(new MessageReceived(
            $customerMessage,
            $conversation,
            $customer,
            'home'
        ));

        $widgetTrace->logStep('widget.respond.customer_message_broadcasted', array_filter([
            'trace_id' => $traceId,
            'conversation_id' => $conversation->id,
            'message_id' => $customerMessage->id,
            'next_step' => 'send_admin_push_notification',
        ], fn ($value) => $value !== null));

        app(PushNotificationService::class)->sendToAdmins(
            'New message from ' . ($customer->name ?: 'Customer'),
            mb_substr((string) $customerMessage->content, 0, 120),
            url('/admin/inbox?conversation=' . $conversation->id),
            [
                'conversation_id' => $conversation->id,
                'message_id' => $customerMessage->id,
                'platform' => 'home',
            ]
        );

        $widgetTrace->logStep('widget.respond.admin_notification_sent', array_filter([
            'trace_id' => $traceId,
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'next_step' => 'run_chat_pipeline',
        ], fn ($value) => $value !== null));

        if (!$guardResult->allowed()) {
            $blockedReply = $guardResult->safeReply() ?? __('storefront.chatbot.blocked');
            if (! $policy->passesLocaleQa($blockedReply)) {
                $blockedReply = __('storefront.chatbot.blocked');
            }

            $widgetTrace->logStep('widget.respond.guard_blocked', array_filter([
                'trace_id' => $traceId,
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'guard_reason' => $guardResult->reason(),
            ], fn ($value) => $value !== null));

            $botMessage = DB::transaction(function () use ($conversation, $customer, $blockedReply, $guardResult): Message {
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'customer_id' => $customer->id,
                    'sender_type' => 'bot',
                    'sender_id' => 0,
                    'sender_name' => 'MyTechnic Assistant',
                    'content' => $blockedReply,
                    'platform_message_id' => 'home_' . Str::uuid(),
                    'metadata' => [
                        'chatbot_failure' => false,
                        'fallback_reason' => ChatbotOutcomeReason::INPUT_GUARD,
                        'guard_blocked' => true,
                        'guard_reason' => $guardResult->reason(),
                        'locale' => app()->getLocale(),
                    ],
                ]);
                $conversation->update(['last_message_at' => now()]);
                return $message;
            });

            event(new MessageReceived($botMessage, $conversation->fresh('customer'), $customer, 'home'));

            $qualityMetrics->recordWidgetResponseQuality(
                $conversation->id,
                $customer->id,
                fallbackUsed: true,
                nonGeorgianModelOutput: false,
                fallbackReason: ChatbotOutcomeReason::INPUT_GUARD,
            );

            $guardResponseData = [
                'conversation_id' => $conversation->id,
                'message' => $blockedReply,
            ];

            if ($this->shouldExposeWidgetDebug()) {
                $guardResponseData['debug'] = [
                    'intent' => null,
                    'intent_confidence' => null,
                    'intent_fallback' => false,
                    'validation_passed' => true,
                    'validation_violations' => [],
                    'georgian_passed' => true,
                    'fallback_reason' => ChatbotOutcomeReason::INPUT_GUARD,
                    'regeneration_attempted' => false,
                    'regeneration_succeeded' => false,
                    'products_found' => 0,
                    'products_attached' => 0,
                    'carousel_suppressed' => false,
                    'trace_id' => $traceId,
                ];
            }

            $langfuse->updateTrace([
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'fallback_reason' => ChatbotOutcomeReason::INPUT_GUARD,
                'guard_reason' => $guardResult->reason(),
            ], $blockedReply);
            $langfuse->clearContext();

            return response()->json($guardResponseData);
        }

        $responseData = $this->buildWidgetResponse(
            safeIncomingMessage: $safeIncomingMessage,
            conversation: $conversation,
            customer: $customer,
            traceId: $traceId,
            widgetTrace: $widgetTrace,
            memory: $memory,
            chatPipeline: $chatPipeline,
            intentAnalyzer: $intentAnalyzer,
            supervisor: $supervisor,
            policy: $policy,
            langfuse: $langfuse,
            fallbackStrategy: $fallbackStrategy,
            qualityMetrics: $qualityMetrics,
            productSelection: $productSelection
        );

        if ($request->expectsJson()) {
            return response()->json($responseData, 200, [
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return response()->stream(function () use ($responseData): void {
            echo 'data: ' . json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

            if (function_exists('ob_flush')) {
                @ob_flush();
            }

            flush();
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWidgetResponse(
        string $safeIncomingMessage,
        Conversation $conversation,
        Customer $customer,
        ?string $traceId,
        WidgetTraceLogger $widgetTrace,
        BifurcatedMemoryService $memory,
        ChatPipelineService $chatPipeline,
        IntentAnalyzerService $intentAnalyzer,
        SupervisorAgent $supervisor,
        UnifiedAiPolicyService $policy,
        LangfuseService $langfuse,
        ChatbotFallbackStrategyService $fallbackStrategy,
        ChatbotQualityMetricsService $qualityMetrics,
        ChatbotProductSelectionService $productSelection
    ): array {
        $responseData = [
            'conversation_id' => $conversation->id,
        ];

            $pipelineResult = null;
            try {
                $widgetTrace->logStep('widget.respond.pipeline_handoff', array_filter([
                    'trace_id' => $traceId,
                    'conversation_id' => $conversation->id,
                    'customer_id' => $customer->id,
                    'message_for_pipeline' => $safeIncomingMessage,
                    'next_step' => 'intent_analysis_and_supervisor',
                ], fn ($value) => $value !== null));

                $pipelineResult = $chatPipeline->process(
                    $safeIncomingMessage,
                    $conversation,
                    $customer,
                    $traceId,
                    $memory,
                    $intentAnalyzer,
                    $supervisor,
                    $policy,
                    $fallbackStrategy
                );

                $pipelineResponse = $this->removeUnsupportedBudgetClaim(
                    $pipelineResult->response(),
                    $safeIncomingMessage,
                    $customer
                );
                $pipelineFallbackReason = $pipelineResult->fallbackReason();
                $pipelineValidationPassed = $pipelineResult->validationPassed();
                $pipelineValidationViolations = $pipelineResult->validationViolations();
                $pipelineRegenerationAttempted = $pipelineResult->regenerationAttempted();
                $pipelineRegenerationSucceeded = $pipelineResult->regenerationSucceeded();
                $pipelineGeorgianPassed = $pipelineResult->georgianPassed();

                $widgetTrace->logStep('widget.respond.supervisor_completed', array_filter([
                    'trace_id' => $traceId,
                    'conversation_id' => $conversation->id,
                    'customer_id' => $customer->id,
                    'supervisor_reply' => $pipelineResponse,
                    'fallback_reason' => $pipelineFallbackReason,
                    'validation_passed' => $pipelineValidationPassed,
                    'validation_violations' => $pipelineValidationViolations,
                    'reflection_attempts' => $pipelineRegenerationAttempted ? 1 : 0,
                    'next_step' => 'persist_bot_message',
                ], fn ($value) => $value !== null));

            $botMessage = DB::transaction(function () use ($conversation, $customer, $pipelineResult, $pipelineResponse, $pipelineFallbackReason, $pipelineRegenerationAttempted, $pipelineRegenerationSucceeded): Message {
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'customer_id' => $customer->id,
                    'sender_type' => 'bot',
                    'sender_id' => 0,
                    'sender_name' => 'MyTechnic Assistant',
                    'content' => $pipelineResponse,
                    'platform_message_id' => 'home_' . Str::uuid(),
                    'metadata' => [
                        'chatbot_failure' => false,
                        'fallback_reason' => $pipelineFallbackReason,
                        'regeneration_attempted' => $pipelineRegenerationAttempted,
                        'regeneration_succeeded' => $pipelineRegenerationSucceeded,
                        'locale' => app()->getLocale(),
                    ],
                ]);

                $conversation->update([
                    'last_message_at' => now(),
                ]);
                return $message;
            });

            event(new MessageReceived($botMessage, $conversation->fresh('customer'), $customer, 'home'));

            $widgetTrace->logStep('widget.respond.bot_message_persisted', array_filter([
                'trace_id' => $traceId,
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'bot_reply' => $pipelineResponse,
                'next_step' => 'optionally_attach_product_cards',
            ], fn ($value) => $value !== null));

            $responseData['message'] = $pipelineResponse;

            $qualityMetrics->recordWidgetResponseQuality(
                $conversation->id,
                $customer->id,
                fallbackUsed: $pipelineFallbackReason !== null,
                nonGeorgianModelOutput: !$pipelineGeorgianPassed,
                fallbackReason: $pipelineFallbackReason,
                regenerationAttempted: $pipelineRegenerationAttempted,
                regenerationSucceeded: $pipelineRegenerationSucceeded,
            );
        } catch (\Throwable $exception) {
            $widgetTrace->logStep('widget.respond.pipeline_failed', array_filter([
                'trace_id' => $traceId,
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'error' => $exception->getMessage(),
                'exception_class' => $exception::class,
                'next_step' => 'persist_runtime_fallback',
            ], fn ($value) => $value !== null));

            Log::error('Widget chatbot pipeline failed', [
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'error' => $exception->getMessage(),
            ]);

            $failureMessage = __('storefront.chatbot.failure');

            $botMessage = DB::transaction(function () use ($conversation, $customer, $failureMessage, $exception): Message {
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'customer_id' => $customer->id,
                    'sender_type' => 'bot',
                    'sender_id' => 0,
                    'sender_name' => 'MyTechnic Assistant',
                    'content' => $failureMessage,
                    'platform_message_id' => 'home_' . Str::uuid(),
                    'metadata' => [
                        'chatbot_failure' => true,
                        'fallback_reason' => ChatbotOutcomeReason::RUNTIME_EXCEPTION,
                        'exception_class' => $exception::class,
                        'exception_message' => $exception->getMessage(),
                        'locale' => app()->getLocale(),
                    ],
                ]);

                $conversation->update([
                    'last_message_at' => now(),
                ]);
                return $message;
            });

            event(new MessageReceived($botMessage, $conversation->fresh('customer'), $customer, 'home'));

            $responseData['message'] = $failureMessage;

            $qualityMetrics->recordWidgetResponseQuality(
                $conversation->id,
                $customer->id,
                fallbackUsed: true,
                nonGeorgianModelOutput: false,
                fallbackReason: ChatbotOutcomeReason::RUNTIME_EXCEPTION,
            );

            $langfuse->updateTrace([
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'fallback_reason' => ChatbotOutcomeReason::RUNTIME_EXCEPTION,
                'error' => $exception->getMessage(),
            ], $failureMessage);
        }

        // Attach product cards for carousel if RAG found products
        $validationContext = $pipelineResult?->validationContext() ?? [];
        $products = $validationContext['products'] ?? [];
        if ($pipelineResult !== null && $productSelection->shouldIncludeWidgetProducts($pipelineResult)) {
            $selectedProducts = $productSelection->selectWidgetProductsForResponse(
                collect($products)
                    ->filter(fn (array $p) => ($p['is_in_stock'] ?? false))
                    ->take(6)
                    ->values()
                    ->all(),
                $pipelineResult
            );

            $selectedProducts = $this->filterWidgetProductsByResponse(
                $selectedProducts,
                $pipelineResult->response()
            );

            if ($selectedProducts === [] && $pipelineResult->fallbackReason() === null && $pipelineResult->validationPassed()) {
                $selectedProducts = $this->lookupMentionedWidgetProductsFromCatalog($pipelineResult->response());
            }

            if ($selectedProducts !== []) {
                $responseData['products'] = $this->formatWidgetProducts($selectedProducts);

                if (isset($botMessage)) {
                    $botMessage->update([
                        'metadata' => array_merge($botMessage->metadata ?? [], [
                            'widget_products' => $responseData['products'],
                        ]),
                    ]);
                }

                $widgetTrace->logStep('widget.respond.products_attached', array_filter([
                    'trace_id' => $traceId,
                    'conversation_id' => $conversation->id,
                    'customer_id' => $customer->id,
                    'attached_products' => $responseData['products'],
                    'next_step' => 'return_widget_response',
                ], fn ($value) => $value !== null));
            }
        }

        if ($this->shouldExposeWidgetDebug()) {
            $responseData['debug'] = $pipelineResult
                ? [
                    'intent' => $pipelineResult->intentResult()?->intent(),
                    'intent_confidence' => $pipelineResult->intentResult()?->confidence(),
                    'intent_fallback' => $pipelineResult->intentResult()?->isFallback() ?? false,
                    'validation_passed' => $pipelineResult->validationPassed(),
                    'validation_violations' => $pipelineResult->validationViolations(),
                    'georgian_passed' => $pipelineResult->georgianPassed(),
                    'fallback_reason' => $pipelineResult->fallbackReason(),
                    'regeneration_attempted' => $pipelineResult->regenerationAttempted(),
                    'regeneration_succeeded' => $pipelineResult->regenerationSucceeded(),
                    'products_found' => count($products),
                    'products_attached' => isset($responseData['products']) ? count($responseData['products']) : 0,
                    'carousel_suppressed' => $products !== [] && !isset($responseData['products']),
                    'trace_id' => $traceId,
                ]
                : [
                    'intent' => null,
                    'intent_confidence' => null,
                    'intent_fallback' => false,
                    'validation_passed' => false,
                    'validation_violations' => [],
                    'georgian_passed' => true,
                    'fallback_reason' => ChatbotOutcomeReason::RUNTIME_EXCEPTION,
                    'regeneration_attempted' => false,
                    'regeneration_succeeded' => false,
                    'products_found' => 0,
                    'products_attached' => 0,
                    'carousel_suppressed' => false,
                    'trace_id' => $traceId,
                ];
        }

        $widgetTrace->logStep('widget.respond.response_sent', array_filter([
            'trace_id' => $traceId,
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'response_message' => $responseData['message'] ?? null,
            'response_products_count' => isset($responseData['products']) ? count($responseData['products']) : 0,
            'next_step' => 'widget_receives_payload',
        ], fn ($value) => $value !== null));

        $langfuse->updateTrace([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'intent' => $pipelineResult?->intentResult()?->intent(),
            'intent_confidence' => $pipelineResult?->intentResult()?->confidence(),
            'fallback_reason' => $pipelineResult?->fallbackReason(),
            'validation_passed' => $pipelineResult?->validationPassed(),
            'regeneration_attempted' => $pipelineResult?->regenerationAttempted(),
            'regeneration_succeeded' => $pipelineResult?->regenerationSucceeded(),
            'products_attached' => isset($responseData['products']) ? count($responseData['products']) : 0,
        ], $responseData['message'] ?? null);
        $langfuse->clearContext();

        return $responseData;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    private function filterWidgetProductsByResponse(array $products, string $response): array
    {
        if ($products === []) {
            return [];
        }

        $mentions = $this->extractWidgetResponseMentions($response);

        if ($mentions === []) {
            return $products;
        }

        $filtered = array_values(array_filter(
            $products,
            fn (array $product): bool => $this->responseMentionsWidgetProduct($response, $mentions, $product)
        ));

        return $filtered !== [] ? $filtered : $products;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array{name: string, price: string, url: string, image: string}>
     */
    private function formatWidgetProducts(array $products): array
    {
        return collect($products)
            ->map(fn (array $product) => [
                'name' => (string) ($product['name'] ?? ''),
                'price' => isset($product['sale_price']) && $product['sale_price']
                    ? $product['sale_price'] . ' ₾'
                    : (isset($product['price']) ? $product['price'] . ' ₾' : ''),
                'url' => (string) ($product['url'] ?? ''),
                'image' => (string) ($product['image'] ?? ''),
            ])
            ->filter(fn (array $product) => $product['name'] !== '' && $product['url'] !== '')
            ->values()
            ->all();
    }

    private function removeUnsupportedBudgetClaim(
        string $response,
        string $incomingMessage,
        Customer $customer
    ): string {
        $preferences = is_array($customer->preferences) ? $customer->preferences : [];
        $hasSavedBudget = isset($preferences['budget_max_gel'])
            && is_numeric($preferences['budget_max_gel']);
        $mentionsBudget = preg_match(
            '/(?:ბიუჯეტ|ფარგლებ|ლარამდე|₾|gel|budget|under|up to)/iu',
            $incomingMessage
        ) === 1;

        if ($hasSavedBudget || $mentionsBudget) {
            return $response;
        }

        return preg_replace(
            '/თქვენი\s+ბიუჯეტის\s+და\s+მოთხოვნის\s+გათვალისწინებით\s*,?/iu',
            'თქვენი მოთხოვნის მიხედვით,',
            $response
        ) ?? $response;
    }

    /**
     * @return array<int, string>
     */
    private function extractWidgetResponseMentions(string $response): array
    {
        $mentions = [];

        if (preg_match_all('/\*\*(.+?)\*\*/u', $response, $boldMatches)) {
            foreach (($boldMatches[1] ?? []) as $match) {
                $mentions[] = $this->normalizeWidgetProductText((string) $match);
            }
        }

        if (preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/u', $response, $linkMatches, PREG_SET_ORDER)) {
            foreach ($linkMatches as $match) {
                $mentions[] = $this->normalizeWidgetProductText((string) ($match[1] ?? ''));
                $mentions[] = $this->normalizeWidgetProductText($this->widgetProductSlugFromUrl((string) ($match[2] ?? '')));
            }
        }

        return array_values(array_filter(array_unique($mentions)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lookupMentionedWidgetProductsFromCatalog(string $response, int $limit = 3): array
    {
        if (trim($response) === '') {
            return [];
        }

        return Product::query()
            ->active()
            ->with(['primaryImage', 'variants'])
            ->withSum('variants as total_stock', 'quantity')
            ->orderByDesc('featured')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->filter(fn (Product $product): bool => $this->messageMentionsProduct($response, $product))
            ->take($limit)
            ->map(function (Product $product): array {
                $price = $product->sale_price
                    ? $product->sale_price . ' ₾'
                    : (is_numeric($product->price) ? $product->price . ' ₾' : '');

                return [
                    'name' => $product->name,
                    'price' => $price,
                    'url' => url('/products/' . $product->slug),
                    'image' => $product->primaryImage?->thumbnail_url ?: '',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $mentions
     * @param array<string, mixed> $product
     */
    private function responseMentionsWidgetProduct(string $response, array $mentions, array $product): bool
    {
        $url = trim((string) ($product['url'] ?? ''));
        if ($url !== '' && str_contains($response, $url)) {
            return true;
        }

        $aliases = array_values(array_filter(array_unique([
            $this->normalizeWidgetProductText((string) ($product['name'] ?? '')),
            $this->normalizeWidgetProductText((string) ($product['slug'] ?? '')),
            $this->normalizeWidgetProductText(str_replace('-', ' ', (string) ($product['slug'] ?? ''))),
            ...$this->widgetProductModelAliases((string) ($product['name'] ?? '')),
            ...$this->widgetProductModelAliases(str_replace('-', ' ', (string) ($product['slug'] ?? ''))),
        ])));

        foreach ($aliases as $alias) {
            foreach ($mentions as $mention) {
                if ($mention === $alias || $this->widgetProductTextContains($mention, $alias)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function widgetProductModelAliases(string $source): array
    {
        $tokens = collect(preg_split('/[^\p{L}\p{N}]+/u', $source) ?: [])
            ->filter(fn ($token): bool => is_string($token) && trim($token) !== '')
            ->map(fn ($token): string => trim((string) $token))
            ->values();

        $aliases = [];

        foreach ($tokens as $index => $token) {
            $normalizedToken = $this->normalizeWidgetProductText($token);

            if (preg_match('/(?=.*\d)(?=.*\p{L})/u', $normalizedToken) !== 1 || !$this->widgetAliasTokenIsDistinctive($normalizedToken)) {
                continue;
            }

            $aliases[] = $normalizedToken;

            $previousToken = $tokens->get($index - 1);
            if (is_string($previousToken) && $this->widgetBrandLikeToken($previousToken)) {
                $aliases[] = $this->normalizeWidgetProductText($previousToken . ' ' . $token);
            }
        }

        return array_values(array_filter(array_unique($aliases)));
    }

    private function widgetAliasTokenIsDistinctive(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        if (in_array($token, [
            'smart', 'watch', 'kids', 'kid', 'child', 'children', 'gps', 'sos', 'call', 'video', 'network',
            'waterproof', 'android', 'tracker', 'anti', 'lost', 'camera', 'phone', 'color', 'sim', '4g', '2g',
            '3g', '5g', 'wifi', 'lbs', 'rtos', 'oem', 'newest', 'style',
        ], true)) {
            return false;
        }

        return mb_strlen($token) >= 3 && preg_match('/(?=.*\d)(?=.*\p{L})/u', $token) === 1;
    }

    private function widgetBrandLikeToken(string $token): bool
    {
        $normalized = $this->normalizeWidgetProductText($token);

        return $normalized !== '' && preg_match('/\d/', $normalized) !== 1 && mb_strlen($normalized) >= 4;
    }

    private function normalizeWidgetProductText(string $value): string
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value));

        return trim((string) $normalized);
    }

    private function widgetProductTextContains(string $haystack, string $needle): bool
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        return str_contains(' ' . $haystack . ' ', ' ' . $needle . ' ');
    }

    private function widgetProductSlugFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);

        return (string) end($segments);
    }

    private function shouldExposeWidgetDebug(): bool
    {
        return (bool) config('app.debug') || app()->environment(['local', 'testing']);
    }

    /**
     * Return recent conversation history for the widget session.
     */
    public function history(Request $request, WidgetTraceLogger $widgetTrace): JsonResponse
    {
        $traceId = $widgetTrace->enabled() ? $widgetTrace->newTraceId() : null;

        $widgetTrace->logStep('widget.history.request_received', array_filter([
            'trace_id' => $traceId,
            'session_id' => substr(session()->getId(), 0, 16),
            'ip' => $request->ip(),
            'next_step' => 'resolve_existing_widget_conversation',
        ], fn ($value) => $value !== null));

        $customer = $this->findWidgetCustomer($request);
        if (!$customer) {
            $widgetTrace->logStep('widget.history.empty', array_filter([
                'trace_id' => $traceId,
                'reason' => 'customer_not_found',
            ], fn ($value) => $value !== null));

            return response()->json(['messages' => [], 'conversation_id' => null]);
        }

        $conversation = $this->findCurrentWidgetConversation($customer);

        if (!$conversation) {
            $widgetTrace->logStep('widget.history.empty', array_filter([
                'trace_id' => $traceId,
                'customer_id' => $customer->id,
                'reason' => 'conversation_not_found_or_stale',
            ], fn ($value) => $value !== null));

            return response()->json(['messages' => [], 'conversation_id' => null]);
        }

        $messages = $conversation->messages()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(20)
            ->get()
            ->reverse()
            ->values()
            ->map(function (Message $message) use ($customer): array {
                $products = data_get($message->metadata, 'widget_products', []);

                if (!is_array($products)) {
                    $products = [];
                }

                if ($message->sender_type === 'bot' && $products === []) {
                    $products = $this->formatWidgetProducts(
                        $this->lookupMentionedWidgetProductsFromCatalog($message->content)
                    );
                }

                return [
                    'id' => $message->id,
                    'content' => $message->sender_type === 'bot'
                        ? $this->removeUnsupportedBudgetClaim($message->content, '', $customer)
                        : $message->content,
                    'sender_type' => $message->sender_type,
                    'created_at' => $message->created_at->toIso8601String(),
                    'products' => $products,
                ];
            });

        $widgetTrace->logStep('widget.history.response_sent', array_filter([
            'trace_id' => $traceId,
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'message_count' => $messages->count(),
            'messages' => $messages->values()->all(),
        ], fn ($value) => $value !== null));

        return response()->json([
            'messages' => $messages,
            'conversation_id' => $conversation->id,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Find an existing widget customer (does not create one).
     */
    private function findWidgetCustomer(Request $request): ?Customer
    {
        $sessionId = session()->getId();
        $identifier = 'widget_' . $sessionId;

        return Customer::where('platform_user_ids->home', $identifier)->first();
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
                    'image' => $product->primaryImage?->thumbnail_url ?: '',
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

        if (!empty($contactSettings['messenger_url'])) {
            $allowedUrls[] = rtrim((string) $contactSettings['messenger_url'], '/');
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

    private function messageMentionsProduct(string $message, Product $product): bool
    {
        $haystack = Str::lower($message);

        $aliases = array_values(array_filter(array_unique([
            Str::lower((string) $product->name),
            Str::lower((string) $product->name_en),
            Str::lower((string) $product->name_ka),
            Str::lower((string) $product->slug),
            Str::replace('-', ' ', Str::lower((string) $product->slug)),
            ...$this->widgetProductModelAliases((string) $product->name),
            ...$this->widgetProductModelAliases(str_replace('-', ' ', (string) $product->slug)),
        ])));

        foreach ($aliases as $alias) {
            if ($alias !== '' && Str::contains($haystack, $alias)) {
                return true;
            }
        }

        return false;
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

