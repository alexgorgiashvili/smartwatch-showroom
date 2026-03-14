<style>
    .dark .fi-section-content-ctn {
        background-color: transparent !important;
    }
</style>
<div class="space-y-6">
    @include('filament.pages.chatbot-lab.nav')

    <x-filament::section>
        <x-slot name="heading">ფილტრები</x-slot>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">დროის ფანჯარა</label>
                <select wire:model.live="hours" class="fi-input block w-full rounded-lg border-none bg-white shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20">
                    @foreach ($hourOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">ნაბიჯის ძებნა</label>
                <input
                    wire:model.live.debounce.400ms="stepSearch"
                    type="text"
                    class="fi-input block w-full rounded-lg border-none bg-white shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20"
                    placeholder="მაგ: pipeline.completed"
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">მაქს. ჩანაწერები</label>
                <select wire:model.live="limit" class="fi-input block w-full rounded-lg border-none bg-white shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20">
                    @foreach ($limitOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-3">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">დამატებითი ფილტრები</div>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    <input wire:model.live="fallbackOnly" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-primary-600 focus:ring-primary-500">
                    მხოლოდ fallback-იანი
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    <input wire:model.live="multiAgentOnly" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-primary-600 focus:ring-primary-500">
                    მხოლოდ multi-agent
                </label>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <x-filament::button type="button" color="gray" wire:click="resetFilters">
                ფილტრების გასუფთავება
            </x-filament::button>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                ფანჯარა: {{ \Illuminate\Support\Carbon::parse($meta['window_start'])->format('Y-m-d H:i') }} - {{ \Illuminate\Support\Carbon::parse($meta['window_end'])->format('Y-m-d H:i') }}
            </div>
        </div>
    </x-filament::section>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-filament::section>
            <div class="text-xs text-gray-500">Pipeline ნაბიჯები</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $summary['total_pipeline_steps'] ?? 0 }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs text-gray-500">უნიკალური Trace ID</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $summary['unique_trace_ids'] ?? 0 }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs text-gray-500">საშუალო პასუხის დრო</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $summary['avg_response_time_ms'] ?? 0 }} ms</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs text-gray-500">ვალიდაციის Pass Rate</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((float) ($summary['validation_pass_rate'] ?? 0), 2) }}%</div>
        </x-filament::section>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <x-filament::section>
            <div class="text-xs text-gray-500">Multi-Agent დაწყებული</div>
            <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $summary['multi_agent_started'] ?? 0 }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Multi-Agent დასრულებული</div>
            <div class="mt-1 text-xl font-semibold text-success-600 dark:text-success-400">{{ $summary['multi_agent_completed'] ?? 0 }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Multi-Agent შეცდომა</div>
            <div class="mt-1 text-xl font-semibold text-danger-600 dark:text-danger-400">{{ $summary['multi_agent_failed'] ?? 0 }}</div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Pipeline ტრეისები</x-slot>

        <div class="mb-4 text-xs text-gray-500 dark:text-gray-400">
            ნაპოვნი ჩანაწერები: {{ $meta['entries_count'] ?? 0 }} | დამუშავებული ლოგ ხაზები: {{ $meta['matched_log_lines'] ?? 0 }}
        </div>

        @if (($entries ?? []) === [])
            <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                მონიშნულ ფილტრებში pipeline ტრეისები ვერ მოიძებნა.
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-3 py-3 text-left">დრო</th>
                            <th class="px-3 py-3 text-left">Trace</th>
                            <th class="px-3 py-3 text-left">Conversation</th>
                            <th class="px-3 py-3 text-left">ნაბიჯი</th>
                            <th class="px-3 py-3 text-left">ტიპი</th>
                            <th class="px-3 py-3 text-left">Latency</th>
                            <th class="px-3 py-3 text-left">Validation</th>
                            <th class="px-3 py-3 text-left">Fallback</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-700 dark:text-gray-200">{{ $entry['timestamp_label'] ?? '—' }}</td>
                                <td class="px-3 py-3 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $entry['trace_id'] ?? '—' }}</td>
                                <td class="px-3 py-3 text-gray-700 dark:text-gray-200">{{ $entry['conversation_id'] ?? '—' }}</td>
                                <td class="px-3 py-3 font-mono text-xs text-gray-900 dark:text-white">{{ $entry['step'] ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    @if (!empty($entry['is_multi_agent']))
                                        <span class="inline-flex rounded-full bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">multi-agent</span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-gray-700 dark:text-gray-200">
                                    @if (($entry['response_time_ms'] ?? null) !== null)
                                        {{ $entry['response_time_ms'] }} ms
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if (($entry['validation_passed'] ?? null) === true)
                                        <span class="inline-flex rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-300">გაიარა</span>
                                    @elseif (($entry['validation_passed'] ?? null) === false)
                                        <span class="inline-flex rounded-full bg-danger-50 px-2 py-1 text-xs font-medium text-danger-700 dark:bg-danger-500/15 dark:text-danger-300">ვერ გაიარა</span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $entry['fallback_reason'] ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="px-3 pb-4" colspan="8">
                                    <details class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                        <summary class="cursor-pointer text-xs font-medium text-gray-600 dark:text-gray-300">დეტალები (context)</summary>
                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">next_step: <span class="font-mono">{{ $entry['next_step'] ?? '—' }}</span></div>
                                        <pre class="mt-2 max-h-64 overflow-auto rounded-md bg-gray-50 p-3 text-xs text-gray-700 dark:bg-white/5 dark:text-gray-200">{{ $entry['context_pretty'] ?? '{}' }}</pre>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</div>
