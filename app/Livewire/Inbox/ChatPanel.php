<?php

namespace App\Livewire\Inbox;

use App\Events\ConversationStatusChanged;
use App\Models\Conversation;
use App\Models\Message;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

class ChatPanel extends Component
{
    public ?int $conversationId = null;

    public ?array $typingAgent = null;

    public function mount(?int $conversationId = null): void
    {
        $this->conversationId = $conversationId;

        if ($this->conversationId) {
            $this->markConversationAsRead();
        }
    }

    #[On('message-sent')]
    public function handleMessageSent(int $conversationId): void
    {
        if ($conversationId === $this->conversationId) {
            $this->markConversationAsRead();
        }
    }

    #[On('agent.typing')]
    public function handleAgentTyping(array $event): void
    {
        if ($event['conversation_id'] === $this->conversationId) {
            if ($event['is_typing'] && $event['agent_id'] !== auth()->id()) {
                $this->typingAgent = [
                    'name' => $event['agent_name'],
                    'id' => $event['agent_id'],
                ];
            } else {
                $this->typingAgent = null;
            }
        }
    }

    public function refreshMessages(): void
    {
        if ($this->conversationId) {
            $this->markConversationAsRead();
        }
    }

    public function toggleAi(): void
    {
        $conversation = $this->getConversationModel();

        if (! $conversation) {
            return;
        }

        $conversation->update([
            'ai_enabled' => ! $conversation->ai_enabled,
        ]);

        $this->dispatch('conversation-updated', conversationId: $conversation->id);
    }

    public function updateStatus(string $status): void
    {
        if (! in_array($status, ['active', 'archived', 'closed'], true)) {
            return;
        }

        $conversation = $this->getConversationModel();

        if (! $conversation || $conversation->status === $status) {
            return;
        }

        $oldStatus = $conversation->status;

        $conversation->update([
            'status' => $status,
        ]);

        ConversationStatusChanged::dispatch($conversation->fresh('customer'), $oldStatus, $status);

        $this->dispatch('conversation-updated', conversationId: $conversation->id);

        Notification::make()
            ->title('Conversation status updated.')
            ->success()
            ->send();
    }

    public function handleIncomingMessage(array $event): void
    {
        $conversationId = (int) data_get($event, 'conversation.id', 0);

        if ($conversationId !== $this->conversationId) {
            return;
        }

        if (data_get($event, 'message.sender_type') !== 'admin') {
            $this->markConversationAsRead();
        }

        $this->dispatch('conversation-updated', conversationId: $conversationId);
    }

    public function handleConversationStatusChanged(array $event): void
    {
        if ((int) data_get($event, 'conversation.id', 0) === $this->conversationId) {
            $this->dispatch('conversation-updated', conversationId: $this->conversationId);
        }
    }

    protected function getListeners(): array
    {
        return [
            'echo-private:inbox,.MessageReceived' => 'handleIncomingMessage',
            'echo-private:inbox,.ConversationStatusChanged' => 'handleConversationStatusChanged',
        ];
    }

    protected function getConversationModel(): ?Conversation
    {
        if (! $this->conversationId) {
            return null;
        }

        return Conversation::query()->find($this->conversationId);
    }

    protected function markConversationAsRead(): void
    {
        $conversation = $this->getConversationModel();

        if (! $conversation || $conversation->unread_count <= 0) {
            return;
        }

        $conversation->markAsRead();
        $this->dispatch('conversation-updated', conversationId: $conversation->id);
    }

    public function render()
    {
        $conversation = null;
        $messages = collect();

        if ($this->conversationId) {
            $conversation = Conversation::query()
                ->with('customer')
                ->find($this->conversationId);

            if ($conversation) {
                $messages = Message::query()
                    ->where('conversation_id', $conversation->id)
                    ->orderBy('created_at')
                    ->get();
            }
        }

        return view('livewire.inbox.chat-panel', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }
}
