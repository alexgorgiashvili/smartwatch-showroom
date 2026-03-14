<?php

namespace App\Livewire\Inbox;

use App\Repositories\ConversationRepository;
use App\Repositories\MessageRepository;
use App\Services\Business\ConversationManager;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ChatWorkspace extends Component
{
    #[Reactive]
    public ?int $conversationId = null;

    public array $typingAgents = [];

    public function mount(): void
    {
        if ($this->conversationId) {
            $this->markAsRead();
        }
    }

    #[On('conversation-selected')]
    public function handleConversationSelected(int $conversationId): void
    {
        if ($conversationId === $this->conversationId) {
            $this->markAsRead();
        }
    }

    #[On('message-sent')]
    public function handleMessageSent(int $conversationId): void
    {
        if ($conversationId === $this->conversationId) {
            $this->markAsRead();
            $this->dispatch('scroll-to-bottom');
        }
    }

    #[On('inbox-message-received')]
    public function handleInboxMessageReceived(array $event = []): void
    {
        if (!$this->conversationId) {
            return;
        }

        $incomingConversationId = (int) ($event['conversationId'] ?? $event['conversation_id'] ?? 0);

        if ($incomingConversationId !== 0 && $incomingConversationId !== $this->conversationId) {
            return;
        }

        $this->markAsRead();
        $this->dispatch('$refresh');
        $this->dispatch('message-received');
    }

    public function toggleAi(): void
    {
        if (!$this->conversationId) {
            return;
        }

        $success = app(ConversationManager::class)->toggleAiMode($this->conversationId);

        if ($success) {
            $this->dispatch('conversation-updated');
        }
    }

    public function updateStatus(string $status): void
    {
        if (!$this->conversationId) {
            return;
        }

        $success = app(ConversationManager::class)->updateConversationStatus($this->conversationId, $status);

        if ($success) {
            $this->dispatch('conversation-updated');
        }
    }

    public function setPriority(string $priority): void
    {
        if (!$this->conversationId) {
            return;
        }

        $success = app(ConversationManager::class)->setPriority($this->conversationId, $priority);

        if ($success) {
            $this->dispatch('conversation-updated');
        }
    }

    // Temporarily disabled - will implement via JavaScript
    // #[On('echo-private:inbox.conversation.{conversationId},.MessageSent')]
    // public function handleMessageSentEcho($event): void
    // {
    //     $this->dispatch('new-message-received', $event);
    // }

    // #[On('echo-private:inbox.conversation.{conversationId},.MessageReceived')]
    // public function handleMessageReceivedEcho($event): void
    // {
    //     $this->markAsRead();
    //     $this->dispatch('new-message-received', $event);
    // }

    // #[On('echo-private:inbox.conversation.{conversationId},.AgentTypingStarted')]
    // public function handleAgentTypingStarted($event): void
    // {
    //     $agentId = $event['agent_id'] ?? null;
    //     $agentName = $event['agent_name'] ?? 'Agent';

    //     if ($agentId && $agentId !== auth()->id()) {
    //         $this->typingAgents[$agentId] = $agentName;
    //     }
    // }

    // #[On('echo-private:inbox.conversation.{conversationId},.AgentTypingStopped')]
    // public function handleAgentTypingStopped($event): void
    // {
    //     $agentId = $event['agent_id'] ?? null;

    //     if ($agentId && isset($this->typingAgents[$agentId])) {
    //         unset($this->typingAgents[$agentId]);
    //     }
    // }

    protected function markAsRead(): void
    {
        if (!$this->conversationId) {
            $this->dispatchUnreadCountUpdated();
            return;
        }

        $conversation = app(ConversationRepository::class)->findForChat($this->conversationId);

        if ($conversation && $conversation->unread_count > 0) {
            app(ConversationRepository::class)->markAsRead($this->conversationId);
            app(MessageRepository::class)->markConversationMessagesAsRead($this->conversationId);
            $this->dispatch('conversation-updated');
        }

        $this->dispatchUnreadCountUpdated();
    }

    protected function dispatchUnreadCountUpdated(): void
    {
        $totalUnread = app(ConversationRepository::class)->getUnreadCount();

        $this->dispatch('inbox-unread-count-updated', totalUnread: (int) $totalUnread);
    }

    public function render()
    {
        $conversation = null;
        $messages = collect();

        if ($this->conversationId) {
            $conversation = app(ConversationRepository::class)->findForChat($this->conversationId);

            if ($conversation) {
                $messages = app(MessageRepository::class)->getConversationMessages($this->conversationId, 60);
            }
        }

        return view('livewire.inbox.chat-workspace', [
            'conversation' => $conversation,
            'messages' => $messages,
            'typingAgents' => $this->typingAgents,
        ]);
    }
}
