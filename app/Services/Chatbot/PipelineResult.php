<?php

namespace App\Services\Chatbot;

class PipelineResult
{
    public function __construct(
        private string $response,
        private int $conversationId,
        private string $ragContextText,
        private ?IntentResult $intentResult,
        private array $validationContext,
        private bool $guardAllowed,
        private ?string $guardReason,
        private bool $validationPassed,
        private array $validationViolations,
        private bool $georgianPassed,
        private int $responseTimeMs,
        private ?string $fallbackReason = null,
        private bool $regenerationAttempted = false,
        private bool $regenerationSucceeded = false
    ) {
    }

    public function response(): string
    {
        return $this->response;
    }

    public function conversationId(): int
    {
        return $this->conversationId;
    }

    public function ragContextText(): string
    {
        return $this->ragContextText;
    }

    public function intentResult(): ?IntentResult
    {
        return $this->intentResult;
    }

    public function validationContext(): array
    {
        return $this->validationContext;
    }

    public function guardAllowed(): bool
    {
        return $this->guardAllowed;
    }

    public function guardReason(): ?string
    {
        return $this->guardReason;
    }

    public function validationPassed(): bool
    {
        return $this->validationPassed;
    }

    public function validationViolations(): array
    {
        return $this->validationViolations;
    }

    public function georgianPassed(): bool
    {
        return $this->georgianPassed;
    }

    public function responseTimeMs(): int
    {
        return $this->responseTimeMs;
    }

    public function fallbackReason(): ?string
    {
        return $this->fallbackReason;
    }

    public function regenerationAttempted(): bool
    {
        return $this->regenerationAttempted;
    }

    public function regenerationSucceeded(): bool
    {
        return $this->regenerationSucceeded;
    }

    public function toArray(): array
    {
        return [
            'response' => $this->response,
            'conversation_id' => $this->conversationId,
            'rag_context_text' => $this->ragContextText,
            'intent_result' => $this->intentResult,
            'validation_context' => $this->validationContext,
            'guard_allowed' => $this->guardAllowed,
            'guard_reason' => $this->guardReason,
            'validation_passed' => $this->validationPassed,
            'validation_violations' => $this->validationViolations,
            'georgian_passed' => $this->georgianPassed,
            'response_time_ms' => $this->responseTimeMs,
            'fallback_reason' => $this->fallbackReason,
            'regeneration_attempted' => $this->regenerationAttempted,
            'regeneration_succeeded' => $this->regenerationSucceeded,
        ];
    }
}
