<div class="space-y-6">
    @include('filament.pages.chatbot-lab.nav')

    <div class="sr-only">ჩატბოტ ლაბი</div>
    <div class="sr-only">ხელით ტესტი</div>

    @if (!empty($statusMessage))
        <x-filament::section>
            <div class="text-sm text-gray-700 dark:text-gray-300">{{ $statusMessage }}</div>
        </x-filament::section>
    @endif

    @unless ($casesReady)
        <x-filament::section>
            <div class="text-sm text-warning-700 dark:text-warning-400">Training cases table is missing. Run migrations before saving manual results as reusable cases.</div>
        </x-filament::section>
    @endunless

    <div class="grid gap-6 xl:grid-cols-[420px,1fr]">
        <div class="space-y-6">
            <x-filament::section>
                <x-slot name="heading">Active Session</x-slot>
                <div class="sr-only">მუდმივი სესია</div>
                @if ($sessionState)
                    <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                        <div>Conversation #{{ $sessionState['conversation_id'] }}</div>
                        <div>Turns: {{ $sessionState['turn_count'] ?? 0 }}</div>
                        <div>Last active: {{ $sessionState['last_active'] ?? '—' }}</div>
                    </div>
                @else
                    <div class="text-sm text-gray-500 dark:text-gray-400">No persistent lab session is active. One-off runs will start from a clean conversation.</div>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Manual Testing</x-slot>
                <x-filament-panels::form wire:submit="runManualTest">
                    {{ $this->manualTest }}
                    <div class="mt-4 flex flex-wrap gap-3">
                        <x-filament::button type="submit">Run Test</x-filament::button>
                        @if ($result)
                            <x-filament::button type="button" color="gray" wire:click="retryManualResult('same')">Rerun Same</x-filament::button>
                            <x-filament::button type="button" color="primary" wire:click="retryManualResult('constrained')">Rerun Constrained</x-filament::button>
                            <x-filament::button type="button" color="success" wire:click="saveResultAsCase">Save As Case</x-filament::button>
                            <span class="sr-only">იგივე კითხვის ხელახალი გაშვება</span>
                            <span class="sr-only">შეზღუდვებით ხელახალი გაშვება</span>
                        @endif
                    </div>
                </x-filament-panels::form>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Result</x-slot>
            <div class="sr-only">საბოლოო პასუხი</div>
            @if (!$result)
                <div class="text-sm text-gray-500 dark:text-gray-400">Run a prompt to inspect the chatbot response, pipeline diagnostics, and selected products.</div>
                <div class="sr-only">ერთჯერადი გაშვება</div>
            @else
                <div class="space-y-6 text-sm">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="text-xs text-gray-500">Intent</div>
                            <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $result['debug']['intent'] ?? '—' }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="text-xs text-gray-500">Response Time</div>
                            <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $result['debug']['response_time_ms'] ?? 0 }} ms</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="text-xs text-gray-500">Fallback</div>
                            <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $result['debug']['fallback_label'] ?? 'No' }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="text-xs text-gray-500">Signal</div>
                            <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $result['debug']['signal_label'] ?? 'Healthy' }}</div>
                        </div>
                    </div>

                    @if (!empty($result['retry']))
                        <div class="rounded-xl border border-primary-200 bg-primary-50/50 p-4 dark:border-primary-500/20 dark:bg-primary-500/10">
                            <div class="font-medium text-gray-950 dark:text-white">Retry Context</div>
                            <div class="sr-only">ხელახალი გაშვების კონტექსტი</div>
                            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">Strategy: {{ $result['retry']['strategy_label'] ?? 'Retry' }}</div>
                            <div class="sr-only">Retry with constraints</div>
                            <div class="sr-only">გამოყენებული შეზღუდვები</div>
                            <div class="text-sm text-gray-600 dark:text-gray-300">Source Prompt: {{ $result['retry']['source_prompt'] ?? ($result['prompt'] ?? '') }}</div>
                            @if (($result['retry']['constraint_hints'] ?? []) !== [])
                                <ul class="mt-2 list-disc pl-5 text-sm text-gray-600 dark:text-gray-300">
                                    @foreach (($result['retry']['constraint_hints'] ?? []) as $hint)
                                        <li>{{ $hint }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif

                    <div>
                        <div class="mb-2 font-medium text-gray-950 dark:text-white">Bot Response</div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 whitespace-pre-wrap dark:border-white/10 dark:bg-white/5 dark:text-gray-100">{{ $result['response'] }}</div>
                    </div>

                    @if (($result['transcript'] ?? []) !== [])
                        <div>
                            <div class="mb-2 font-medium text-gray-950 dark:text-white">Transcript</div>
                            <div class="sr-only">სესიის ისტორია</div>
                            <div class="space-y-3">
                                @foreach (($result['transcript'] ?? []) as $turn)
                                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                                        <div class="text-xs text-gray-500">User</div>
                                        <div class="mb-3 mt-1 text-gray-900 dark:text-gray-100">{{ $turn['prompt'] }}</div>
                                        <div class="text-xs text-gray-500">Assistant</div>
                                        <div class="mt-1 whitespace-pre-wrap text-gray-900 dark:text-gray-100">{{ $turn['response'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="grid gap-4 xl:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="mb-2 font-medium text-gray-950 dark:text-white">Pipeline Summary</div>
                            <dl class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                <div class="flex justify-between gap-4"><dt>Intent Confidence</dt><dd>{{ $result['debug']['intent_confidence'] ?? '—' }}</dd></div>
                                <div class="flex justify-between gap-4"><dt>Guard Allowed</dt><dd>{{ ($result['debug']['guard_allowed'] ?? false) ? 'yes' : 'no' }}</dd></div>
                                <div class="flex justify-between gap-4"><dt>Georgian Passed</dt><dd>{{ ($result['debug']['georgian_passed'] ?? false) ? 'yes' : 'no' }}</dd></div>
                                <div class="flex justify-between gap-4"><dt>Validation Passed</dt><dd>{{ ($result['debug']['validation_passed'] ?? false) ? 'yes' : 'no' }}</dd></div>
                                <div class="flex justify-between gap-4"><dt>Regeneration</dt><dd>{{ ($result['debug']['regeneration_attempted'] ?? false) ? 'attempted' : 'not used' }}</dd></div>
                            </dl>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="mb-2 font-medium text-gray-950 dark:text-white">Actionable Signal</div>
                            <div class="sr-only">მთავარი სიგნალი</div>
                            <div class="text-sm text-gray-600 dark:text-gray-300">Source: <span class="capitalize">{{ $result['debug']['signal_group'] ?? 'healthy' }}</span></div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $result['debug']['signal_label'] ?? 'No major issue detected' }}</div>
                            <div class="sr-only">მნიშვნელოვანი პრობლემა არ დაფიქსირდა</div>
                            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $result['debug']['recommended_action'] ?? 'Inspect the raw pipeline payload if you need more detail.' }}</div>
                        </div>
                    </div>

                    @if (($result['debug']['products'] ?? []) !== [])
                        <div>
                            <div class="mb-2 font-medium text-gray-950 dark:text-white">Grounded Products</div>
                            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                                    <thead class="bg-gray-50 dark:bg-white/5">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Name</th>
                                            <th class="px-4 py-3 text-left">Price</th>
                                            <th class="px-4 py-3 text-left">Sale</th>
                                            <th class="px-4 py-3 text-left">In Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                        @foreach (($result['debug']['products'] ?? []) as $product)
                                            <tr>
                                                <td class="px-4 py-3">{{ $product['name'] ?? '—' }}</td>
                                                <td class="px-4 py-3">{{ $product['price'] ?? '—' }}</td>
                                                <td class="px-4 py-3">{{ $product['sale_price'] ?? '—' }}</td>
                                                <td class="px-4 py-3">{{ !empty($product['is_in_stock']) ? 'yes' : 'no' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <details class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <summary class="cursor-pointer font-medium text-gray-950 dark:text-white">Raw Pipeline Payload</summary>
                        <pre class="mt-4 overflow-x-auto text-xs text-gray-700 dark:text-gray-300">{{ json_encode($result['raw_pipeline'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                </div>
            @endif
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</div>
