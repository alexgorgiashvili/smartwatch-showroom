{{-- Compact AI Controls - Collapsible --}}
<div x-data="{ expanded: false }" class="space-y-2">
    @if ($conversationId && $this->conversation)
        {{-- Compact Toggle Bar --}}
        <div class="flex items-center gap-2">
            <button
                @click="expanded = !expanded"
                class="flex-1 flex items-center justify-between rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-sm font-medium text-violet-700 transition hover:bg-violet-100"
            >
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-m-sparkles" class="h-4 w-4" />
                    <span>AI Assistant</span>
                </div>
                <svg class="h-4 w-4 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            {{-- AI Toggle Switch --}}
            <button
                wire:click="toggleAI"
                class="relative inline-flex h-8 w-14 items-center rounded-full transition-colors"
                @class([
                    'bg-violet-600' => $this->conversation->is_ai_enabled ?? false,
                    'bg-slate-300' => !($this->conversation->is_ai_enabled ?? false),
                ])
            >
                <span
                    class="inline-block h-6 w-6 transform rounded-full bg-white transition-transform shadow-sm"
                    @class([
                        'translate-x-7' => $this->conversation->is_ai_enabled ?? false,
                        'translate-x-1' => !($this->conversation->is_ai_enabled ?? false),
                    ])
                ></span>
            </button>
        </div>

        {{-- Expandable AI Panel --}}
        <div x-show="expanded" x-collapse class="space-y-2">
            {{-- AI Suggestions --}}
            @if (!empty($suggestions))
                <div class="rounded-lg border border-violet-200 bg-violet-50 p-3">
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-violet-700">
                        <x-filament::icon icon="heroicon-m-sparkles" class="h-4 w-4" />
                        Suggestions
                    </div>
                    <div class="space-y-2">
                        @foreach ($suggestions as $suggestion)
                            <button
                                type="button"
                                wire:click="useSuggestion('{{ \Illuminate\Support\Str::replace("'", "\\'", $suggestion) }}')"
                                class="w-full rounded-lg border border-violet-200 bg-white p-2 text-left text-sm text-slate-700 transition hover:border-violet-300 hover:bg-violet-100"
                            >
                                {{ $suggestion }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Generate Button --}}
            <button
                wire:click="generateSuggestion"
                wire:loading.attr="disabled"
                wire:target="generateSuggestion"
                class="w-full rounded-lg border border-violet-200 bg-white px-4 py-2 text-sm font-medium text-violet-700 transition hover:border-violet-300 hover:bg-violet-50"
            >
                <span wire:loading.remove wire:target="generateSuggestion">
                    <x-filament::icon icon="heroicon-m-sparkles" class="h-4 w-4 inline mr-2" />
                    Generate Suggestions
                </span>
                <span wire:loading wire:target="generateSuggestion">
                    <x-filament::icon icon="heroicon-m-arrow-path" class="h-4 w-4 inline mr-2 animate-spin" />
                    Generating...
                </span>
            </button>
        </div>
    @else
        {{-- Empty State --}}
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center">
            <x-filament::icon icon="heroicon-m-cpu-chip" class="h-6 w-6 text-slate-400 mx-auto" />
            <p class="mt-1 text-xs text-slate-500">Select conversation</p>
        </div>
    @endif
</div>
