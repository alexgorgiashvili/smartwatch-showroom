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

class ComparisonAgent
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
     * Handle product comparison queries
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
        $this->traceWidget('comparison_agent.started', [
            'intent' => $intent->intent(),
            'product_count' => $products->count(),
        ], $trace);

        $selectedProducts = $this->productContext->selectForPrompt($products, $intent, $preferences);

        $contactSettings = \App\Models\ContactSetting::allKeyed();
        $validationContext = $this->productContext->buildValidationContext($selectedProducts, $contactSettings);

        $systemPrompt = $this->promptBuilder->buildSystemPrompt($preferences, $intent);
        $systemPrompt .= "\n\nCOMPARISON MODE: Provide a detailed comparison of the products. Highlight key differences in features, prices, and suitability for different use cases.";

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

        $this->traceWidget('comparison_agent.model_request', [
            'model' => config('chatbot.supervisor.model', 'gpt-4o-mini'),
            'message_count' => count($messages),
            'product_count' => $selectedProducts->count(),
        ], $trace);

        $completion = $this->modelCompletion->complete(
            config('chatbot.supervisor.model', 'gpt-4o-mini'),
            $messages,
            [
                'max_tokens' => 350,
                'temperature' => 0.7,
            ]
        );

        if ($completion['reason'] !== null) {
            $this->traceWidget('comparison_agent.model_failed', [
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

        $this->traceWidget('comparison_agent.reflection_check', [
            'should_reflect' => $this->reflection->shouldReflect($response, 1.0, $intent),
        ], $trace);

        if ($this->reflection->shouldReflect($response, 1.0, $intent)) {
            $reflectionResult = $this->reflection->reflect(
                $response,
                $validationContext,
                $intent,
                $messages
            );

            $this->traceWidget('comparison_agent.reflection_completed', [
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

        $this->traceWidget('comparison_agent.completed', [
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
}
