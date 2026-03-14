<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUnassigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $agentId,
        public $conversation,
        public string $reason = 'unassigned'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agent.' . $this->agentId),
            new PrivateChannel('inbox'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.unassigned';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'customer_name' => $this->conversation->customer->name,
            'reason' => $this->reason,
            'message' => "Conversation unassigned: {$this->reason}",
        ];
    }
}
