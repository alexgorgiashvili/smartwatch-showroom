<?php

namespace App\Services\Chatbot;

class ChatbotFallbackResolution
{
    public function __construct(
        private string $reply,
        private ?string $fallbackReason,
        private bool $validationPassed,
        private array $validationViolations,
        private bool $georgianPassed,
        private bool $regenerationAttempted = false,
        private bool $regenerationSucceeded = false
    ) {
    }

    public function reply(): string
    {
        return $this->reply;
    }

    public function fallbackReason(): ?string
    {
        return $this->fallbackReason;
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

    public function regenerationAttempted(): bool
    {
        return $this->regenerationAttempted;
    }

    public function regenerationSucceeded(): bool
    {
        return $this->regenerationSucceeded;
    }
}
