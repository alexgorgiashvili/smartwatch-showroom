<?php

namespace App\Livewire\Inbox;

use App\Models\Conversation;
use App\Models\Order;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class CustomerInfoPanel extends Component
{
    #[Reactive]
    public ?int $conversationId = null;

    public string $internalNotes = '';

    public bool $isEditingNotes = false;

    public function mount(): void
    {
        if ($this->conversationId) {
            $this->loadInternalNotes();
        }
    }

    public function loadInternalNotes(): void
    {
        if (!$this->conversationId) {
            return;
        }

        $conversation = Conversation::find($this->conversationId);
        $this->internalNotes = $conversation?->internal_notes ?? '';
    }

    public function saveInternalNotes(): void
    {
        if (!$this->conversationId) {
            return;
        }

        $conversation = Conversation::find($this->conversationId);

        if (!$conversation) {
            return;
        }

        $conversation->update(['internal_notes' => $this->internalNotes]);
        $this->isEditingNotes = false;

        $this->dispatch('notes-saved', conversationId: $this->conversationId);
    }

    public function assignToMe(): void
    {
        if (!$this->conversationId) {
            return;
        }

        $conversation = Conversation::find($this->conversationId);
        $agent = auth()->user()->agent;

        if (!$conversation || !$agent) {
            return;
        }

        $conversation->assignToAgent($agent);
        $this->dispatch('conversation-assigned', conversationId: $this->conversationId);
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
        } else {
            $conversation->enableAI();
        }

        $this->dispatch('ai-toggled', conversationId: $this->conversationId, enabled: $conversation->is_ai_enabled);
    }

    public function closeConversation(): void
    {
        if (!$this->conversationId) {
            return;
        }

        $conversation = Conversation::find($this->conversationId);

        if (!$conversation) {
            return;
        }

        $conversation->close();
        $this->dispatch('conversation-closed', conversationId: $this->conversationId);
    }

    public function archiveConversation(): void
    {
        if (!$this->conversationId) {
            return;
        }

        $conversation = Conversation::find($this->conversationId);

        if (!$conversation) {
            return;
        }

        $conversation->archive();
        $this->dispatch('conversation-archived', conversationId: $this->conversationId);
    }

    public function getConversationProperty(): ?Conversation
    {
        return $this->conversationId ? Conversation::find($this->conversationId) : null;
    }

    public function getCustomerProperty()
    {
        return $this->conversation?->customer;
    }

    public function getRecentOrdersProperty()
    {
        if (!$this->customer) {
            return collect();
        }

        // Order model has customer fields directly, not a relationship
        // We'll match by customer name and email
        return Order::where(function($query) {
                $query->where('customer_name', $this->customer->name);
                if ($this->customer->email) {
                    $query->orWhere('customer_email', $this->customer->email);
                }
                if ($this->customer->phone) {
                    $query->orWhere('customer_phone', $this->customer->phone);
                }
            })
            ->with('items')
            ->latest()
            ->take(5)
            ->get();
    }

    public function getPreviousConversationsProperty()
    {
        if (!$this->customer) {
            return collect();
        }

        return Conversation::where('customer_id', $this->customer->id)
            ->where('id', '!=', $this->conversationId)
            ->with(['latestMessage', 'assignedAgent.user'])
            ->latest('last_message_at')
            ->take(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.inbox.customer-info-panel');
    }
}
