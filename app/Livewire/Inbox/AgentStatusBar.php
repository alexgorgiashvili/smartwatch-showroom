<?php

namespace App\Livewire\Inbox;

use App\Models\Agent;
use App\Services\AgentPresenceService;
use Livewire\Component;
use Livewire\Attributes\Url;

class AgentStatusBar extends Component
{
    #[Url]
    public string $filter = 'all';

    public array $onlineAgents = [];

    public array $agentCounts = [];

    public function mount(AgentPresenceService $agentPresenceService): void
    {
        $this->refreshAgents($agentPresenceService);
        $this->agentCounts = $agentPresenceService->getAgentCountByStatus();
    }

    public function refreshAgents(AgentPresenceService $agentPresenceService): void
    {
        $this->onlineAgents = $agentPresenceService->getOnlineAgents();
        $this->agentCounts = $agentPresenceService->getAgentCountByStatus();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function getFilteredAgentsProperty(): array
    {
        if ($this->filter === 'all') {
            return $this->onlineAgents;
        }

        return collect($this->onlineAgents)
            ->filter(fn($agent) => $agent['status'] === $this->filter)
            ->values()
            ->toArray();
    }

    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'online' => 'bg-emerald-500',
            'busy' => 'bg-amber-500',
            'away' => 'bg-slate-400',
            default => 'bg-slate-300',
        };
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'online' => 'Online',
            'busy' => 'Busy',
            'away' => 'Away',
            'offline' => 'Offline',
            default => $status,
        };
    }

    public function render()
    {
        return view('livewire.inbox.agent-status-bar');
    }
}
