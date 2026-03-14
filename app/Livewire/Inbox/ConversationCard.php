<?php

namespace App\Livewire\Inbox;

use App\Models\Conversation;
use Livewire\Component;

class ConversationCard extends Component
{
    public Conversation $conversation;
    public bool $isSelected = false;

    public function mount(Conversation $conversation, bool $isSelected = false): void
    {
        $this->conversation = $conversation;
        $this->isSelected = $isSelected;
    }

    public function selectConversation(): void
    {
        $this->dispatch('conversation-selected', conversationId: $this->conversation->id);
    }

    public function getPlatformIcon(): string
    {
        return match ($this->conversation->platform) {
            'instagram' => 'camera',
            'facebook', 'messenger' => 'chat-bubble-left-right',
            'whatsapp' => 'device-phone-mobile',
            default => 'chat-bubble-left-right',
        };
    }

    public function getPlatformColor(): string
    {
        return match ($this->conversation->platform) {
            'instagram' => 'text-pink-600',
            'facebook', 'messenger' => 'text-blue-600',
            'whatsapp' => 'text-green-600',
            default => 'text-gray-600',
        };
    }

    public function render()
    {
        return view('livewire.inbox.conversation-card', [
            'platformIcon' => $this->getPlatformIcon(),
            'platformColor' => $this->getPlatformColor(),
        ]);
    }
}
