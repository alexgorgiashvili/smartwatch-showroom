<?php

namespace App\Livewire\Inbox;

use App\Models\Message;
use Livewire\Component;

class MessageBubble extends Component
{
    public Message $message;

    public function mount(Message $message): void
    {
        $this->message = $message;
    }

    public function getBubbleClasses(): string
    {
        return match ($this->message->sender_type) {
            'customer' => 'bg-gray-100 text-gray-900',
            'admin' => 'bg-violet-600 text-white ml-auto',
            'bot' => 'bg-emerald-100 text-emerald-900 border border-emerald-300',
            'system' => 'bg-amber-50 text-amber-900 text-sm italic',
            default => 'bg-gray-100 text-gray-900',
        };
    }

    public function getAlignment(): string
    {
        return match ($this->message->sender_type) {
            'admin' => 'justify-end',
            default => 'justify-start',
        };
    }

    public function getSenderLabel(): string
    {
        return match ($this->message->sender_type) {
            'customer' => $this->message->sender_name,
            'admin' => 'You',
            'bot' => 'AI Assistant',
            'system' => 'System',
            default => $this->message->sender_name,
        };
    }

    public function render()
    {
        return view('livewire.inbox.message-bubble', [
            'bubbleClasses' => $this->getBubbleClasses(),
            'alignment' => $this->getAlignment(),
            'senderLabel' => $this->getSenderLabel(),
        ]);
    }
}
