<?php

namespace App\Events;

use App\Models\Agent;
use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationTransferred implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Agent $fromAgent,
        public Agent $toAgent,
        public Conversation $conversation
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agent.' . $this->fromAgent->id),
            new PrivateChannel('agent.' . $this->toAgent->id),
            new PrivateChannel('inbox'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.transferred';
    }

    public function broadcastWith(): array
    {
        return [
            'from_agent_id' => $this->fromAgent->id,
            'from_agent_name' => $this->fromAgent->user->name,
            'to_agent_id' => $this->toAgent->id,
            'to_agent_name' => $this->toAgent->user->name,
            'conversation_id' => $this->conversation->id,
            'customer_name' => $this->conversation->customer->name,
            'message' => "Conversation transferred from {$this->fromAgent->user->name} to {$this->toAgent->user->name}",
        ];
    }
}
