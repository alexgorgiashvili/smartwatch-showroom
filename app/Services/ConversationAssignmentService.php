<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;

class ConversationAssignmentService
{
    public function __construct(
        private AgentPresenceService $agentPresenceService
    ) {}

    /**
     * Assign conversation to an agent
     */
    public function assignToAgent(Conversation $conversation, Agent $agent): bool
    {
        // Check if agent is available
        if (!$this->isAgentAvailable($agent)) {
            return false;
        }

        DB::transaction(function () use ($conversation, $agent) {
            // Unassign from previous agent if any
            if ($conversation->assigned_to) {
                $this->notifyAgentUnassigned($conversation->assigned_to, $conversation);
            }

            // Assign to new agent
            $conversation->assignToAgent($agent);
            $this->notifyAgentAssigned($agent, $conversation);
        });

        return true;
    }

    /**
     * Auto-assign conversation to available agent
     */
    public function autoAssign(Conversation $conversation): ?Agent
    {
        $agent = $this->findBestAvailableAgent();
        
        if (!$agent) {
            return null;
        }

        return $this->assignToAgent($conversation, $agent) ? $agent : null;
    }

    /**
     * Unassign conversation
     */
    public function unassign(Conversation $conversation, string $reason = 'manual'): void
    {
        if (!$conversation->assigned_to) {
            return;
        }

        $previousAgentId = $conversation->assigned_to;
        
        DB::transaction(function () use ($conversation, $reason, $previousAgentId) {
            $conversation->unassign();
            $this->notifyAgentUnassigned($previousAgentId, $conversation, $reason);
        });
    }

    /**
     * Transfer conversation to another agent
     */
    public function transfer(Conversation $conversation, Agent $toAgent, ?Agent $fromAgent = null): bool
    {
        if (!$this->isAgentAvailable($toAgent)) {
            return false;
        }

        DB::transaction(function () use ($conversation, $toAgent, $fromAgent) {
            $previousAgentId = $conversation->assigned_to;
            
            $conversation->assignToAgent($toAgent);
            
            if ($previousAgentId && $previousAgentId !== $toAgent->id) {
                $this->notifyAgentUnassigned($previousAgentId, $conversation, 'transferred');
            }
            
            $this->notifyAgentAssigned($toAgent, $conversation, 'transferred');
            
            if ($fromAgent && $fromAgent->id !== $toAgent->id) {
                $this->notifyTransferComplete($fromAgent, $toAgent, $conversation);
            }
        });

        return true;
    }

    /**
     * Get agent's current assignments count
     */
    public function getAgentAssignmentCount(Agent $agent): int
    {
        return Conversation::where('assigned_to', $agent->id)
            ->whereIn('status', ['active', 'archived'])
            ->count();
    }

    /**
     * Get agent's workload
     */
    public function getAgentWorkload(Agent $agent): array
    {
        $conversations = Conversation::where('assigned_to', $agent->id)
            ->with(['customer', 'latestMessage'])
            ->orderBy('last_message_at', 'desc')
            ->get();

        return [
            'total' => $conversations->count(),
            'unread' => $conversations->sum('unread_count'),
            'active' => $conversations->where('status', 'active')->count(),
            'archived' => $conversations->where('status', 'archived')->count(),
            'conversations' => $conversations->take(10), // Recent 10
        ];
    }

    /**
     * Check if agent is available for assignment
     */
    private function isAgentAvailable(Agent $agent): bool
    {
        return $agent->status !== 'offline' && $agent->isOnline();
    }

    /**
     * Find best available agent (least busy)
     */
    private function findBestAvailableAgent(): ?Agent
    {
        $onlineAgents = Agent::where('status', '!=', 'offline')
            ->where('is_online', true)
            ->get();

        if ($onlineAgents->isEmpty()) {
            return null;
        }

        // Get assignment counts for all online agents
        $assignmentCounts = Conversation::select('assigned_to', DB::raw('count(*) as count'))
            ->whereIn('assigned_to', $onlineAgents->pluck('id'))
            ->whereIn('status', ['active', 'archived'])
            ->groupBy('assigned_to')
            ->pluck('count', 'assigned_to')
            ->toArray();

        // Find agent with least assignments
        return $onlineAgents->sortBy(function ($agent) use ($assignmentCounts) {
            return $assignmentCounts[$agent->id] ?? 0;
        })->first();
    }

    /**
     * Notify agent of assignment
     */
    private function notifyAgentAssigned(Agent $agent, Conversation $conversation, string $type = 'assigned'): void
    {
        // TODO: Implement real-time notification via Echo
        broadcast(new \App\Events\ConversationAssigned($agent, $conversation, $type));
    }

    /**
     * Notify agent of unassignment
     */
    private function notifyAgentUnassigned(int $agentId, Conversation $conversation, string $reason = 'unassigned'): void
    {
        // TODO: Implement real-time notification via Echo
        broadcast(new \App\Events\ConversationUnassigned($agentId, $conversation, $reason));
    }

    /**
     * Notify transfer complete
     */
    private function notifyTransferComplete(Agent $fromAgent, Agent $toAgent, Conversation $conversation): void
    {
        // TODO: Implement real-time notification via Echo
        broadcast(new \App\Events\ConversationTransferred($fromAgent, $toAgent, $conversation));
    }
}
