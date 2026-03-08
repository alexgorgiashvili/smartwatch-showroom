<?php

namespace App\Services\Chatbot;

class InputGuardResult
{
    public function __construct(
        private bool $allowed,
        private string $sanitizedInput,
        private ?string $reason = null,
        private ?string $safeReply = null
    ) {
    }

    public static function allow(string $sanitizedInput): self
    {
        return new self(true, $sanitizedInput);
    }

    public static function block(string $sanitizedInput, string $reason, string $safeReply): self
    {
        return new self(false, $sanitizedInput, $reason, $safeReply);
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function sanitizedInput(): string
    {
        return $this->sanitizedInput;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function safeReply(): ?string
    {
        return $this->safeReply;
    }
}
