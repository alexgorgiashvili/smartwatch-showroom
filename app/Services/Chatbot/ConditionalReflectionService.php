<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Log;

class ConditionalReflectionService
{
    public function __construct(
        private ResponseValidatorService $validator,
        private ModelCompletionService $modelCompletion,
        private PromptBuilderService $promptBuilder,
        private UnifiedAiPolicyService $policy
    ) {
    }

    /**
     * Determine if reflection should be triggered
     */
    public function shouldReflect(
        string $response,
        float $retrievalScore,
        IntentResult $intent
    ): bool {
        if (!config('chatbot.reflection.enabled', true)) {
            return false;
        }

        $confidenceThreshold = config('chatbot.reflection.confidence_threshold', 0.7);

        if ($retrievalScore < $confidenceThreshold) {
            return true;
        }

        if (in_array($intent->intent(), ['price_query', 'stock_query'], true)) {
            return true;
        }

        if ($intent->intent() === 'comparison') {
            return true;
        }

        return false;
    }

    /**
     * Perform reflection with retry logic
     *
     * @return array{success: bool, response: string, attempts: int, violations: array}
     */
    public function reflect(
        string $initialResponse,
        array $validationContext,
        IntentResult $intent,
        array $messages
    ): array {
        $maxRetries = config('chatbot.reflection.max_retries', 3);
        $model = config('chatbot.reflection.critique_model', 'gpt-4o-mini');

        $validation = $this->validator->validateAll($initialResponse, $validationContext, $intent);

        if ($validation->isValid()) {
            return [
                'success' => true,
                'response' => $initialResponse,
                'attempts' => 0,
                'violations' => [],
            ];
        }

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            Log::info("Reflection attempt {$attempt}", [
                'violations' => $validation->violations(),
            ]);

            $regenerated = $this->regenerateWithFeedback(
                $initialResponse,
                $validation->violations(),
                $messages,
                $model,
                $attempt
            );

            if ($regenerated['reason'] !== null) {
                continue;
            }

            $candidateReply = $regenerated['reply'];

            if (!$this->policy->passesStrictGeorgianQa($candidateReply)) {
                continue;
            }

            $candidateValidation = $this->validator->validateAll($candidateReply, $validationContext, $intent);

            if ($candidateValidation->isValid()) {
                return [
                    'success' => true,
                    'response' => $candidateReply,
                    'attempts' => $attempt,
                    'violations' => [],
                ];
            }

            if ($this->isPartialImprovement($validation, $candidateValidation)) {
                $validation = $candidateValidation;
                $initialResponse = $candidateReply;
            }
        }

        return [
            'success' => false,
            'response' => $initialResponse,
            'attempts' => $maxRetries,
            'violations' => $validation->violations(),
        ];
    }

    /**
     * Regenerate response with progressive feedback
     */
    private function regenerateWithFeedback(
        string $invalidReply,
        array $violations,
        array $messages,
        string $model,
        int $attempt
    ): array {
        $regenerationMessages = $messages;
        
        $regenerationMessages[] = [
            'role' => 'assistant',
            'content' => $invalidReply,
        ];

        $feedbackInstruction = $this->buildProgressiveFeedback($violations, $attempt);
        
        $regenerationMessages[] = [
            'role' => 'user',
            'content' => $feedbackInstruction,
        ];

        return $this->modelCompletion->complete($model, $regenerationMessages);
    }

    /**
     * Build progressive feedback based on attempt number
     */
    private function buildProgressiveFeedback(array $violations, int $attempt): string
    {
        $baseInstruction = $this->promptBuilder->buildRegenerationInstruction($violations);

        if ($attempt === 1) {
            return $baseInstruction;
        }

        if ($attempt === 2) {
            return $baseInstruction . "\n\nIMPORTANT: This is your second attempt. Be extra careful with the validation context. Only mention products, prices, and stock levels that are explicitly provided.";
        }

        return $baseInstruction . "\n\nFINAL ATTEMPT: You must fix all violations. Double-check every price, stock claim, and URL against the provided context. If unsure, omit the detail rather than guessing.";
    }

    /**
     * Check if new validation is a partial improvement
     */
    private function isPartialImprovement(ValidationResult $old, ValidationResult $new): bool
    {
        return count($new->violations()) < count($old->violations());
    }

    /**
     * Get reflection statistics
     */
    public function getStats(): array
    {
        return [
            'enabled' => config('chatbot.reflection.enabled', true),
            'max_retries' => config('chatbot.reflection.max_retries', 3),
            'confidence_threshold' => config('chatbot.reflection.confidence_threshold', 0.7),
            'critique_model' => config('chatbot.reflection.critique_model', 'gpt-4o-mini'),
        ];
    }
}
