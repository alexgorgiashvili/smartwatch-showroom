<?php

namespace App\Livewire\Inbox;

use App\Models\Conversation;
use App\Services\AiSuggestionService;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class AIControls extends Component
{
    #[Reactive]
    public ?int $conversationId = null;

    public array $suggestions = [];

    public bool $isGenerating = false;

    public bool $aiEnabled = false;

    public function mount(): void
    {
        if ($this->conversationId) {
            $this->loadAIStatus();
        }
    }

    public function loadAIStatus(): void
    {
        if (!$this->conversationId) {
            return;
        }

        $conversation = Conversation::find($this->conversationId);
        $this->aiEnabled = $conversation?->is_ai_enabled ?? false;
    }

    public function generateSuggestion(AiSuggestionService $aiService): void
    {
        if (!$this->conversationId || $this->isGenerating) {
            return;
        }

        $conversation = Conversation::with(['customer', 'latestMessage'])
            ->find($this->conversationId);

        if (!$conversation || !$conversation->latestMessage) {
            return;
        }

        $this->isGenerating = true;

        try {
            $suggestions = $aiService->generateSuggestions(
                $conversation,
                $conversation->latestMessage,
                3
            );

            $this->suggestions = $suggestions ?? [];
            $this->dispatch('suggestions-generated', suggestions: $suggestions);
        } catch (\Exception $e) {
            $this->dispatch('ai-error', message: 'Failed to generate suggestions');
        } finally {
            $this->isGenerating = false;
        }
    }

    public function useSuggestion(string $suggestion): void
    {
        $this->dispatch('use-suggestion', suggestion: $suggestion);
        $this->suggestions = [];
    }

    public function toggleAI(): void
    {
        if (!$this->conversationId) {
            return;
        }

        $conversation = Conversation::find($this->conversationId);

        if (!$conversation) {
            return;
        }

        if ($conversation->is_ai_enabled) {
            $conversation->disableAI();
            $this->aiEnabled = false;
        } else {
            $conversation->enableAI();
            $this->aiEnabled = true;
        }

        $this->dispatch('ai-toggled', enabled: $this->aiEnabled);
    }

    public function getConversationProperty(): ?Conversation
    {
        return $this->conversationId ? Conversation::find($this->conversationId) : null;
    }

    public function render()
    {
        return view('livewire.inbox.a-i-controls');
    }
}
