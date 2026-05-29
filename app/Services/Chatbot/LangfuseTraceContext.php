<?php

namespace App\Services\Chatbot;

class LangfuseTraceContext
{
    private ?string $traceId = null;

    /**
     * @var array<int, string>
     */
    private array $observationStack = [];

    /**
     * @var array<string, mixed>
     */
    private array $metadata = [];

    public function setTrace(?string $traceId, array $metadata = []): void
    {
        $this->traceId = $traceId;
        $this->metadata = $metadata;
        $this->observationStack = [];
    }

    public function traceId(): ?string
    {
        return $this->traceId;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function clear(): void
    {
        $this->traceId = null;
        $this->metadata = [];
        $this->observationStack = [];
    }

    public function pushObservation(string $observationId): void
    {
        $this->observationStack[] = $observationId;
    }

    public function popObservation(): ?string
    {
        return array_pop($this->observationStack);
    }

    public function currentObservationId(): ?string
    {
        if ($this->observationStack === []) {
            return null;
        }

        return $this->observationStack[array_key_last($this->observationStack)];
    }
}
