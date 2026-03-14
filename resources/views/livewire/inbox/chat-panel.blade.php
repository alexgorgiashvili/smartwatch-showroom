<div class="flex h-full min-h-[78vh] flex-col">
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

        .typing-indicator {
            display: inline-flex;
            align-items: center;
            gap: 2px;
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
    @if (! $conversation)
        <div class="flex h-full flex-1 items-center justify-center bg-[radial-gradient(circle_at_top,_rgba(124,58,237,0.12),_transparent_38%),linear-gradient(180deg,_#ffffff,_#f8fafc)] px-6 text-center">
            <div>
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-3xl bg-violet-100 text-violet-700">
                    <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-10 w-10" />
                </div>
                <h3 class="mt-6 text-xl font-semibold text-slate-900">Select a conversation</h3>
                <p class="mt-2 max-w-md text-sm text-slate-500">
                    Keep the current inbox workflow, but manage replies, AI suggestions, and status changes inside Filament.
                </p>
            </div>
        </div>
    @else
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        wire:click="$dispatch('conversation-closed')"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 lg:hidden"
                    >
                        <x-filament::icon icon="heroicon-o-arrow-left" class="h-5 w-5" />
                    </button>

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

                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-900">{{ $conversation->customer->name }}</h2>
                            <span class="platform-badge platform-{{ $conversation->platform }} rounded-full px-2 py-1">
                                {{ $conversation->platform }}
                            </span>
                            @if ($conversation->is_ai_enabled)
                                <span class="text-violet-600" title="AI Enabled">
                                    <x-filament::icon icon="heroicon-m-cpu-chip" class="h-4 w-4" />
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $conversation->getPlatformLabel() }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="toggleAi"
                        @class([
                            'inline-flex items-center gap-2 rounded-2xl border px-4 py-2 text-sm font-medium transition',
                            'border-emerald-200 bg-emerald-50 text-emerald-700' => $conversation->ai_enabled,
                            'border-slate-200 bg-white text-slate-600' => ! $conversation->ai_enabled,
                        ])
                    >
                        <x-filament::icon icon="heroicon-o-cpu-chip" class="h-4 w-4" />
                        {{ $conversation->ai_enabled ? 'Auto Reply On' : 'Auto Reply Off' }}
                    </button>

                    @foreach (['active' => 'Active', 'archived' => 'Archived', 'closed' => 'Closed'] as $statusValue => $statusLabel)
                        <button
                            type="button"
                            wire:click="updateStatus('{{ $statusValue }}')"
                            @class([
                                'rounded-2xl border px-3 py-2 text-sm font-medium transition',
                                'border-violet-300 bg-violet-50 text-violet-700' => $conversation->status === $statusValue,
                                'border-slate-200 bg-white text-slate-600' => $conversation->status !== $statusValue,
                            ])
                        >
                            {{ $statusLabel }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="kw-scroll flex-1 overflow-y-auto bg-[linear-gradient(180deg,_#ffffff,_#f8fafc)] px-5 py-5" wire:poll.visible.8s>
            <div class="space-y-4">
                @foreach ($messages as $message)
                    @php
                        $isAdmin = $message->sender_type === 'admin';
                        $isBot = $message->sender_type === 'bot';
                    @endphp

                    <div @class([
                        'flex',
                        'justify-end' => $isAdmin,
                        'justify-start' => ! $isAdmin,
                    ])>
                        <div class="max-w-[85%] sm:max-w-[75%]">
                            <div class="mb-1 flex items-center gap-2 text-xs text-slate-400 {{ $isAdmin ? 'justify-end' : 'justify-start' }}">
                                <span>{{ $message->sender_name }}</span>
                                @if ($isBot)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">Bot</span>
                                @endif
                                <span>{{ optional($message->created_at)->format('H:i') }}</span>
                            </div>

                            <div @class([
                                'rounded-[22px] px-4 py-3 text-sm leading-6 shadow-sm',
                                'bg-violet-600 text-white' => $isAdmin,
                                'bg-emerald-500 text-white' => $isBot,
                                'bg-slate-100 text-slate-800' => ! $isAdmin && ! $isBot,
                            ])>
                                @if ($isBot)
                                    <div class="prose prose-sm prose-invert max-w-none">
                                        {!! \Illuminate\Support\Str::markdown($message->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                    </div>
                                @else
                                    <p class="m-0 whitespace-pre-wrap break-words">{{ $message->content }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Typing Indicator -->
            @if ($typingAgent)
                <div class="flex justify-start">
                    <div class="max-w-[85%] sm:max-w-[75%]">
                        <div class="mb-1 flex items-center gap-2 text-xs text-slate-400">
                            <span>{{ $typingAgent['name'] }}</span>
                        </div>
                        <div class="rounded-[22px] bg-slate-100 px-4 py-3">
                            <div class="typing-indicator">
                                <span class="typing-dot"></span>
                                <span class="typing-dot"></span>
                                <span class="typing-dot"></span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="border-t border-slate-200 bg-white px-5 py-4">
            <livewire:inbox.reply-form :conversationId="$conversation->id" :key="'reply-form-' . $conversation->id" />
        </div>
    @endif
</div>
