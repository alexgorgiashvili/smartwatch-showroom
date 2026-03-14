<div class="flex h-full flex-col" wire:key="customer-panel-{{ $conversationId }}">
    @if ($this->conversation)
        <!-- Customer Header -->
        <div class="border-b border-slate-200 p-4">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center text-white font-semibold">
                    {{ \Illuminate\Support\Str::of($this->conversation->customer->name ?? '?')->substr(0, 1)->upper() }}
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="truncate text-sm font-semibold text-slate-900">{{ $this->conversation->customer->name }}</h3>
                    <p class="truncate text-xs text-slate-500">{{ $this->conversation->platform }}</p>
                </div>
                @if ($this->conversation->assignedAgent)
                    <div class="relative">
                        <img
                            src="{{ $this->conversation->assignedAgent->user->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($this->conversation->assignedAgent->user->name) . '&color=7C3AED&background=F3F0FF' }}"
                            alt="{{ $this->conversation->assignedAgent->user->name }}"
                            class="h-8 w-8 rounded-full border-2 border-white"
                            title="{{ $this->conversation->assignedAgent->user->name }}"
                        >
                    </div>
                @endif
            </div>
        </div>

        <!-- Customer Info -->
        <div class="border-b border-slate-200 p-4">
            <div class="space-y-2 text-sm">
                @if ($this->conversation->customer->email)
                    <div class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-envelope" class="h-4 w-4 text-slate-400" />
                        <span class="text-slate-600">{{ $this->conversation->customer->email }}</span>
                    </div>
                @endif
                @if ($this->conversation->customer->phone)
                    <div class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-phone" class="h-4 w-4 text-slate-400" />
                        <span class="text-slate-600">{{ $this->conversation->customer->phone }}</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="border-b border-slate-200 p-4">
            <div class="grid grid-cols-2 gap-2">
                <button
                    wire:click="assignToMe"
                    class="rounded-lg bg-violet-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-violet-700"
                >
                    Assign to me
                </button>
                <button
                    wire:click="toggleAI"
                    @class([
                        'rounded-lg px-3 py-2 text-xs font-medium transition',
                        'bg-violet-600 text-white hover:bg-violet-700' => $this->conversation->is_ai_enabled,
                        'bg-slate-100 text-slate-700 hover:bg-slate-200' => !$this->conversation->is_ai_enabled,
                    ])
                >
                    {{ $this->conversation->is_ai_enabled ? 'AI On' : 'AI Off' }}
                </button>
                <button
                    wire:click="archiveConversation"
                    class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-200"
                >
                    Archive
                </button>
                <button
                    wire:click="closeConversation"
                    class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-200"
                >
                    Close
                </button>
            </div>
        </div>

        <!-- Content Scroll Area -->
        <div class="flex-1 overflow-y-auto">
            <!-- Internal Notes -->
            <div class="border-b border-slate-200 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-xs font-semibold text-slate-900">Internal Notes</h4>
                    @if (!$isEditingNotes)
                        <button
                            wire:click="$set('isEditingNotes', true)"
                            class="text-violet-600 hover:text-violet-700"
                        >
                            <x-filament::icon icon="heroicon-m-pencil" class="h-4 w-4" />
                        </button>
                    @endif
                </div>

                @if ($isEditingNotes)
                    <textarea
                        wire:model.live="internalNotes"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                        rows="4"
                        placeholder="Add internal notes..."
                    ></textarea>
                    <div class="mt-2 flex gap-2">
                        <button
                            wire:click="saveInternalNotes"
                            class="rounded bg-violet-600 px-3 py-1 text-xs font-medium text-white hover:bg-violet-700"
                        >
                            Save
                        </button>
                        <button
                            wire:click="$set('isEditingNotes', false)"
                            class="rounded bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200"
                        >
                            Cancel
                        </button>
                    </div>
                @else
                    <p class="text-sm text-slate-600">
                        {{ $internalNotes ?: 'No internal notes added yet.' }}
                    </p>
                @endif
            </div>

            <!-- Recent Orders -->
            <div class="border-b border-slate-200 p-4">
                <h4 class="mb-3 text-xs font-semibold text-slate-900">Recent Orders</h4>
                @if ($recentOrders->count() > 0)
                    <div class="space-y-2">
                        @foreach ($recentOrders as $order)
                            <div class="rounded-lg bg-slate-50 p-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">#{{ $order->id }}</p>
                                        <p class="text-xs text-slate-500">{{ $order->created_at->format('M j, Y') }}</p>
                                    </div>
                                    <span class="text-sm font-semibold text-slate-900">
                                        ${{ number_format($order->total_amount, 2) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500">No recent orders</p>
                @endif
            </div>

            <!-- Previous Conversations -->
            <div class="p-4">
                <h4 class="mb-3 text-xs font-semibold text-slate-900">Previous Conversations</h4>
                @if ($previousConversations->count() > 0)
                    <div class="space-y-2">
                        @foreach ($previousConversations as $prevConv)
                            <button class="w-full rounded-lg bg-slate-50 p-3 text-left transition hover:bg-slate-100">
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-900 truncate">
                                            {{ $prevConv->latestMessage?->content ?: 'No messages' }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{ $prevConv->platform }} · {{ $prevConv->last_message_at->diffForHumans() }}
                                        </p>
                                    </div>
                                    @if ($prevConv->assignedAgent)
                                        <img
                                            src="{{ $prevConv->assignedAgent->user->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($prevConv->assignedAgent->user->name) . '&color=7C3AED&background=F3F0FF' }}"
                                            alt="{{ $prevConv->assignedAgent->user->name }}"
                                            class="h-6 w-6 rounded-full"
                                        >
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500">No previous conversations</p>
                @endif
            </div>
        </div>
    @else
        <!-- Empty State -->
        <div class="flex h-full items-center justify-center p-8">
            <div class="text-center">
                <x-filament::icon icon="heroicon-m-chat-bubble-left-right" class="mx-auto h-12 w-12 text-slate-300" />
                <h3 class="mt-4 text-sm font-semibold text-slate-900">No conversation selected</h3>
                <p class="mt-2 text-xs text-slate-500">Select a conversation to view customer details</p>
            </div>
        </div>
    @endif
</div>
