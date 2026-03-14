<?php

namespace App\Services;

use App\Models\Conversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CollisionDetectionService
{
    private const LOCK_TTL = 30; // 30 seconds

    /**
     * Check if agent can start replying to conversation
     */
    public function canStartReplying(Conversation $conversation, string $agentIdentifier): bool
    {
        // Check if another agent is currently replying
        if ($conversation->isBeingRepliedByOtherAgent($agentIdentifier)) {
            return false;
        }

        // Check for concurrent reply attempts
        $lockKey = "conversation:{$conversation->id}:replying";
        $existingLock = Cache::get($lockKey);

        if ($existingLock && $existingLock !== $agentIdentifier) {
            return false;
        }

        return true;
    }

    /**
     * Mark agent as starting to reply
     */
    public function markAsReplying(Conversation $conversation, string $agentIdentifier): bool
    {
        if (!$this->canStartReplying($conversation, $agentIdentifier)) {
            return false;
        }

        $lockKey = "conversation:{$conversation->id}:replying";
        
        // Set both database flag and cache lock
        DB::transaction(function () use ($conversation, $agentIdentifier, $lockKey) {
            $conversation->markAgentAsReplying($agentIdentifier);
            Cache::put($lockKey, $agentIdentifier, self::LOCK_TTL);
        });

        return true;
    }

    /**
     * Clear replying status
     */
    public function clearReplyingStatus(Conversation $conversation, string $agentIdentifier): void
    {
        $lockKey = "conversation:{$conversation->id}:replying";
        
        DB::transaction(function () use ($conversation, $agentIdentifier, $lockKey) {
            // Only clear if this agent owns the lock
            if ($conversation->agent_replying_to === $agentIdentifier) {
                $conversation->clearReplyingAgent();
                Cache::forget($lockKey);
            }
        });
    }

    /**
     * Extend reply lock
     */
    public function extendReplyLock(Conversation $conversation, string $agentIdentifier): bool
    {
        $lockKey = "conversation:{$conversation->id}:replying";
        $currentLock = Cache::get($lockKey);

        if ($currentLock !== $agentIdentifier) {
            return false;
        }

        Cache::put($lockKey, $agentIdentifier, self::LOCK_TTL);
        return true;
    }

    /**
     * Get current replying agent info
     */
    public function getReplyingAgent(Conversation $conversation): ?array
    {
        if (!$conversation->agent_replying_to) {
            return null;
        }

        // Extract agent ID from identifier (format: "agent_123" or "user_123")
        $parts = explode('_', $conversation->agent_replying_to);
        $agentId = end($parts);

        $agent = \App\Models\Agent::find($agentId);

        if (!$agent) {
            return null;
        }

        return [
            'id' => $agent->id,
            'name' => $agent->user->name,
            'identifier' => $conversation->agent_replying_to,
            'started_at' => $conversation->updated_at->toISOString(),
        ];
    }

    /**
     * Force clear stale locks (run via cron/scheduler)
     */
    public function clearStaleLocks(): int
    {
        $staleConversations = Conversation::whereNotNull('agent_replying_to')
            ->where('updated_at', '<', now()->subMinutes(self::LOCK_TTL))
            ->get();

        $count = 0;
        foreach ($staleConversations as $conversation) {
            $lockKey = "conversation:{$conversation->id}:replying";
            
            DB::transaction(function () use ($conversation, $lockKey) {
                $conversation->clearReplyingAgent();
                Cache::forget($lockKey);
            });
            
            $count++;
        }

        return $count;
    }

    /**
     * Check for potential conflicts before sending message
     */
    public function checkBeforeSend(Conversation $conversation, string $agentIdentifier): array
    {
        $result = [
            'can_send' => true,
            'warning' => null,
            'conflict_with' => null,
        ];

        // Check if another agent started replying
        if ($conversation->isBeingRepliedByOtherAgent($agentIdentifier)) {
            $replyingAgent = $this->getReplyingAgent($conversation);
            
            $result['can_send'] = false;
            $result['warning'] = 'Another agent is currently replying to this conversation';
            $result['conflict_with'] = $replyingAgent;
            
            return $result;
        }

        // Check if conversation was recently updated by another agent
        if ($conversation->last_agent_reply_at) {
            $timeSinceLastReply = now()->diffInMinutes($conversation->last_agent_reply_at);
            
            if ($timeSinceLastReply < 1) {
                $result['warning'] = 'This conversation was recently updated by another agent';
            }
        }

        return $result;
    }

    /**
     * Generate unique agent identifier
     */
    public static function generateAgentIdentifier(int $agentId): string
    {
        return "agent_{$agentId}";
    }

    /**
     * Extract agent ID from identifier
     */
    public static function extractAgentId(string $identifier): ?int
    {
        if (str_starts_with($identifier, 'agent_')) {
            return (int) substr($identifier, 6);
        }

        return null;
    }
}
