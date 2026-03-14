<x-filament-panels::page>
    <style>
        .dark .fi-section-content-ctn {
            background-color: transparent !important;
        }
    </style>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Chat Interface (Left Column) -->
        <div class="lg:col-span-2">
            <x-filament::section>
                <x-slot name="heading">
                    ჩატის ინტერფეისი
                </x-slot>

                <!-- Conversation History -->
                <div class="mb-4 space-y-3 max-h-96 overflow-y-auto p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                    @forelse($conversation as $message)
                        <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[80%] rounded-lg px-4 py-2 {{ $message['role'] === 'user' ? 'bg-primary-600 text-white' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700' }}">
                                <div class="text-sm font-medium mb-1">
                                    {{ $message['role'] === 'user' ? 'მომხმარებელი' : 'ასისტენტი' }}
                                    @if($message['cached'] ?? false)
                                        <span class="ml-2 text-xs bg-green-500 text-white px-2 py-0.5 rounded">Cache Hit</span>
                                    @endif
                                    @if($message['error'] ?? false)
                                        <span class="ml-2 text-xs bg-red-500 text-white px-2 py-0.5 rounded">Error</span>
                                    @endif
                                </div>
                                <div class="text-sm whitespace-pre-wrap">{{ $message['content'] }}</div>
                                <div class="text-xs opacity-70 mt-1">{{ \Carbon\Carbon::parse($message['timestamp'])->format('H:i:s') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-500 py-8">
                            საუბარი ცარიელია. დაწერეთ შეტყობინება ტესტირების დასაწყებად.
                        </div>
                    @endforelse
                </div>

                <!-- Message Input -->
                <form wire:submit="sendMessage" class="space-y-3">
                    <div>
                        <input
                            type="text"
                            wire:model="message"
                            placeholder="დაწერეთ შეტყობინება..."
                            class="w-full rounded-lg border-none bg-white/5 shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-400 focus:ring-2 focus:ring-primary-500"
                            autofocus
                            wire:loading.attr="disabled"
                        />
                    </div>

                    <div class="flex gap-2 items-center">
                        <x-filament::button type="submit" color="primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>გაგზავნა</span>
                            <span wire:loading class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                ფიქრობს...
                            </span>
                        </x-filament::button>

                        <x-filament::button type="button" wire:click="clearConversation" color="gray">
                            გასუფთავება
                        </x-filament::button>
                    </div>

                    <!-- Options -->
                    <div class="flex gap-4 text-sm">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="cacheBypass" class="rounded border-none bg-white/5 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20 text-primary-600 focus:ring-2 focus:ring-primary-500">
                            <span class="text-gray-700 dark:text-gray-300">Cache Bypass</span>
                        </label>
                        <label class="flex items-center gap-2 opacity-50">
                            <input type="checkbox" wire:model="streamingEnabled" class="rounded border-none bg-white/5 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20" disabled>
                            <span class="text-gray-700 dark:text-gray-300">Streaming (Coming Soon)</span>
                        </label>
                    </div>
                </form>
            </x-filament::section>
        </div>

        <!-- Metrics & Debug (Right Column) -->
        <div class="space-y-6">
            <!-- Metrics -->
            <x-filament::section>
                <x-slot name="heading">
                    მეტრიკები
                </x-slot>

                @if($lastMetrics)
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="font-medium">სრული დრო:</dt>
                            <dd class="font-mono {{ $lastMetrics['total_latency_ms'] < 3000 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $lastMetrics['total_latency_ms'] }}ms
                            </dd>
                        </div>

                        @if(isset($lastMetrics['cache_hit']))
                            <div class="flex justify-between">
                                <dt class="font-medium">Cache:</dt>
                                <dd class="font-mono {{ $lastMetrics['cache_hit'] ? 'text-green-600' : 'text-gray-600' }}">
                                    {{ $lastMetrics['cache_hit'] ? 'HIT (' . ($lastMetrics['cache_layer'] ?? 'unknown') . ')' : 'MISS' }}
                                </dd>
                            </div>
                        @endif

                        @if(isset($lastMetrics['intent_analysis_ms']))
                            <div class="flex justify-between">
                                <dt class="font-medium">Intent Analysis:</dt>
                                <dd class="font-mono">{{ $lastMetrics['intent_analysis_ms'] }}ms</dd>
                            </div>
                        @endif

                        @if(isset($lastMetrics['supervisor_ms']))
                            <div class="flex justify-between">
                                <dt class="font-medium">Supervisor:</dt>
                                <dd class="font-mono">{{ $lastMetrics['supervisor_ms'] }}ms</dd>
                            </div>
                        @endif

                        @if(isset($lastMetrics['ttft_ms']))
                            <div class="flex justify-between">
                                <dt class="font-medium">TTFT:</dt>
                                <dd class="font-mono">{{ $lastMetrics['ttft_ms'] ?? 'N/A' }}</dd>
                            </div>
                        @endif
                    </dl>
                @else
                    <p class="text-sm text-gray-500">მეტრიკები გამოჩნდება პირველი შეტყობინების შემდეგ</p>
                @endif
            </x-filament::section>

            <!-- Execution Path -->
            @if($lastExecutionPath)
                <x-filament::section>
                    <x-slot name="heading">
                        შესრულების გზა
                    </x-slot>

                    <div class="space-y-2">
                        @foreach($lastExecutionPath as $step)
                            <div class="flex items-center justify-between text-sm p-2 rounded {{ $step['status'] === 'success' ? 'bg-green-50 dark:bg-green-900/20' : ($step['status'] === 'hit' ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-gray-50 dark:bg-gray-900/20') }}">
                                <span class="font-medium text-gray-900 dark:text-dark-100">{{ $step['step'] }}</span>
                                <span class="font-mono text-xs text-gray-700 dark:text-dark-300">{{ $step['duration_ms'] }}ms</span>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            <!-- Debug Info -->
            @if($lastDebugInfo)
                <x-filament::section>
                    <x-slot name="heading">
                        Debug ინფორმაცია
                    </x-slot>

                    <dl class="space-y-2 text-sm">
                        @foreach($lastDebugInfo as $key => $value)
                            <div>
                                <dt class="font-medium text-gray-700 dark:text-gray-300">{{ $key }}:</dt>
                                <dd class="font-mono text-xs mt-1 p-2 bg-gray-100 dark:bg-gray-800 rounded">
                                    {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $value }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </x-filament::section>
            @endif

            <!-- System Stats -->
            <x-filament::section>
                <x-slot name="heading">
                    სისტემის სტატუსი
                </x-slot>

                <div class="space-y-3 text-sm">
                    <!-- Circuit Breaker -->
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="font-medium">Circuit Breaker:</span>
                            <span class="px-2 py-0.5 rounded text-xs {{ $circuitBreakerStats['state'] === 'closed' ? 'bg-green-500 text-white' : 'bg-red-500 text-white' }}">
                                {{ strtoupper($circuitBreakerStats['state']) }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">
                            Failures: {{ $circuitBreakerStats['failures'] }}/{{ $circuitBreakerStats['threshold'] }}
                        </div>
                        @if($circuitBreakerStats['state'] !== 'closed')
                            <x-filament::button
                                type="button"
                                wire:click="resetCircuitBreaker"
                                color="warning"
                                size="xs"
                                class="mt-2"
                            >
                                Reset Circuit Breaker
                            </x-filament::button>
                        @endif
                    </div>

                    <!-- Cache Stats -->
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="font-medium">Cache:</span>
                            <span class="px-2 py-0.5 rounded text-xs {{ $cacheStats['enabled'] ? 'bg-green-500 text-white' : 'bg-gray-500 text-white' }}">
                                {{ $cacheStats['enabled'] ? 'ENABLED' : 'DISABLED' }}
                            </span>
                        </div>
                        <x-filament::button
                            type="button"
                            wire:click="clearCache"
                            color="gray"
                            size="xs"
                            class="mt-2"
                        >
                            Clear All Caches
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
