<?php

namespace App\Livewire\Inbox;

use App\Repositories\ConversationRepository;
use App\Services\Business\ConversationManager;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ChatHeader extends Component
{
    #[Reactive]
    public ?int $conversationId = null;

    public function assignToMe(): void
    {
        if (!$this->conversationId) {
            return;
        }

        $agentId = auth()->user()->agent?->id;

        if (!$agentId) {
            return;
        }

        app(ConversationManager::class)->assignConversation(
            $this->conversationId,
            $agentId,
            auth()->id()
        );

        $this->dispatch('conversation-updated');
    }

    public function unassign(): void
    {
        if (!$this->conversationId) {
            return;
        }

        app(ConversationManager::class)->unassignConversation($this->conversationId);
        $this->dispatch('conversation-updated');
    }

    public function closeChat(): void
    {
        $this->dispatch('conversation-closed');
    }

    public function render()
    {
        $conversation = null;

        if ($this->conversationId) {
            $conversation = app(ConversationRepository::class)->findForChat($this->conversationId);
        }

        return view('livewire.inbox.chat-header', [
            'conversation' => $conversation,
        ]);
    }
}
