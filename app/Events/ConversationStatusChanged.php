<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The conversation instance
     */
    public Conversation $conversation;

    /**
     * The old status
     */
    public string $oldStatus;

    /**
     * The new status
     */
    public string $newStatus;

    /**
     * Create a new event instance
     */
    public function __construct(Conversation $conversation, string $oldStatus, string $newStatus)
    {
        // Eager load relationships for efficient broadcasting
        $this->conversation = $conversation->load('customer');
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('inbox'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ConversationStatusChanged';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'conversation' => [
                'id' => $this->conversation->id,
                'customer_id' => $this->conversation->customer_id,
                'platform' => $this->conversation->platform,
                'platform_conversation_id' => $this->conversation->platform_conversation_id,
                'subject' => $this->conversation->subject,
                'status' => $this->conversation->status,
                'unread_count' => $this->conversation->unread_count,
                'last_message_at' => $this->conversation->last_message_at,
                'created_at' => $this->conversation->created_at,
                'updated_at' => $this->conversation->updated_at,
            ],
            'customer' => [
                'id' => $this->conversation->customer->id,
                'name' => $this->conversation->customer->name,
                'email' => $this->conversation->customer->email,
                'phone' => $this->conversation->customer->phone,
                'avatar_url' => $this->conversation->customer->avatar_url,
                'created_at' => $this->conversation->customer->created_at,
                'updated_at' => $this->conversation->customer->updated_at,
            ],
            'oldStatus' => $this->oldStatus,
            'newStatus' => $this->newStatus,
        ];
    }
}
