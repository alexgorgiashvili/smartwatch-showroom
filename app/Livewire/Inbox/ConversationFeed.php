<?php

namespace App\Livewire\Inbox;

use App\Services\Business\ConversationManager;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\WithPagination;

class ConversationFeed extends Component
{
    use WithPagination;

    #[Reactive]
    public ?int $selectedConversationId = null;

    #[Reactive]
    public ?string $platformFilter = null;

    #[Reactive]
    public string $statusFilter = 'all';

    #[Reactive]
    public string $searchQuery = '';

    public function mount(): void
    {
        //
    }

    #[On('filters-updated')]
    public function updateFilters(array $filters): void
    {
        $this->platformFilter = $filters['platform'] ?? 'all';
        $this->statusFilter = $filters['status'] ?? 'all';
        $this->searchQuery = $filters['search'] ?? '';

        $this->resetPage();
    }

    public function selectConversation(int $conversationId): void
    {
        $this->dispatch('conversation-selected', conversationId: $conversationId);
    }

    #[On('inbox-message-received')]
    public function handleMessageReceived(array $event = []): void
    {
        $incomingConversationId = (int) ($event['conversationId'] ?? $event['conversation_id'] ?? 0);
        $senderType = (string) ($event['senderType'] ?? $event['sender_type'] ?? '');

        if (
            $incomingConversationId !== 0
            && $incomingConversationId === (int) $this->selectedConversationId
            && $senderType === 'admin'
        ) {
            return;
        }

        if ($this->getPage() !== 1) {
            $this->resetPage();
        }
    }

    #[On('inbox-conversation-assigned')]
    public function handleConversationAssigned(): void
    {
        if ($this->getPage() !== 1) {
            $this->resetPage();
        }
    }

    #[On('conversation-updated')]
    public function handleConversationUpdated(): void
    {
        //
    }

    // Temporarily disabled - will implement via JavaScript
    // #[On('echo-private:inbox,.MessageReceived')]
    // public function handleEchoMessageReceived($event): void
    // {
    //     $conversationId = $event['conversation']['id'] ?? null;
    //
    //     if ($conversationId && $conversationId !== $this->selectedConversationId) {
    //         $customerName = $event['customer']['name'] ?? 'Customer';
    //         $messageContent = $event['message']['content'] ?? '';
    //
    //         $this->dispatch('inbox-browser-notification', [
    //             'title' => 'New message from ' . $customerName,
    //             'body' => mb_substr($messageContent, 0, 120),
    //             'conversationId' => $conversationId,
    //         ]);
    //     }
    // }

    public function render()
    {
        $filters = [
            'platform' => $this->platformFilter !== 'all' ? $this->platformFilter : null,
            'status' => $this->statusFilter !== 'all' ? $this->statusFilter : null,
            'search' => trim($this->searchQuery) !== '' ? trim($this->searchQuery) : null,
        ];

        $filters = array_filter($filters, fn($value) => $value !== null);

        $conversations = app(ConversationManager::class)
            ->getFilteredConversations($filters, 20);

        return view('livewire.inbox.conversation-feed', [
            'conversations' => $conversations,
        ]);
    }
}
