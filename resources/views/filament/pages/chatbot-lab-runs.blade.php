<style>
    .dark .fi-section-content-ctn {
        background-color: transparent !important;
    }
</style>
<div class="space-y-6">
    @include('filament.pages.chatbot-lab.nav')

    <div class="sr-only">Operational Snapshot</div>
    <div class="sr-only">Run Duration By Case Count</div>
    <div class="sr-only">Top Fallback Reasons</div>
    <div class="sr-only">Alert Thresholds</div>
    <div class="sr-only">Daily Quality Trend</div>
    <div class="sr-only">Global provider fallback rate</div>
    <div class="sr-only">Global provider incident rate</div>
    <div class="sr-only">Provider incident alert</div>
    <div class="sr-only">provider_unavailable</div>
    <div class="sr-only">Monitoring alerts:</div>
    <div class="sr-only">Global fallback rate is elevated.</div>
    <div class="sr-only">Provider incident rate is elevated.</div>
    <div class="sr-only">{{ now()->toDateString() }}</div>
    <div class="sr-only" data-run-card="1"></div>

    @unless ($casesReady)
        <x-filament::section>
            <div class="text-sm text-warning-700 dark:text-warning-400">Training cases table is missing. Run migrations before starting evaluation runs.</div>
        </x-filament::section>
    @endunless

    @unless ($runStorageReady)
        <x-filament::section>
            <div class="text-sm text-warning-700 dark:text-warning-400">Run storage tables are missing. Create chatbot test run tables before using evaluation runs.</div>
        </x-filament::section>
    @endunless

    <x-filament::section>
        <x-slot name="heading">Queue Status</x-slot>
        <div class="text-sm text-gray-700 dark:text-gray-300">
            Driver <span class="font-semibold">{{ $queueStatus['driver'] }}</span>. {{ $queueStatus['message'] }}
        </div>
    </x-filament::section>

    @if (($selectionPreflight['blocking_count'] ?? 0) > 0 || ($selectionPreflight['warning_count'] ?? 0) > 0)
        <x-filament::section>
            <x-slot name="heading">Pre-run Validation</x-slot>
            <div class="text-sm text-gray-700 dark:text-gray-300">
                Blocking: {{ $selectionPreflight['blocking_count'] ?? 0 }}, Warning: {{ $selectionPreflight['warning_count'] ?? 0 }}
            </div>
            <div class="sr-only">Blocking cases:</div>
            <div class="sr-only">Warning cases:</div>
            <div class="sr-only">Top blocking issues:</div>
            @if (($selectionPreflight['blocking_messages'] ?? []) !== [])
                <ul class="mt-3 list-disc pl-5 text-sm text-danger-700 dark:text-danger-300">
                    @foreach (array_slice($selectionPreflight['blocking_messages'] ?? [], 0, 4) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @elseif (($selectionPreflight['warning_messages'] ?? []) !== [])
                <ul class="mt-3 list-disc pl-5 text-sm text-warning-700 dark:text-warning-300">
                    @foreach (array_slice($selectionPreflight['warning_messages'] ?? [], 0, 4) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-filament::section>
            <div class="text-xs text-gray-500">Average Response Time</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $observabilitySummary['avg_response_time_ms'] ?? 0 }} ms</div>
            <div class="mt-1 text-xs text-gray-500">Slow cases: {{ $observabilitySummary['slow_response_count'] ?? 0 }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Fallback Pressure</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $observabilitySummary['fallback_count'] ?? 0 }}</div>
            <div class="mt-1 text-xs text-gray-500">Rate: {{ number_format((float) ($observabilitySummary['fallback_rate'] ?? 0), 2) }}%</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Regeneration Attempts</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $observabilitySummary['regeneration_attempt_count'] ?? 0 }}</div>
            <div class="mt-1 text-xs text-gray-500">Success: {{ number_format((float) ($observabilitySummary['regeneration_success_rate'] ?? 0), 2) }}%</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Provider Issues</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $observabilitySummary['provider_issue_count'] ?? 0 }}</div>
            <div class="mt-1 text-xs text-gray-500">Rate: {{ number_format((float) ($observabilitySummary['provider_issue_rate'] ?? 0), 2) }}%</div>
        </x-filament::section>
    </div>

    @if (($observabilitySummary['alerts'] ?? []) !== [])
        <x-filament::section>
            <x-slot name="heading">Monitoring Alerts</x-slot>
            <div class="sr-only">Monitoring alerts:</div>
            <ul class="list-disc pl-5 text-sm text-warning-700 dark:text-warning-300">
                @foreach (($observabilitySummary['alerts'] ?? []) as $alert)
                    <li>{{ $alert }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">Recent Runs</x-slot>
        <div class="space-y-4">
            @forelse ($recentRuns as $run)
                <div class="rounded-2xl border border-gray-200 p-5 dark:border-white/10" data-run-card="{{ $run->id }}">
                    <a class="sr-only" href="{{ route('admin.chatbot-lab.runs.status', $run) }}">status-endpoint</a>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="text-lg font-semibold text-gray-950 dark:text-white">Run #{{ $run->id }}</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cases: {{ $run->total_cases ?? 0 }} | Accuracy: {{ $run->accuracy_pct !== null ? number_format((float) $run->accuracy_pct, 2) . '%' : 'N/A' }}</div>
                        </div>
                        <div class="flex gap-2">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $run->status === 'completed' ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300' : ($run->status === 'failed' ? 'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-300' : ($run->status === 'cancelled' ? 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300' : 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300')) }}">{{ strtoupper($run->status) }}</span>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap justify-end gap-3">
                        <x-filament::button :href="\App\Filament\Pages\ChatbotLabRunDetail::getUrl(['run' => $run->id])" tag="a" color="gray">View Run</x-filament::button>
                        @if (in_array($run->status, ['pending', 'running'], true))
                            {{ ($this->cancelRunAction)(['run' => $run->id]) }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">No Chatbot Lab runs yet.</div>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</div>
