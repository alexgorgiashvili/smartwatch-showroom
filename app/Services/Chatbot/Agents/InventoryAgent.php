<?php

namespace App\Services\Chatbot\Agents;

use App\Services\Chatbot\ConditionalReflectionService;
use App\Services\Chatbot\ChatbotFallbackStrategyService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\ModelCompletionService;
use App\Services\Chatbot\ProductContextService;
use App\Services\Chatbot\PromptBuilderService;
use App\Services\Chatbot\SearchContext;
use App\Services\Chatbot\WidgetTraceLogger;
use Illuminate\Support\Collection;

class InventoryAgent
{
    public function __construct(
        private ProductContextService $productContext,
        private PromptBuilderService $promptBuilder,
        private ModelCompletionService $modelCompletion,
        private ConditionalReflectionService $reflection,
        private ChatbotFallbackStrategyService $fallbackStrategy,
        private WidgetTraceLogger $widgetTrace
    ) {
    }

    /**
     * Handle inventory-related queries (price, stock)
     */
    public function handle(
        string $message,
        int $conversationId,
        IntentResult $intent,
        ?SearchContext $searchContext,
        Collection $products,
        array $sessionContext,
        array $preferences,
        array $trace = []
    ): array {
        $this->traceWidget('inventory_agent.started', [
            'intent' => $intent->intent(),
            'product_count' => $products->count(),
        ], $trace);

        $selectedProducts = $this->productContext->selectForPrompt($products, $intent, $preferences);

        $contactSettings = \App\Models\ContactSetting::allKeyed();
        $validationContext = $this->productContext->buildValidationContext($selectedProducts, $contactSettings);

        $systemPrompt = $this->promptBuilder->buildSystemPrompt($preferences, $intent);
        $modeInstruction = 'ინვენტარის რეჟიმი: უპასუხე ზუსტად ფასზე, მარაგზე და ხელმისაწვდომობაზე. არ მოიგონო ინფორმაცია, რომელიც კონტექსტში არ ჩანს.';
        $systemPrompt .= "\n\n" . $modeInstruction;

        $userContext = $this->promptBuilder->buildUserContext(
            $message,
            $intent,
            $searchContext ?? new SearchContext('', collect(), null, null),
            $contactSettings,
            $selectedProducts,
            $searchContext?->ragContext() ?? ''
        );

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($sessionContext['recent'] ?? [] as $historyEntry) {
            $role = $historyEntry['role'] ?? '';
            $content = trim($historyEntry['content'] ?? '');

            if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $userQuestion = trim($intent->standaloneQuery()) !== ''
            ? $intent->standaloneQuery()
            : $message;

        $messages[] = [
            'role' => 'user',
            'content' => $userContext . "\n\nUser question: " . $userQuestion,
        ];

        $this->traceWidget('inventory_agent.handoff_prepared', array_filter([
            'mode_instruction' => $modeInstruction,
            'history_count' => count($sessionContext['recent'] ?? []),
            'selected_products' => $this->productSnapshot($selectedProducts),
            'system_prompt' => $this->widgetTrace->payloadsEnabled() ? $systemPrompt : null,
            'user_context' => $this->widgetTrace->payloadsEnabled() ? $userContext : null,
        ], fn ($value) => $value !== null), $trace);

        $this->traceWidget('inventory_agent.model_request', [
            'model' => config('chatbot.supervisor.model', 'gpt-4.1-mini'),
            'message_count' => count($messages),
        ], $trace);

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_database',
                    'description' => 'Search products in database by keywords and criteria',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Search query/keywords'],
                        ],
                        'required' => ['query'],
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_pinecone',
                    'description' => 'Search related documentation and semantic product features in Pinecone',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Semantic search query'],
                        ],
                        'required' => ['query'],
                    ]
                ]
            ]
        ];

        $completion = $this->modelCompletion->complete(
            config('chatbot.supervisor.model', 'gpt-4.1-mini'),
            $messages,
            [
                'max_tokens' => 400,
                'temperature' => 0.5,
                'langfuse_name' => 'chatbot.inventory_agent',
                'langfuse_metadata' => [
                    'agent' => 'inventory',
                    'intent' => $intent->intent(),
                    'conversation_id' => $conversationId,
                ],
                'tools' => $tools,
            ]
        );

        if (!empty($completion['tool_calls'])) {
            $this->traceWidget('inventory_agent.tool_calls_received', [
                'tool_calls' => $completion['tool_calls']
            ], $trace);

            // Handle tool calls here - dummy loop to handle them later or now
            // In a real implementation you would call searchOrchestrator or Pinecone
            foreach ($completion['tool_calls'] as $toolCall) {
                // To do: Implement tool call execution
                $messages[] = [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [$toolCall]
                ];
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => '{"status": "success", "data": "Tool call executed"}'
                ];
            }

            // Fetch the model response after tools
            $completion = $this->modelCompletion->complete(
                config('chatbot.supervisor.model', 'gpt-4.1-mini'),
                $messages,
                [
                    'max_tokens' => 400,
                    'temperature' => 0.5,
                    'langfuse_name' => 'chatbot.inventory_agent_post_tools',
                    'langfuse_metadata' => [
                        'agent' => 'inventory',
                        'intent' => $intent->intent(),
                        'conversation_id' => $conversationId,
                    ],
                ]
            );
        }

        if ($completion['reason'] !== null) {
            $this->traceWidget('inventory_agent.model_failed', [
                'reason' => $completion['reason'],
            ], $trace);

            $fallback = $this->fallbackStrategy->resolveProviderFailureOutcome(
                $intent,
                $validationContext,
                $sessionContext['recent'] ?? [],
                $preferences
            );

            $this->traceWidget('inventory_agent.provider_fallback', array_filter([
                'reason' => $completion['reason'],
                'fallback_reply' => $this->widgetTrace->payloadsEnabled() ? $fallback->reply() : null,
            ], fn ($value) => $value !== null), $trace);

            return [
                'success' => true,
                'response' => $fallback->reply(),
                'reason' => $completion['reason'],
                'validation_passed' => $fallback->validationPassed(),
                'reflection_attempts' => 0,
                'violations' => $fallback->validationViolations(),
                'validation_context' => $validationContext,
            ];
        }

        $response = $completion['reply'];

        if ($this->shouldReplaceWeakCatalogReply($response, $message, $intent)) {
            $fallback = $this->fallbackStrategy->resolveProviderFailureOutcome(
                $intent,
                $validationContext,
                $sessionContext['recent'] ?? [],
                $preferences
            );

            return [
                'success' => true,
                'response' => $fallback->reply(),
                'validation_passed' => $fallback->validationPassed(),
                'reflection_attempts' => 0,
                'violations' => $fallback->validationViolations(),
                'validation_context' => $validationContext,
            ];
        }

        $this->traceWidget('inventory_agent.model_completed', array_filter([
            'model_reply' => $response,
            'usage' => $completion['usage'] ?? [],
        ], fn ($value) => $value !== null), $trace);

        $this->traceWidget('inventory_agent.reflection_check', [
            'should_reflect' => $this->reflection->shouldReflect($response, 1.0, $intent),
        ], $trace);

        if ($this->reflection->shouldReflect($response, 1.0, $intent)) {
            $reflectionResult = $this->reflection->reflect(
                $response,
                $validationContext,
                $intent,
                $messages
            );

            $this->traceWidget('inventory_agent.reflection_completed', [
                'success' => $reflectionResult['success'],
                'attempts' => $reflectionResult['attempts'],
                'violations' => $reflectionResult['violations'],
            ], $trace);

            return [
                'success' => $reflectionResult['success'],
                'response' => $reflectionResult['response'],
                'validation_passed' => $reflectionResult['success'],
                'reflection_attempts' => $reflectionResult['attempts'],
                'violations' => $reflectionResult['violations'],
                'validation_context' => $validationContext,
            ];
        }

        $this->traceWidget('inventory_agent.completed', [
            'response_length' => mb_strlen($response),
        ], $trace);

        return [
            'success' => true,
            'response' => $response,
            'validation_passed' => true,
            'reflection_attempts' => 0,
            'validation_context' => $validationContext,
        ];
    }

    private function traceWidget(string $step, array $context, array $trace): void
    {
        if (!$this->widgetTrace->enabled()) {
            return;
        }

        $this->widgetTrace->logStep($step, array_merge($trace, $context));
    }

    private function productSnapshot(Collection $products): array
    {
        return $products
            ->take(5)
            ->map(static function ($product): array {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $product->sale_price ?: $product->price,
                    'stock' => (int) ($product->total_stock ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function shouldReplaceWeakCatalogReply(string $response, string $message, IntentResult $intent): bool
    {
        if (!in_array($intent->intent(), ['stock_query', 'price_query', 'recommendation', 'general'], true)) {
            return false;
        }

        $normalizedResponse = mb_strtolower($response);
        $normalizedMessage = mb_strtolower($message);

        $denialPhrases = [
            'არ გვაქვს',
            'არ შეიცავს',
            'კატალოგში არ არის',
            'ახალი მოდელები ჩვენს კატალოგში არ არის',
            'ვერ მოვიძიე',
            'ვერ შემოგთავაზებთ',
        ];

        $productSeekingSignals = [
            'ახალი',
            'მოდელი',
            'მოდელები',
            'რა გაქვთ',
            'ბიუჯეტ',
            'gps',
            '2g',
            '4g',
        ];

        $hasDenial = collect($denialPhrases)->contains(
            fn (string $phrase): bool => str_contains($normalizedResponse, $phrase)
        );

        if (!$hasDenial) {
            return false;
        }

        return collect($productSeekingSignals)->contains(
            fn (string $signal): bool => str_contains($normalizedMessage, $signal)
        );
    }
}
