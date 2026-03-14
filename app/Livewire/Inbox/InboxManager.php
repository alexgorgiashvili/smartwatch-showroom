<?php

namespace App\Livewire\Inbox;

use Livewire\Attributes\On;
use Livewire\Component;

class InboxManager extends Component
{
    public ?int $selectedConversationId = null;

    public bool $mobileConversationOpen = false;

    #[On('conversation-selected')]
    public function openConversation(int $conversationId): void
    {
        $this->selectedConversationId = $conversationId;
        $this->mobileConversationOpen = true;
    }

    #[On('conversation-closed')]
    public function closeConversation(): void
    {
        $this->mobileConversationOpen = false;
    }

    public function refreshConversations(): void
    {
        // This method is called by wire:poll to refresh the conversation list
        // The actual refresh happens in the ConversationList component
    }

    public function render()
    {
        return view('livewire.inbox.inbox-manager', [
            'mobileConversationOpen' => $this->mobileConversationOpen,
            'selectedConversationId' => $this->selectedConversationId,
        ]);
    }
}
