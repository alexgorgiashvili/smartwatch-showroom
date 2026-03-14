<div class="space-y-6">
    <div class="grid gap-6 xl:grid-cols-[2fr,1fr]">
        <x-filament::section>
            <x-slot name="heading">
                FAQ Entries
            </x-slot>

            <x-slot name="description">
                Create, update, and deactivate FAQ content that feeds the chatbot knowledge base.
            </x-slot>

            <div class="space-y-4">
                @if ($faqs->isEmpty())
                    <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500">
                        No FAQ entries yet.
                    </div>
                @else
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Question</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Category</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Sort</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Status</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($faqs as $faq)
                                    <tr class="align-top">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-950 dark:text-white">{{ $faq->question }}</div>
                                            <div class="mt-1 line-clamp-3 text-xs text-gray-500 dark:text-gray-400">{{ $faq->answer }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $faq->category }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $faq->sort_order }}</td>
                                        <td class="px-4 py-3">
                                            @if ($faq->is_active)
                                                <span class="inline-flex rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Active</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <x-filament-actions::group
                                                :actions="[
                                                    ($this->editFaqAction)(['faq' => $faq->id]),
                                                    ($this->deleteFaqAction)(['faq' => $faq->id]),
                                                ]"
                                                label="Actions"
                                                icon="heroicon-m-ellipsis-horizontal"
                                                color="gray"
                                                size="sm"
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Contact Settings
            </x-slot>

            <x-slot name="description">
                These values are used by the widget and omnichannel chatbot responses.
            </x-slot>

            <x-filament-panels::form wire:submit="saveContacts">
                {{ $this->contactForm }}

                <div class="mt-4">
                    <x-filament::button type="submit">
                        Save Contact Settings
                    </x-filament::button>
                </div>
            </x-filament-panels::form>
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</div>
