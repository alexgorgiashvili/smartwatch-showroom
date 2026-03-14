<?php

namespace App\Livewire\Inbox;

use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ConversationList extends Component
{
    use WithPagination;

    #[Reactive]
    public ?int $selectedConversationId = null;

    #[Url(as: 'platform')]
    public string $platformFilter = 'all';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPlatformFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function selectConversation(int $conversationId): void
    {
        $this->dispatch('conversation-selected', conversationId: $conversationId);
    }

    public function refreshConversations(): void
    {
    }

    public function handleConversationUpdated(): void
    {
    }

    public function handleIncomingMessage(array $event): void
    {
        $message = data_get($event, 'message', []);

        if (($message['sender_type'] ?? null) !== 'admin') {
            $customerName = data_get($event, 'customer.name', 'Customer');

            $this->dispatch(
                'inbox-browser-notification',
                title: 'New message from ' . $customerName,
                body: str((string) ($message['content'] ?? ''))->limit(120)->toString(),
                conversationId: (int) data_get($event, 'conversation.id', 0)
            );
        }
    }

    public function handleConversationStatusChanged(): void
    {
    }

    protected function getListeners(): array
    {
        return [
            'conversation-updated' => 'handleConversationUpdated',
            'echo-private:inbox,.MessageReceived' => 'handleIncomingMessage',
            'echo-private:inbox,.ConversationStatusChanged' => 'handleConversationStatusChanged',
        ];
    }

    protected function getConversations(): LengthAwarePaginator
    {
        return Conversation::query()
            ->with(['customer', 'latestMessage', 'assignedAgent.user'])
            ->when($this->platformFilter !== 'all', function ($query): void {
                $query->where('platform', $this->platformFilter);
            })
            ->when($this->statusFilter !== 'all', function ($query): void {
                $query->where('status', $this->statusFilter);
            })
            ->when(trim($this->search) !== '', function ($query): void {
                $term = trim($this->search);

                $query->where(function ($conversationQuery) use ($term): void {
                    $conversationQuery
                        ->whereHas('customer', function ($customerQuery) use ($term): void {
                            $customerQuery
                                ->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%")
                                ->orWhere('phone', 'like', "%{$term}%");
                        })
                        ->orWhereHas('messages', function ($messageQuery) use ($term): void {
                            $messageQuery->where('content', 'like', "%{$term}%");
                        });
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate(20);
    }

    public function render()
    {
        return view('livewire.inbox.conversation-list', [
            'conversations' => $this->getConversations(),
            'search' => $this->search,
            'platformFilter' => $this->platformFilter,
            'statusFilter' => $this->statusFilter,
            'selectedConversationId' => $this->selectedConversationId,
        ]);
    }
}
