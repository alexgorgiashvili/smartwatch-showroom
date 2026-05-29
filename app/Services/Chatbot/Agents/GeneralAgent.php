<?php

namespace App\Services\Chatbot\Agents;

use App\Services\Chatbot\ConditionalReflectionService;
use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\ModelCompletionService;
use App\Services\Chatbot\ProductContextService;
use App\Services\Chatbot\PromptBuilderService;
use App\Services\Chatbot\SearchContext;
use App\Services\Chatbot\WidgetTraceLogger;
use Illuminate\Support\Collection;

class GeneralAgent
{
    public function __construct(
        private ProductContextService $productContext,
        private PromptBuilderService $promptBuilder,
        private ModelCompletionService $modelCompletion,
        private ConditionalReflectionService $reflection,
        private WidgetTraceLogger $widgetTrace
    ) {
    }

    /**
     * Handle general queries and recommendations
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
        $this->traceWidget('general_agent.started', [
            'intent' => $intent->intent(),
            'product_count' => $products->count(),
        ], $trace);

        $selectedProducts = $this->productContext->selectForPrompt($products, $intent, $preferences);

        $contactSettings = \App\Models\ContactSetting::allKeyed();
        $validationContext = $this->productContext->buildValidationContext($selectedProducts, $contactSettings);

        $systemPrompt = $this->promptBuilder->buildSystemPrompt($preferences, $intent);
        $modeInstruction = 'ზოგადი რეჟიმი: მიეცი მომხმარებელს სასარგებლო, ბუნებრივი პასუხები. ფოკუსირდი მისი საჭიროებების გაგებაზე და პერსონალიზებულ რეკომენდაციებზე.';
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

        if ($sessionContext['summary'] ?? null) {
            $messages[] = [
                'role' => 'system',
                'content' => 'Previous conversation summary: ' . $sessionContext['summary'],
            ];
        }

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

        $this->traceWidget('general_agent.handoff_prepared', array_filter([
            'mode_instruction' => $modeInstruction,
            'history_count' => count($sessionContext['recent'] ?? []),
            'selected_products' => $this->productSnapshot($selectedProducts),
            'system_prompt' => $this->widgetTrace->payloadsEnabled() ? $systemPrompt : null,
            'user_context' => $this->widgetTrace->payloadsEnabled() ? $userContext : null,
        ], fn ($value) => $value !== null), $trace);

        $this->traceWidget('general_agent.model_request', [
            'model' => config('chatbot.supervisor.model', 'gpt-4.1-mini'),
            'message_count' => count($messages),
            'has_summary' => isset($sessionContext['summary']),
        ], $trace);

        $completion = $this->modelCompletion->complete(
            config('chatbot.supervisor.model', 'gpt-4.1-mini'),
            $messages,
            [
                'max_tokens' => 300,
                'temperature' => 0.7,
                'langfuse_name' => 'chatbot.general_agent',
                'langfuse_metadata' => [
                    'agent' => 'general',
                    'intent' => $intent->intent(),
                    'conversation_id' => $conversationId,
                ],
            ]
        );

        if ($completion['reason'] !== null) {
            $this->traceWidget('general_agent.model_failed', [
                'reason' => $completion['reason'],
            ], $trace);

            return [
                'success' => false,
                'response' => '',
                'reason' => $completion['reason'],
                'validation_passed' => false,
            ];
        }

        $response = $completion['reply'];

        $this->traceWidget('general_agent.model_completed', array_filter([
            'model_reply' => $response,
            'usage' => $completion['usage'] ?? [],
        ], fn ($value) => $value !== null), $trace);

        $shouldReflect = $this->reflection->shouldReflect($response, 0.8, $intent);

        $this->traceWidget('general_agent.reflection_check', [
            'should_reflect' => $shouldReflect,
        ], $trace);

        if ($shouldReflect) {
            $reflectionResult = $this->reflection->reflect(
                $response,
                $validationContext,
                $intent,
                $messages
            );

            $this->traceWidget('general_agent.reflection_completed', [
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
            ];
        }

        $this->traceWidget('general_agent.completed', [
            'response_length' => mb_strlen($response),
        ], $trace);

        return [
            'success' => true,
            'response' => $response,
            'validation_passed' => true,
            'reflection_attempts' => 0,
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
}
