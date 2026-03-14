<?php

namespace App\Livewire\Inbox;

use App\Repositories\MessageRepository;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class MessageList extends Component
{
    #[Reactive]
    public ?int $conversationId = null;

    public int $messageLimit = 100;

    #[On('new-message-received')]
    public function handleNewMessage($event): void
    {
        $this->dispatch('scroll-to-bottom');
    }

    #[On('scroll-to-bottom')]
    public function scrollToBottom(): void
    {
        // This will be handled by Alpine.js in the view
    }

    public function loadMore(): void
    {
        $this->messageLimit += 50;
    }

    public function render()
    {
        $messages = collect();

        if ($this->conversationId) {
            $messages = app(MessageRepository::class)
                ->getConversationMessages($this->conversationId, $this->messageLimit);
        }

        return view('livewire.inbox.message-list', [
            'messages' => $messages,
        ]);
    }
}
