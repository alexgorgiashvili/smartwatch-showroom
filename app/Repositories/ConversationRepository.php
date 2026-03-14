<?php

namespace App\Repositories;

use App\Models\Conversation;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ConversationRepository
{
    private const UNREAD_COUNT_CACHE_KEY = 'inbox:unread-count:v1';
    private const UNASSIGNED_COUNT_CACHE_KEY = 'inbox:unassigned-count:v1';

    public function findById(int $id): ?Conversation
    {
        return Conversation::query()
            ->select([
                'id',
                'customer_id',
                'platform',
                'platform_conversation_id',
                'subject',
                'status',
                'priority',
                'ai_mode',
                'is_ai_enabled',
                'unread_count',
                'last_message_at',
                'assigned_agent_id',
            ])
            ->with([
                'customer:id,name,email,phone,avatar_url',
                'assignedAgent:id,user_id',
                'assignedAgent.user:id,name,avatar_url',
                'latestMessage',
            ])
            ->find($id);
    }

    public function findForChat(int $id): ?Conversation
    {
        return Conversation::query()
            ->select([
                'id',
                'customer_id',
                'platform',
                'platform_conversation_id',
                'status',
                'priority',
                'ai_mode',
                'is_ai_enabled',
                'unread_count',
                'assigned_agent_id',
                'last_message_at',
            ])
            ->with([
                'customer:id,name,email,phone,avatar_url,platform_user_ids',
            ])
            ->find($id);
    }

    public function getActiveConversations(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Conversation::query()
            ->select([
                'id',
                'customer_id',
                'platform',
                'platform_conversation_id',
                'status',
                'priority',
                'ai_mode',
                'is_ai_enabled',
                'unread_count',
                'last_message_at',
            ])
            ->with([
                'customer:id,name,email,phone,avatar_url',
                'latestMessage',
            ]);

        $selfInstagramId = (string) config('services.facebook.instagram_account_id', '');
        if ($selfInstagramId !== '') {
            $query->where(function ($q) use ($selfInstagramId) {
                $q->where('platform', '!=', 'instagram')
                    ->orWhere('platform_conversation_id', '!=', $selfInstagramId);
            });
        }

        $selfFacebookPageId = (string) config('services.facebook.page_id', '');
        if ($selfFacebookPageId !== '') {
            $query->where(function ($q) use ($selfFacebookPageId) {
                $q->where('platform', '!=', 'facebook')
                    ->orWhere('platform_conversation_id', '!=', $selfFacebookPageId);
            });
        }

        if (isset($filters['platform']) && $filters['platform'] !== 'all') {
            $query->where('platform', $filters['platform']);
        }

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['assigned_agent_id'])) {
            $query->where('assigned_agent_id', $filters['assigned_agent_id']);
        }

        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $query->whereNull('assigned_agent_id');
        }

        if (isset($filters['ai_mode'])) {
            $query->where('ai_mode', $filters['ai_mode']);
        }

        if (isset($filters['search']) && trim($filters['search']) !== '') {
            $searchTerm = trim($filters['search']);
            $includeMessageBodySearch = mb_strlen($searchTerm) >= 2;

            $query->where(function ($q) use ($searchTerm, $includeMessageBodySearch) {
                $q->whereHas('customer', function ($customerQuery) use ($searchTerm) {
                    $customerQuery->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%")
                        ->orWhere('phone', 'like', "%{$searchTerm}%");
                })
                ->orWhere('subject', 'like', "%{$searchTerm}%")
                ->orWhere('platform_conversation_id', 'like', "%{$searchTerm}%");

                if ($includeMessageBodySearch) {
                    $q->orWhereHas('messages', function ($messageQuery) use ($searchTerm) {
                        $messageQuery->where('content', 'like', "%{$searchTerm}%");
                    });
                }
            });
        }

        return $query->orderByDesc('last_message_at')
            ->paginate($perPage);
    }

    public function findByPlatformId(string $platform, string $platformId): ?Conversation
    {
        return Conversation::query()
            ->where('platform', $platform)
            ->where('platform_conversation_id', $platformId)
            ->with(['customer', 'assignedAgent'])
            ->first();
    }

    public function createConversation(Customer $customer, string $platform, string $platformId, array $data = []): Conversation
    {
        $conversation = Conversation::firstOrCreate(
            [
                'platform' => $platform,
                'platform_conversation_id' => $platformId,
            ],
            [
                'customer_id' => $customer->id,
                'subject' => $data['subject'] ?? null,
                'status' => $data['status'] ?? 'active',
                'priority' => $data['priority'] ?? 'normal',
                'ai_mode' => $data['ai_mode'] ?? 'off',
                'unread_count' => 0,
                'metadata' => $data['metadata'] ?? null,
            ]
        );

        $this->flushUnreadCountersCache();

        return $conversation;
    }

    public function updateLastMessage(int $conversationId, Carbon $timestamp): void
    {
        Conversation::query()
            ->where('id', $conversationId)
            ->update(['last_message_at' => $timestamp]);
    }

    public function incrementUnreadCount(int $conversationId): void
    {
        Conversation::query()
            ->where('id', $conversationId)
            ->increment('unread_count');

        $this->updateLastMessage($conversationId, now());
        $this->flushUnreadCountersCache();
    }

    public function markAsRead(int $conversationId): void
    {
        Conversation::query()
            ->where('id', $conversationId)
            ->update(['unread_count' => 0]);

        $this->flushUnreadCountersCache();
    }

    public function assignToAgent(int $conversationId, int $agentId): void
    {
        Conversation::query()
            ->where('id', $conversationId)
            ->update([
                'assigned_agent_id' => $agentId,
                'last_agent_reply_at' => now(),
            ]);

        $this->flushUnreadCountersCache();
    }

    public function unassign(int $conversationId): void
    {
        Conversation::query()
            ->where('id', $conversationId)
            ->update(['assigned_agent_id' => null]);

        $this->flushUnreadCountersCache();
    }

    public function updateStatus(int $conversationId, string $status): void
    {
        if (!in_array($status, ['active', 'archived', 'closed'], true)) {
            return;
        }

        Conversation::query()
            ->where('id', $conversationId)
            ->update(['status' => $status]);

        $this->flushUnreadCountersCache();
    }

    public function updatePriority(int $conversationId, string $priority): void
    {
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            return;
        }

        Conversation::query()
            ->where('id', $conversationId)
            ->update(['priority' => $priority]);
    }

    public function updateAiMode(int $conversationId, string $mode): void
    {
        if (!in_array($mode, ['off', 'auto', 'manual_override'], true)) {
            return;
        }

        Conversation::query()
            ->where('id', $conversationId)
            ->update(['ai_mode' => $mode]);
    }

    public function addTag(int $conversationId, string $tag): void
    {
        $conversation = $this->findById($conversationId);

        if (!$conversation) {
            return;
        }

        $tags = $conversation->tags ?? [];

        if (!in_array($tag, $tags, true)) {
            $tags[] = $tag;
            $conversation->update(['tags' => $tags]);
        }
    }

    public function removeTag(int $conversationId, string $tag): void
    {
        $conversation = $this->findById($conversationId);

        if (!$conversation) {
            return;
        }

        $tags = $conversation->tags ?? [];
        $tags = array_values(array_filter($tags, fn($t) => $t !== $tag));
        $conversation->update(['tags' => $tags]);
    }

    public function getUnassignedCount(): int
    {
        return Cache::remember(self::UNASSIGNED_COUNT_CACHE_KEY, now()->addSeconds(20), function (): int {
            return (int) Conversation::query()
                ->whereNull('assigned_agent_id')
                ->where('status', 'active')
                ->count();
        });
    }

    public function getUnreadCount(): int
    {
        return Cache::remember(self::UNREAD_COUNT_CACHE_KEY, now()->addSeconds(20), function (): int {
            return (int) Conversation::query()
                ->where('unread_count', '>', 0)
                ->sum('unread_count');
        });
    }

    public function getByAgent(int $agentId, int $perPage = 20): LengthAwarePaginator
    {
        return Conversation::query()
            ->select([
                'id',
                'customer_id',
                'platform',
                'status',
                'priority',
                'unread_count',
                'last_message_at',
                'assigned_agent_id',
            ])
            ->where('assigned_agent_id', $agentId)
            ->with([
                'customer:id,name,email,phone,avatar_url',
                'latestMessage',
            ])
            ->orderByDesc('last_message_at')
            ->paginate($perPage);
    }

    private function flushUnreadCountersCache(): void
    {
        Cache::forget(self::UNREAD_COUNT_CACHE_KEY);
        Cache::forget(self::UNASSIGNED_COUNT_CACHE_KEY);
    }
}
