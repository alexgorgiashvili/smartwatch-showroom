<?php

namespace App\Livewire\Inbox;

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class InboxShell extends Component
{
    #[Url(as: 'conversation')]
    public ?int $selectedConversationId = null;
    public bool $showChatOnMobile = false;
    public string $searchQuery = '';
    public string $statusFilter = 'all';
    public ?string $platformFilter = null;

    public function mount(): void
    {
        if ($this->selectedConversationId) {
            $this->showChatOnMobile = true;
        }
    }

    #[On('conversation-selected')]
    public function selectConversation(int $conversationId): void
    {
        $this->selectedConversationId = $conversationId;
        $this->showChatOnMobile = true;
    }

    #[On('conversation-closed')]
    public function backToList(): void
    {
        $this->showChatOnMobile = false;
    }

    // Echo listeners removed - will be handled via JavaScript
    // #[On('echo-private:inbox,.MessageReceived')]
    // public function handleGlobalInboxUpdate($event): void
    // {
    //     $this->dispatch('inbox-message-received', $event);
    // }

    // #[On('echo-private:inbox,.ConversationAssigned')]
    // public function handleConversationAssigned($event): void
    // {
    //     $this->dispatch('inbox-conversation-assigned', $event);
    // }

    public function render()
    {
        return view('livewire.inbox.inbox-shell');
    }
}
