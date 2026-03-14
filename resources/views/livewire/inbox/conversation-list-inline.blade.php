@php
    // Initialize variables
    $search = $search ?? '';
    $platformFilter = $platformFilter ?? 'all';
    $statusFilter = $statusFilter ?? 'all';

    // Get conversations from database directly
    $conversations = \App\Models\Conversation::with(['customer', 'assignedAgent.user'])
        ->when($search, function ($query, $search) {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            })
            ->orWhere('last_message_content', 'like', "%{$search}%");
        })
        ->when($platformFilter && $platformFilter !== 'all', function ($query, $platformFilter) {
            $query->where('platform', $platformFilter);
        })
        ->when($statusFilter && $statusFilter !== 'all', function ($query, $statusFilter) {
            $query->where('status', $statusFilter);
        })
        ->orderByDesc('last_message_at')
        ->paginate(20);
@endphp

<div class="flex h-full min-h-[78vh] flex-col">
    <div class="border-b border-slate-200 px-5 py-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Inbox</h2>
                <p class="text-sm text-slate-500">Omnichannel conversations</p>
            </div>
            <div class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-500">
                {{ $conversations->total() }} total
            </div>
        </div>

        <div class="mt-4 space-y-3">
            <label class="relative block">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-4 w-4" />
                </span>
                <input
                    type="text"
                    placeholder="Search customer or message"
                    class="w-full rounded-2xl border border-slate-200 bg-white py-3 pl-10 pr-4 text-sm text-slate-900 shadow-sm outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                >
            </label>

            <div class="grid grid-cols-2 gap-3">
                <select class="rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100">
                    <option value="all">All platforms</option>
                    <option value="home">Home</option>
                    <option value="messenger">Messenger</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="instagram">Instagram</option>
                    <option value="facebook">Facebook</option>
                </select>

                <select class="rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100">
                    <option value="all">All statuses</option>
                    <option value="active">Active</option>
                    <option value="archived">Archived</option>
                    <option value="closed">Closed</option>
                </select>
            </div>

            <div class="grid grid-cols-5 gap-2 rounded-2xl bg-slate-100 p-1 text-xs font-medium text-slate-500">
                @foreach ([
                    'all' => 'All',
                    'home' => 'Home',
                    'messenger' => 'Messenger',
                    'whatsapp' => 'WhatsApp',
                    'instagram' => 'Instagram',
                ] as $platformValue => $platformLabel)
                    <button
                        type="button"
                        class="rounded-xl px-2 py-2 transition bg-white text-slate-900 shadow-sm"
                    >
                        {{ $platformLabel }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="kw-scroll flex-1 overflow-y-auto px-3 py-3">
        @if ($conversations->total() > 0)
            <div class="space-y-2">
                @foreach ($conversations as $conversation)
                    @php
                        $isSelected = $selectedConversationId == $conversation->id;
                    @endphp

                    <button
                        wire:click="$dispatch('conversation-selected', {{ $conversation->id }})"
                        class="w-full rounded-2xl border p-4 text-left transition hover:border-violet-300 hover:bg-violet-50 {{ $isSelected ? 'border-violet-300 bg-violet-50' : 'border-slate-200 bg-white' }}"
                    >
                        <div class="flex items-start gap-3">
                            <div class="relative">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-sm font-semibold text-slate-700">
                                    {{ \Illuminate\Support\Str::of($conversation->customer->name ?? '?')->substr(0, 1)->upper() }}
                                </div>
                                @if ($conversation->assignedAgent)
                                    <img
                                        src="{{ $conversation->assignedAgent->user->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($conversation->assignedAgent->user->name) . '&color=7C3AED&background=F3F0FF' }}"
                                        alt="{{ $conversation->assignedAgent->user->name }}"
                                        class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full border-2 border-white"
                                        title="{{ $conversation->assignedAgent->user->name }}"
                                    >
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="truncate text-sm font-semibold text-slate-900">{{ $conversation->customer->name }}</h3>
                                    <span class="platform-badge platform-{{ $conversation->platform }} rounded-full px-2 py-1">
                                        {{ $conversation->platform }}
                                    </span>
                                    @if ($conversation->is_ai_enabled)
                                        <span class="text-violet-600" title="AI Enabled">
                                            <x-filament::icon icon="heroicon-m-cpu-chip" class="h-4 w-4" />
                                        </span>
                                    @endif
                                </div>

                                <p class="mt-1 truncate text-sm text-slate-500">
                                    {{ $conversation->last_message_content ?? 'No messages yet' }}
                                </p>

                                <div class="mt-2 flex items-center justify-between">
                                    <span class="rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide
                                        {{ $conversation->status === 'active' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                        {{ $conversation->status === 'archived' ? 'bg-amber-100 text-amber-700' : '' }}
                                        {{ $conversation->status === 'closed' ? 'bg-slate-200 text-slate-700' : '' }}
                                    ">
                                        {{ $conversation->status }}
                                    </span>
                                    <span class="text-xs text-slate-400">
                                        {{ $conversation->last_message_at ? \Carbon\Carbon::parse($conversation->last_message_at)->diffForHumans() : 'Never' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>

            @if ($conversations->hasPages())
                <div class="mt-4 flex justify-center">
                    {{ $conversations->render() }}
                </div>
            @endif
        @else
            <div class="flex h-full items-center justify-center">
                <div class="text-center">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-3xl bg-slate-100 text-slate-400">
                        <x-filament::icon icon="heroicon-o-inbox" class="h-10 w-10" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">No conversations</h3>
                    <p class="mt-2 text-sm text-slate-500">No conversations found matching your criteria.</p>
                </div>
            </div>
        @endif
    </div>
</div>
