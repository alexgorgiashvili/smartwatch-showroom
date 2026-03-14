<div class="border-b border-slate-200 bg-white p-4">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-900">Team Status</h3>
        <div class="flex items-center gap-2 text-xs text-slate-500">
            <span class="inline-flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                {{ count($onlineAgents) }} online
            </span>
        </div>
    </div>

    <div class="mt-3 space-y-2">
        @foreach ($filteredAgents as $agent)
            <div class="flex items-center gap-3 rounded-lg p-2 hover:bg-slate-50 transition">
                <div class="relative">
                    <img 
                        src="{{ $agent['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($agent['name']) . '&color=7C3AED&background=F3F0FF' }}" 
                        alt="{{ $agent['name'] }}"
                        class="h-8 w-8 rounded-full"
                    >
                    <span class="absolute -bottom-1 -right-1 h-3 w-3 rounded-full border-2 border-white {{ $this->getStatusColor($agent['status']) }}"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="truncate text-sm font-medium text-slate-900">{{ $agent['name'] }}</p>
                    <p class="text-xs text-slate-500">{{ $this->getStatusLabel($agent['status']) }}</p>
                </div>
                @if ($agent['last_seen_at'])
                    <span class="text-xs text-slate-400">
                        {{ \Carbon\Carbon::parse($agent['last_seen_at'])->diffForHumans() }}
                    </span>
                @endif
            </div>
        @endforeach
    </div>
</div>
