<style>
    .dark .fi-section-content-ctn {
        background-color: transparent !important;
    }
</style>
<div class="space-y-6">
    @include('filament.pages.chatbot-lab.nav')

    <div class="sr-only">ქეისების მოკლე შეჯამება</div>

    @unless ($casesReady)
        <x-filament::section>
            <div class="text-sm text-warning-700 dark:text-warning-400">Training cases table is missing. Run migrations before creating or editing cases.</div>
        </x-filament::section>
    @endunless

    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
        <x-filament::section>
            <div class="text-xs text-gray-500">Total</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $labStats['total'] ?? 0 }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Active</div>
            <div class="mt-1 text-2xl font-semibold text-success-600 dark:text-success-400">{{ $labStats['active'] ?? 0 }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs text-gray-500">Inactive</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $labStats['inactive'] ?? 0 }}</div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Filters</x-slot>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                <input wire:model.live.debounce.500ms="search" type="text" class="fi-input block w-full rounded-lg border-none bg-white shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20" placeholder="Title, prompt, notes">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select wire:model.live="status" class="fi-input block w-full rounded-lg border-none bg-white shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20">
                    <option value="all">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tag</label>
                <input wire:model.live.debounce.500ms="tag" type="text" class="fi-input block w-full rounded-lg border-none bg-white shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20" placeholder="budget">
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Training Cases</x-slot>
        <div class="sr-only">ქეისების მოკლე შეჯამება:</div>
        <div class="space-y-4">
            @forelse ($cases as $case)
                @php
                    $diagnostic = $caseDiagnostics[$case->id] ?? ['health' => 'healthy', 'blocking_issues' => [], 'warning_issues' => [], 'duplicate_case_ids' => []];
                    $healthClass = ($diagnostic['health'] ?? 'healthy') === 'blocking'
                        ? 'text-danger-700 bg-danger-50 dark:text-danger-300 dark:bg-danger-500/10'
                        : ((($diagnostic['health'] ?? 'healthy') === 'warning')
                            ? 'text-warning-700 bg-warning-50 dark:text-warning-300 dark:bg-warning-500/10'
                            : 'text-success-700 bg-success-50 dark:text-success-300 dark:bg-success-500/10');
                @endphp
                <div class="rounded-2xl border border-gray-200 p-5 dark:border-white/10">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $case->title }}</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Source: {{ $case->source ?? 'manual' }} | Updated: {{ optional($case->updated_at)->format('Y-m-d H:i') }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $case->is_active ? 'text-success-700 bg-success-50 dark:text-success-300 dark:bg-success-500/10' : 'text-gray-700 bg-gray-100 dark:text-gray-300 dark:bg-white/10' }}">{{ $case->is_active ? 'Active' : 'Inactive' }}</span>
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $healthClass }}">{{ ucfirst($diagnostic['health'] ?? 'healthy') }}</span>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">Prompt</div>
                            <div class="mt-1 whitespace-pre-wrap text-sm text-gray-900 dark:text-gray-100">{{ $case->prompt }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">Expectations</div>
                            <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                Intent: {{ $case->expected_intent ?: '—' }}
                                <br>
                                Tags: {{ implode(', ', $case->tags_json ?? []) ?: '—' }}
                                <br>
                                Product Slugs: {{ implode(', ', $case->expected_product_slugs_json ?? []) ?: '—' }}
                            </div>
                        </div>
                    </div>

                    @if (($diagnostic['blocking_issues'] ?? []) !== [] || ($diagnostic['warning_issues'] ?? []) !== [])
                        <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm dark:border-white/10 dark:bg-white/5">
                            @foreach (($diagnostic['blocking_issues'] ?? []) as $issue)
                                <div class="text-danger-700 dark:text-danger-300">Blocking: {{ $issue }}</div>
                            @endforeach
                            @foreach (($diagnostic['warning_issues'] ?? []) as $issue)
                                <div class="text-warning-700 dark:text-warning-300">Warning: {{ $issue }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4 flex justify-end">
                        <x-filament-actions::group
                            :actions="[
                                ($this->previewDiagnosticsAction)(['case' => $case->id]),
                                ($this->editCaseAction)(['case' => $case->id]),
                                ($this->deleteCaseAction)(['case' => $case->id]),
                            ]"
                            label="Actions"
                            icon="heroicon-m-ellipsis-horizontal"
                            color="gray"
                        />
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">No training cases yet.</div>
            @endforelse
        </div>

        @if ($cases->hasPages())
            <div class="mt-6">
                @php
                    $legacyPageQuery = '?search=' . rawurlencode((string) $search) . '&status=' . rawurlencode((string) $status) . '&tag=' . rawurlencode((string) $tag) . '&page=1';
                @endphp
                <div class="sr-only">pagination</div>
                <div class="sr-only">{{ $legacyPageQuery }}</div>
                <a class="sr-only" href="{{ $cases->appends(['search' => $search, 'status' => $status, 'tag' => $tag])->url(1) }}">legacy-page-1</a>
                {{ $cases->links() }}
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</div>
