<style>
        .platform-badge {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .platform-home { background: #7c3aed; color: white; }
        .platform-messenger { background: #0084ff; color: white; }
        .platform-whatsapp { background: #25d366; color: white; }
        .platform-instagram { background: #e4405f; color: white; }
        .platform-facebook { background: #1877f2; color: white; }

        .agent-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid white;
            margin-left: -8px;
            transition: all 0.2s;
        }

        .agent-avatar:first-child {
            margin-left: 0;
        }

        .agent-avatar:hover {
            transform: scale(1.1);
            z-index: 10;
        }

        .typing-indicator {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            padding: 4px 8px;
            background: #f3f4f6;
            border-radius: 12px;
            font-size: 11px;
            color: #6b7280;
        }

        .typing-dot {
            width: 4px;
            height: 4px;
            background: #9ca3af;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }

        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-4px); }
        }
    </style>

    <div class="flex h-full min-h-[78vh] flex-col" wire:poll.visible.12s>
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
                    wire:model.live.debounce.500ms="search"
                    placeholder="Search customer or message"
                    class="w-full rounded-2xl border border-slate-200 bg-white py-3 pl-10 pr-4 text-sm text-slate-900 shadow-sm outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                >
            </label>

            <div class="grid grid-cols-2 gap-3">
                <select
                    wire:model.live="platformFilter"
                    class="rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                >
                    <option value="all">All platforms</option>
                    <option value="home">Home</option>
                    <option value="messenger">Messenger</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="instagram">Instagram</option>
                    <option value="facebook">Facebook</option>
                </select>

                <select
                    wire:model.live="statusFilter"
                    class="rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                >
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
                        wire:click="$set('platformFilter', '{{ $platformValue }}')"
                        @class([
                            'rounded-xl px-2 py-2 transition',
                            'bg-white text-slate-900 shadow-sm' => $platformFilter === $platformValue,
                        ])
                    >
                        {{ $platformLabel }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="kw-scroll flex-1 overflow-y-auto px-3 py-3">
        <div class="space-y-2">
            @forelse ($conversations as $conversation)
                @php
                    $lastMessage = $conversation->latestMessage;
                    $isSelected = $selectedConversationId === $conversation->id;
                @endphp

                <button
                    type="button"
                    wire:click="selectConversation({{ $conversation->id }})"
                    @class([
                        'w-full rounded-2xl border px-3 py-3 sm:px-4 text-left transition-all duration-200 group',
                        'border-violet-400 bg-gradient-to-br from-violet-50 to-violet-100 shadow-md ring-2 ring-violet-200' => $isSelected,
                        'border-slate-200 bg-white hover:border-violet-300 hover:bg-violet-50/50 hover:shadow-sm' => ! $isSelected,
                    ])
                >
                    <div class="flex items-start gap-2.5 sm:gap-3">
                        <div class="relative flex h-10 w-10 sm:h-11 sm:w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-100 to-purple-100 text-sm font-bold text-violet-700 shadow-sm">
                            {{ \Illuminate\Support\Str::of($conversation->customer->name ?? '?')->substr(0, 1)->upper() }}

                            @if ($conversation->assignedAgent)
                                <img
                                    src="{{ $conversation->assignedAgent->user->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($conversation->assignedAgent->user->name) . '&color=7C3AED&background=F3F0FF' }}"
                                    alt="{{ $conversation->assignedAgent->user->name }}"
                                    loading="lazy"
                                    class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full border-2 border-white shadow-sm"
                                >
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="truncate text-sm font-semibold text-slate-900 group-hover:text-violet-700 transition-colors">{{ $conversation->customer->name }}</p>
                                        @if ($conversation->unread_count > 0)
                                            <span class="inline-flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-violet-600 text-[10px] font-bold text-white shadow-sm animate-pulse">
                                                {{ $conversation->unread_count }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-xs text-slate-500 leading-relaxed">
                                        {{ $lastMessage?->content ?: 'No messages yet' }}
                                    </p>
                                </div>

                                <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                                    <span class="platform-badge platform-{{ $conversation->platform }} rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide shadow-sm">
                                        {{ $conversation->platform }}
                                    </span>
                                    <span class="text-[10px] text-slate-400">
                                        {{ optional($conversation->last_message_at)->diffForHumans() }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-2.5 flex items-center gap-1.5 text-xs flex-wrap">
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize',
                                    'bg-emerald-100 text-emerald-700' => $conversation->status === 'active',
                                    'bg-amber-100 text-amber-700' => $conversation->status === 'archived',
                                    'bg-slate-200 text-slate-700' => $conversation->status === 'closed',
                                ])>
                                    <span class="w-1.5 h-1.5 rounded-full @if($conversation->status === 'active') bg-emerald-500 @elseif($conversation->status === 'archived') bg-amber-500 @else bg-slate-500 @endif"></span>
                                    {{ $conversation->status }}
                                </span>

                                @if ($conversation->assignedAgent)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-700">
                                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                                        </svg>
                                        {{ $conversation->assignedAgent->user->name }}
                                    </span>
                                @endif

                                @if ($conversation->is_ai_enabled)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-700" title="AI Enabled">
                                        <x-filament::icon icon="heroicon-m-cpu-chip" class="h-2.5 w-2.5" />
                                        AI
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </button>
            @empty
                <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-gradient-to-br from-slate-50 to-white px-6 py-12 text-center">
                    <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <p class="text-sm font-medium text-slate-600">No conversations found</p>
                    <p class="mt-1 text-xs text-slate-400">Try adjusting your filters</p>
                </div>
            @endforelse
        </div>
    </div>

    @if ($conversations->hasPages())
        <div class="border-t border-slate-200 px-5 py-4">
            {{ $conversations->links() }}
        </div>
    @endif
</div>
