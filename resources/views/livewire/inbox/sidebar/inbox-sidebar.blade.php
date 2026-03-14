<div class="h-full flex flex-col">
    {{-- Header --}}
    <div class="p-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Inbox</h2>
    </div>

    {{-- Search Bar --}}
    <div class="p-4 border-b border-gray-200">
        <div class="relative">
            <x-filament::icon 
                icon="heroicon-o-magnifying-glass" 
                class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" 
            />
            <input 
                type="text" 
                wire:model.live.debounce.300ms="searchQuery"
                placeholder="Search conversations..." 
                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
            />
        </div>
    </div>

    {{-- Platform Filters --}}
    <div class="p-4 border-b border-gray-200">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Platform</h3>
        <div class="space-y-1">
            <button 
                wire:click="applyPlatformFilter('all')"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 transition {{ $platformFilter === 'all' ? 'bg-violet-50 text-violet-700' : 'text-gray-700' }}"
            >
                <x-filament::icon icon="heroicon-o-inbox-stack" class="w-5 h-5" />
                <span class="text-sm font-medium">All Messages</span>
            </button>
            
            <button 
                wire:click="applyPlatformFilter('instagram')"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 transition {{ $platformFilter === 'instagram' ? 'bg-violet-50 text-violet-700' : 'text-gray-700' }}"
            >
                <x-filament::icon icon="heroicon-o-camera" class="w-5 h-5" />
                <span class="text-sm font-medium">Instagram</span>
            </button>
            
            <button 
                wire:click="applyPlatformFilter('facebook')"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 transition {{ $platformFilter === 'facebook' ? 'bg-violet-50 text-violet-700' : 'text-gray-700' }}"
            >
                <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="w-5 h-5" />
                <span class="text-sm font-medium">Messenger</span>
            </button>
            
            <button 
                wire:click="applyPlatformFilter('whatsapp')"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 transition {{ $platformFilter === 'whatsapp' ? 'bg-violet-50 text-violet-700' : 'text-gray-700' }}"
            >
                <x-filament::icon icon="heroicon-o-device-phone-mobile" class="w-5 h-5" />
                <span class="text-sm font-medium">WhatsApp</span>
            </button>
        </div>
    </div>

    {{-- Status Filters --}}
    <div class="p-4 border-b border-gray-200">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Status</h3>
        <div class="space-y-1">
            <button 
                wire:click="applyStatusFilter('all')"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 transition {{ $statusFilter === 'all' ? 'bg-violet-50 text-violet-700' : 'text-gray-700' }}"
            >
                <span class="text-sm font-medium">All</span>
            </button>
            
            <button 
                wire:click="applyStatusFilter('active')"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 transition {{ $statusFilter === 'active' ? 'bg-violet-50 text-violet-700' : 'text-gray-700' }}"
            >
                <span class="text-sm font-medium">Active</span>
            </button>
            
            <button 
                wire:click="applyStatusFilter('archived')"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 transition {{ $statusFilter === 'archived' ? 'bg-violet-50 text-violet-700' : 'text-gray-700' }}"
            >
                <span class="text-sm font-medium">Archived</span>
            </button>
        </div>
    </div>

    {{-- Clear Filters --}}
    @if($platformFilter !== 'all' || $statusFilter !== 'all' || $searchQuery !== '')
    <div class="p-4">
        <button 
            wire:click="clearFilters"
            class="w-full px-4 py-2 text-sm font-medium text-violet-700 bg-violet-50 rounded-lg hover:bg-violet-100 transition"
        >
            Clear All Filters
        </button>
    </div>
    @endif
</div>
