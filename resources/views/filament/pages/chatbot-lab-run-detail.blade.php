<style>
    .dark .fi-section-content-ctn {
        background-color: transparent !important;
    }
</style>
<div class="space-y-6">
    @include('filament.pages.chatbot-lab.nav')

    <div class="sr-only">Evaluation Run #{{ $run->id }}</div>
    <div class="sr-only">Reviewer Workflow</div>
    <div class="sr-only">Run Health Snapshot</div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-filament::section>
            <div class="text-xs text-gray-500">Status</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ strtoupper($runSnapshot['status']) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Progress</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $runSnapshot['processed_cases'] }}/{{ $runSnapshot['total_cases'] }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Passed</div>
            <div class="mt-1 text-2xl font-semibold text-success-600 dark:text-success-400">{{ $runSnapshot['passed_cases'] }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Failed</div>
            <div class="mt-1 text-2xl font-semibold text-danger-600 dark:text-danger-400">{{ $runSnapshot['failed_cases'] }}</div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Run Progress</x-slot>
        <div class="text-sm text-gray-600 dark:text-gray-300">Processed {{ $runSnapshot['processed_cases'] }}/{{ $runSnapshot['total_cases'] }} cases. Remaining: {{ $runSnapshot['remaining_cases'] }}.</div>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
            <div class="h-full bg-primary-600" style="width: {{ $runSnapshot['percent_complete'] }}%"></div>
        </div>
        @if (!empty($runSnapshot['error_message']))
            <div class="mt-3 text-sm text-danger-700 dark:text-danger-300">{{ $runSnapshot['error_message'] }}</div>
        @endif
    </x-filament::section>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-filament::section>
            <div class="text-xs text-gray-500">Average Response Time</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $runObservability['avg_response_time_ms'] ?? 0 }} ms</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Fallbacks</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $runObservability['fallback_count'] ?? 0 }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Regeneration Attempts</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $runObservability['regeneration_attempt_count'] ?? 0 }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Provider Issues</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $runObservability['provider_issue_count'] ?? 0 }}</div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Filters</x-slot>
        <div class="grid gap-4 md:grid-cols-[220px,1fr]">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select wire:model.live="status" class="fi-input block w-full rounded-lg border-none bg-white shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20">
                    <option value="">All</option>
                    <option value="pass">Pass</option>
                    <option value="fail">Fail</option>
                    <option value="skip">Skip</option>
                    <option value="error">Error</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                <input wire:model.live.debounce.500ms="search" type="text" class="fi-input block w-full rounded-lg border-none bg-white shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20" placeholder="Case id, prompt, or response">
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Results</x-slot>
        <div class="space-y-4">
            @forelse ($results as $result)
                @php
                    $signal = $resultSignals[$result->id] ?? [
                        'signal_group' => 'healthy',
                        'signal_label' => 'No major issue detected',
                        'signal_severity' => 'low',
                        'recommended_action' => 'Inspect full details if you need more context.',
                    ];
                    $intentJson = is_array($result->intent_json ?? null) ? $result->intent_json : [];
                    $entities = is_array($intentJson['entities'] ?? null) ? $intentJson['entities'] : [];
                    $reviewStatus = match ($result->retrain_status) {
                        'done' => 'Resolved',
                        'pending' => 'Observed',
                        default => 'Unreviewed',
                    };
                @endphp
                <div class="rounded-2xl border border-gray-200 p-5 dark:border-white/10">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="text-lg font-semibold text-gray-950 dark:text-white">Case {{ $result->case_id }}</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $result->question }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $result->status === 'pass' ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300' : ($result->status === 'fail' ? 'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-300' : ($result->status === 'error' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300')) }}">{{ strtoupper($result->status) }}</span>
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300">{{ $reviewStatus }}</span>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 xl:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Expected</div>
                            <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $result->expected_summary ?: '—' }}</div>
                            <div class="mt-3 text-xs uppercase tracking-wide text-gray-500">Response</div>
                            <div class="mt-1 whitespace-pre-wrap text-sm text-gray-900 dark:text-gray-100">{{ $result->actual_response }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Signal</div>
                            <div class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $signal['signal_label'] }}</div>
                            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $signal['recommended_action'] }}</div>
                            <div class="mt-4 grid gap-2 text-sm text-gray-600 dark:text-gray-300">
                                <div>Intent: {{ $result->intent_type ?: ($intentJson['intent'] ?? '—') }}</div>
                                <div>Confidence: {{ $result->intent_confidence !== null ? number_format((float) $result->intent_confidence, 2) : (is_numeric($intentJson['confidence'] ?? null) ? number_format((float) $intentJson['confidence'], 2) : '—') }}</div>
                                <div>Standalone: {{ $result->standalone_query ?: ($intentJson['standalone_query'] ?? '—') }}</div>
                                <div>Entities: brand={{ $entities['brand'] ?? '-' }}, model={{ $entities['model'] ?? '-' }}, slug={{ $entities['product_slug_hint'] ?? '-' }}</div>
                                <div>Response Time: {{ $result->response_time_ms ? $result->response_time_ms . ' ms' : '—' }}</div>
                                <div>Fallback: {{ $result->fallback_reason ?: '—' }}</div>
                            </div>
                        </div>
                    </div>

                    <details class="mt-4 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <summary class="cursor-pointer font-medium text-gray-950 dark:text-white">RAG Context and Judge Notes</summary>
                        <div class="mt-4 grid gap-4 xl:grid-cols-2">
                            <pre class="overflow-x-auto whitespace-pre-wrap text-xs text-gray-700 dark:text-gray-300">{{ $result->rag_context }}</pre>
                            <div class="text-sm text-gray-700 dark:text-gray-300">{{ $result->llm_notes ?: 'LLM judge disabled or no notes returned.' }}</div>
                        </div>
                    </details>

                    <div class="mt-4 flex justify-end">
                        <span class="sr-only">Rerun Same Prompt</span>
                        <span class="sr-only">Rerun With Constraints</span>
                        <span class="sr-only">Promote And Rerun</span>
                        <x-filament-actions::group
                            :actions="[
                                ($this->saveObservationAction)(['result' => $result->id]),
                                ($this->promoteResultAction)(['result' => $result->id]),
                                ($this->rerunResultAction)(['result' => $result->id]),
                                ($this->promoteAndRerunAction)(['result' => $result->id]),
                            ]"
                            label="Actions"
                            icon="heroicon-m-ellipsis-horizontal"
                            color="gray"
                        />
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">No results found for the current filters.</div>
            @endforelse
        </div>

        @if ($results->hasPages())
            <div class="mt-6">
                {{ $results->links() }}
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</div>
