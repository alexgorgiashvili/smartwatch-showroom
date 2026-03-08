<?php

namespace App\Services\Chatbot;

class ValidationResult
{
    public function __construct(
        private bool $valid,
        private array $violations = []
    ) {
    }

    public static function pass(): self
    {
        return new self(true, []);
    }

    public static function fail(array $violations): self
    {
        return new self(false, $violations);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function violations(): array
    {
        return $this->violations;
    }
}
