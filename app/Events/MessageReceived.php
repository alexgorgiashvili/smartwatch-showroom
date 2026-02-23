<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The message instance
     */
    public Message $message;

    /**
     * The conversation instance
     */
    public Conversation $conversation;

    /**
     * The customer instance
     */
    public Customer $customer;

    /**
     * Platform name
     */
    public string $platform;

    /**
     * Create a new event instance
     */
    public function __construct(Message $message, Conversation $conversation, Customer $customer, string $platform)
    {
        // Eager load relationships for efficient broadcasting
        $this->message = $message->load(['conversation', 'customer']);
        $this->conversation = $conversation->load('customer');
        $this->customer = $customer;
        $this->platform = $platform;
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
        return 'MessageReceived';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'customer_id' => $this->message->customer_id,
                'sender_type' => $this->message->sender_type,
                'sender_id' => $this->message->sender_id,
                'sender_name' => $this->message->sender_name,
                'content' => $this->message->content,
                'media_url' => $this->message->media_url,
                'media_type' => $this->message->media_type,
                'platform_message_id' => $this->message->platform_message_id,
                'metadata' => $this->message->metadata,
                'read_at' => $this->message->read_at,
                'created_at' => $this->message->created_at,
                'updated_at' => $this->message->updated_at,
            ],
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
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
                'avatar_url' => $this->customer->avatar_url,
                'created_at' => $this->customer->created_at,
                'updated_at' => $this->customer->updated_at,
            ],
            'platform' => $this->platform,
        ];
    }
}
