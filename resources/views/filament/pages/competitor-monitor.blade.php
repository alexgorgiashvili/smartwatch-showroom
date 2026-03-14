<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">
            Source Overview
        </x-slot>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,280px)_minmax(0,1fr)_minmax(0,220px)]">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Source</label>
                <select wire:model.live="selectedSourceId" class="fi-input block w-full rounded-lg border-none bg-white shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20">
                    @foreach ($sources as $availableSource)
                        <option value="{{ $availableSource->id }}">{{ $availableSource->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Category URL</div>
                @if ($source)
                    <a href="{{ $source->category_url }}" target="_blank" rel="noopener" class="mt-2 inline-flex text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                        Open source page
                    </a>
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $source->category_url }}</div>
                @else
                    <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">No source selected.</div>
                @endif
            </div>

            <div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Last Refresh</div>
                <div class="mt-2 text-sm text-gray-900 dark:text-white">{{ $source?->last_synced_at?->format('Y-m-d H:i') ?? 'Never' }}</div>
                @if ($source?->last_status === 'failed' && $source?->last_error)
                    <div class="mt-2 text-xs text-danger-600 dark:text-danger-400">{{ $source->last_error }}</div>
                @endif
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            Competitor Products
        </x-slot>

        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Product</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Price</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Old Price</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Stock</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">History</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Mapped Product</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($products as $item)
                        <tr class="align-top">
                            <td class="px-4 py-3">
                                <div class="flex gap-3">
                                    <img
                                        src="{{ $item->image_url ?: asset('assets/images/others/placeholder.jpg') }}"
                                        alt="{{ $item->title }}"
                                        class="h-14 w-14 rounded-lg object-cover"
                                    >
                                    <div>
                                        <a href="{{ $item->product_url }}" target="_blank" rel="noopener" class="font-medium text-gray-950 hover:text-primary-600 dark:text-white dark:hover:text-primary-400">
                                            {{ $item->title }}
                                        </a>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">ID: {{ $item->external_product_id ?: '-' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Last seen: {{ $item->last_seen_at?->format('Y-m-d H:i') ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                {{ $item->current_price !== null ? number_format((float) $item->current_price, 2) . ' ' . $item->currency : '-' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                {{ $item->old_price !== null ? number_format((float) $item->old_price, 2) . ' ' . $item->currency : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($item->is_in_stock === true)
                                    <span class="inline-flex rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">In Stock</span>
                                @elseif ($item->is_in_stock === false)
                                    <span class="inline-flex rounded-full bg-danger-50 px-2 py-1 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">Out</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">Unknown</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $item->snapshots_count }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $item->mapping?->product?->name_ka ?: $item->mapping?->product?->name_en ?: 'Not mapped' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ ($this->mapProductAction)(['product' => $item->id]) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No competitor products yet. Refresh the selected source to scrape data.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($products->hasPages())
            <div class="mt-4">
                {{ $products->links() }}
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</div>
