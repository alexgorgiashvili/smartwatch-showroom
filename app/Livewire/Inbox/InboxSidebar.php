<?php

namespace App\Livewire\Inbox;

use Livewire\Attributes\Url;
use Livewire\Component;

class InboxSidebar extends Component
{
    #[Url(as: 'platform')]
    public string $platformFilter = 'all';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'q')]
    public string $searchQuery = '';

    public function applyPlatformFilter(string $platform): void
    {
        $this->platformFilter = $platform;
        $this->dispatch('filters-updated', [
            'platform' => $this->platformFilter,
            'status' => $this->statusFilter,
            'search' => $this->searchQuery,
        ]);
    }

    public function applyStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->dispatch('filters-updated', [
            'platform' => $this->platformFilter,
            'status' => $this->statusFilter,
            'search' => $this->searchQuery,
        ]);
    }

    public function updatedSearchQuery(): void
    {
        $this->dispatch('filters-updated', [
            'platform' => $this->platformFilter,
            'status' => $this->statusFilter,
            'search' => $this->searchQuery,
        ]);
    }

    public function clearFilters(): void
    {
        $this->platformFilter = 'all';
        $this->statusFilter = 'all';
        $this->searchQuery = '';

        $this->dispatch('filters-updated', [
            'platform' => 'all',
            'status' => 'all',
            'search' => '',
        ]);
    }

    public function render()
    {
        return view('livewire.inbox.inbox-sidebar');
    }
}
