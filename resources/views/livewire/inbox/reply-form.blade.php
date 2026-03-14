<div class="space-y-3">
    @if ($typingWarning)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-3">
            <div class="flex items-center gap-2 text-sm text-amber-800">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4" />
                {{ $typingWarning }}
            </div>
        </div>
    @endif

    @if (! empty($suggestions))
        <div class="rounded-2xl border border-violet-200 bg-violet-50 p-3">
            <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-violet-700">
                <x-filament::icon icon="heroicon-m-sparkles" class="h-4 w-4" />
                AI suggestions
            </div>

            <div class="space-y-2">
                @foreach ($suggestions as $suggestion)
                    <button
                        type="button"
                        wire:click="useSuggestion('{{ \Illuminate\Support\Str::replace("'", "\\'", $suggestion) }}')"
                        class="w-full rounded-xl border border-violet-200 bg-white p-3 text-left text-sm text-slate-700 transition hover:border-violet-300 hover:bg-violet-100"
                    >
                        {{ $suggestion }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <form wire:submit="sendMessage" class="space-y-3">
        <div class="relative">
            <textarea
                wire:model="message"
                wire:keydown.enter.exact.prevent="sendMessage"
                rows="3"
                placeholder="Type your reply... (Press Enter to send, Shift+Enter for new line)"
                class="w-full rounded-[1.4rem] border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-violet-400 focus:bg-white focus:ring-2 focus:ring-violet-100"
            ></textarea>
            <div class="mt-2 flex justify-between text-xs text-slate-400">
                <span>{{ mb_strlen($message) }}/5000</span>
                @if ($isTyping)
                    <span class="flex items-center gap-1">
                        <span class="typing-indicator">
                            <span class="typing-dot"></span>
                            <span class="typing-dot"></span>
                            <span class="typing-dot"></span>
                        </span>
                        You're typing...
                    </span>
                @endif
            </div>
            @error('message')
                <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="$set('message', '')"
                class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
            >
                Clear
            </button>

            <button
                type="button"
                wire:click="suggestAi"
                wire:loading.attr="disabled"
                wire:target="suggestAi"
                class="inline-flex items-center gap-2 rounded-2xl border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-medium text-violet-700 transition hover:border-violet-300 hover:bg-violet-100"
            >
                <x-filament::icon icon="heroicon-m-sparkles" class="h-4 w-4" />
                <span wire:loading.remove wire:target="suggestAi">AI Suggest</span>
                <span wire:loading wire:target="suggestAi">Generating...</span>
            </button>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="sendMessage"
                class="ml-auto inline-flex items-center gap-2 rounded-2xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-700"
            >
                <x-filament::icon icon="heroicon-m-paper-airplane" class="h-4 w-4" />
                <span wire:loading.remove wire:target="sendMessage">Send</span>
                <span wire:loading wire:target="sendMessage">Sending...</span>
            </button>
        </div>
    </form>
</div>
