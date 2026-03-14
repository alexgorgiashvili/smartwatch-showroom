<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AgentPresenceService
{
    private const CACHE_KEY = 'agents:online';
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Get all online agents
     */
    public function getOnlineAgents(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Agent::with('user')
                ->where(function ($query) {
                    $query->where('is_online', true)
                          ->orWhere('last_seen_at', '>', now()->subMinutes(5));
                })
                ->get()
                ->map(function ($agent) {
                    return [
                        'id' => $agent->id,
                        'name' => $agent->user->name,
                        'status' => $agent->status,
                        'last_seen_at' => $agent->last_seen_at?->toISOString(),
                        'avatar' => $agent->user->avatar_url ?? null,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Update agent status
     */
    public function updateAgentStatus(Agent $agent, string $status): void
    {
        $agent->setStatus($status);
        $this->clearCache();
    }

    /**
     * Mark agent as online
     */
    public function markAgentOnline(Agent $agent): void
    {
        $agent->markAsOnline();
        $this->clearCache();
    }

    /**
     * Mark agent as offline
     */
    public function markAgentOffline(Agent $agent): void
    {
        $agent->markAsOffline();
        $this->clearCache();
    }

    /**
     * Get agent by user ID
     */
    public function getAgentByUserId(int $userId): ?Agent
    {
        return Agent::where('user_id', $userId)->first();
    }

    /**
     * Create or get agent for user
     */
    public function getOrCreateAgentForUser(int $userId): Agent
    {
        return Agent::firstOrCreate(
            ['user_id' => $userId],
            ['status' => 'offline']
        );
    }

    /**
     * Check if agent is online
     */
    public function isAgentOnline(int $agentId): bool
    {
        $onlineAgents = $this->getOnlineAgents();
        
        return collect($onlineAgents)->contains('id', $agentId);
    }

    /**
     * Get agent count by status
     */
    public function getAgentCountByStatus(): array
    {
        return Cache::remember('agents:count_by_status', self::CACHE_TTL, function () {
            return [
                'online' => Agent::where('status', 'online')->count(),
                'busy' => Agent::where('status', 'busy')->count(),
                'away' => Agent::where('status', 'away')->count(),
                'offline' => Agent::where('status', 'offline')->count(),
            ];
        });
    }

    /**
     * Clear the online agents cache
     */
    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('agents:count_by_status');
    }

    /**
     * Heartbeat to keep agent online
     */
    public function heartbeat(Agent $agent): void
    {
        DB::table('agents')
            ->where('id', $agent->id)
            ->update([
                'last_seen_at' => now(),
                'is_online' => true,
            ]);

        // Only clear cache if status might have changed
        if (!$agent->is_online) {
            $this->clearCache();
        }
    }

    /**
     * Cleanup offline agents (run via cron/scheduler)
     */
    public function cleanupOfflineAgents(): int
    {
        $count = Agent::where('is_online', true)
            ->where('last_seen_at', '<', now()->subMinutes(10))
            ->update([
                'is_online' => false,
                'status' => 'offline',
            ]);

        if ($count > 0) {
            $this->clearCache();
        }

        return $count;
    }
}
