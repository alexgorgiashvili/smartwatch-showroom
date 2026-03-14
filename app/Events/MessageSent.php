<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;
    public Conversation $conversation;
    public User $sender;
    public string $platform;

    public function __construct(Message $message, Conversation $conversation, User $sender, string $platform)
    {
        $this->message = $message->load(['customer']);
        $this->conversation = $conversation->load('customer');
        $this->sender = $sender;
        $this->platform = $platform;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('inbox'),
            new PrivateChannel("inbox.conversation.{$this->conversation->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_type' => $this->message->sender_type,
                'sender_id' => $this->message->sender_id,
                'sender_name' => $this->message->sender_name,
                'content' => $this->message->content,
                'media_url' => $this->message->media_url,
                'media_type' => $this->message->media_type,
                'delivery_status' => $this->message->delivery_status,
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
            'conversation' => [
                'id' => $this->conversation->id,
                'platform' => $this->conversation->platform,
                'last_message_at' => $this->conversation->last_message_at?->toIso8601String(),
            ],
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
            ],
            'platform' => $this->platform,
        ];
    }
}
