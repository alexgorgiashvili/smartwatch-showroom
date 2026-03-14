<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentTypingStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $conversationId;
    public int $agentId;
    public string $agentName;

    public function __construct(int $conversationId, int $agentId, string $agentName)
    {
        $this->conversationId = $conversationId;
        $this->agentId = $agentId;
        $this->agentName = $agentName;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("inbox.conversation.{$this->conversationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AgentTypingStarted';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'agent_id' => $this->agentId,
            'agent_name' => $this->agentName,
            'is_typing' => true,
        ];
    }
}
