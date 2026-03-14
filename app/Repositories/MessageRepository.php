<?php

namespace App\Repositories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;

class MessageRepository
{
    public function findById(int $id): ?Message
    {
        return Message::query()
            ->with(['conversation', 'customer'])
            ->find($id);
    }

    public function getConversationMessages(int $conversationId, int $limit = 100): Collection
    {
        return Message::query()
            ->where('conversation_id', $conversationId)
            ->select([
                'id',
                'conversation_id',
                'sender_type',
                'sender_name',
                'content',
                'media_url',
                'media_type',
                'delivery_status',
                'created_at',
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function getLatestMessages(int $conversationId, int $limit = 50): Collection
    {
        return Message::query()
            ->where('conversation_id', $conversationId)
            ->with(['customer', 'replyTo'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function createMessage(array $data): Message
    {
        return Message::create([
            'conversation_id' => $data['conversation_id'],
            'customer_id' => $data['customer_id'],
            'reply_to_id' => $data['reply_to_id'] ?? null,
            'sender_type' => $data['sender_type'],
            'sender_id' => $data['sender_id'],
            'sender_name' => $data['sender_name'],
            'content' => $data['content'] ?? '',
            'media_url' => $data['media_url'] ?? null,
            'media_type' => $data['media_type'] ?? null,
            'platform_message_id' => $data['platform_message_id'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'delivery_status' => $data['delivery_status'] ?? 'sent',
        ]);
    }

    public function findByPlatformMessageId(string $platformMessageId): ?Message
    {
        return Message::query()
            ->where('platform_message_id', $platformMessageId)
            ->with(['conversation', 'customer'])
            ->first();
    }

    public function markAsRead(int $messageId): void
    {
        Message::query()
            ->where('id', $messageId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markConversationMessagesAsRead(int $conversationId): void
    {
        Message::query()
            ->where('conversation_id', $conversationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function updateDeliveryStatus(int $messageId, string $status): void
    {
        if (!in_array($status, ['pending', 'sent', 'delivered', 'failed'], true)) {
            return;
        }

        Message::query()
            ->where('id', $messageId)
            ->update(['delivery_status' => $status]);
    }

    public function getUnreadCount(int $conversationId): int
    {
        return Message::query()
            ->where('conversation_id', $conversationId)
            ->whereNull('read_at')
            ->count();
    }

    public function getMessagesSince(int $conversationId, int $messageId): Collection
    {
        return Message::query()
            ->where('conversation_id', $conversationId)
            ->where('id', '>', $messageId)
            ->with(['customer', 'replyTo'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function searchMessages(string $query, ?int $conversationId = null, int $limit = 50): Collection
    {
        $queryBuilder = Message::query()
            ->where('content', 'like', "%{$query}%")
            ->with(['conversation', 'customer']);

        if ($conversationId) {
            $queryBuilder->where('conversation_id', $conversationId);
        }

        return $queryBuilder
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function deleteMessage(int $messageId): bool
    {
        return Message::query()
            ->where('id', $messageId)
            ->delete();
    }

    public function getMediaMessages(int $conversationId): Collection
    {
        return Message::query()
            ->where('conversation_id', $conversationId)
            ->whereNotNull('media_url')
            ->with(['customer'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function countByType(int $conversationId, string $senderType): int
    {
        return Message::query()
            ->where('conversation_id', $conversationId)
            ->where('sender_type', $senderType)
            ->count();
    }
}
