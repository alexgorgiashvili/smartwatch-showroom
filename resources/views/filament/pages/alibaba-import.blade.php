<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">
            Parse Alibaba Product
        </x-slot>

        <x-slot name="description">
            Paste a direct Alibaba URL first. If the request is blocked, paste the full browser page source instead.
        </x-slot>

        <x-filament-panels::form wire:submit="parseAlibaba">
            {{ $this->parseForm }}

            <div class="mt-4">
                <x-filament::button type="submit">
                    Parse Product
                </x-filament::button>
            </div>
        </x-filament-panels::form>
    </x-filament::section>

    @if ($hasParsedProduct)
        <x-filament::section>
            <x-slot name="heading">
                Review and Create Product
            </x-slot>

            <x-slot name="description">
                Adjust the parsed content before creating the product in the catalog.
            </x-slot>

            @if ($parsedImages !== [])
                <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($parsedImages as $image)
                        @php
                            $isSelected = in_array($image, $productData['selected_images'] ?? [], true);
                        @endphp

                        <div class="overflow-hidden rounded-2xl border {{ $isSelected ? 'border-primary-500 ring-1 ring-primary-500/30' : 'border-gray-200 dark:border-white/10' }} bg-white dark:bg-white/5">
                            <img src="{{ $image }}" alt="Alibaba image preview" class="h-40 w-full object-cover">
                            <div class="px-3 py-2 text-xs {{ $isSelected ? 'text-primary-700 dark:text-primary-300' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $isSelected ? 'Selected for import' : 'Not selected' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <x-filament-panels::form wire:submit="createProduct">
                {{ $this->productForm }}

                <div class="mt-4 flex gap-3">
                    <x-filament::button type="submit" color="success">
                        Confirm and Create Product
                    </x-filament::button>

                    <x-filament::button type="button" color="gray" wire:click="$refresh">
                        Refresh Preview
                    </x-filament::button>
                </div>
            </x-filament-panels::form>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</div>
