<?php

namespace App\Events;

use App\Models\Agent;
use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Agent $agent,
        public Conversation $conversation,
        public string $type = 'assigned'
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agent.' . $this->agent->id),
            new PrivateChannel('inbox'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'conversation.assigned';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'agent_id' => $this->agent->id,
            'agent_name' => $this->agent->user->name,
            'conversation_id' => $this->conversation->id,
            'customer_name' => $this->conversation->customer->name,
            'platform' => $this->conversation->platform,
            'type' => $this->type,
            'message' => $this->getBroadcastMessage(),
        ];
    }

    private function getBroadcastMessage(): string
    {
        return match ($this->type) {
            'assigned' => "New conversation assigned from {$this->conversation->platform}",
            'transferred' => "Conversation transferred to you",
            default => "Conversation assigned",
        };
    }
}
