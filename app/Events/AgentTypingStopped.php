<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentTypingStopped implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $conversationId;
    public int $agentId;

    public function __construct(int $conversationId, int $agentId)
    {
        $this->conversationId = $conversationId;
        $this->agentId = $agentId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("inbox.conversation.{$this->conversationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AgentTypingStopped';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'agent_id' => $this->agentId,
            'is_typing' => false,
        ];
    }
}
