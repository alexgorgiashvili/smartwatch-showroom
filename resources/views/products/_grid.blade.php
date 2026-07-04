@php
    $catalogItems = $displayItems ?? $products;
@endphp

@if ($catalogItems->isEmpty())
    <div class="mt-8 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
        </svg>
        <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ __('ui.no_products') }}</h3>
        <p class="mt-1 text-sm text-gray-500">{{ __('ui.no_products_text') }}</p>
        <div class="mt-6">
            <button type="button" data-product-filter="all" class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">
                <i class="fa-solid fa-arrow-rotate-left mr-2"></i>
                {{ __('ui.filter_reset') }}
            </button>
        </div>
    </div>
@else
    <ul class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-4 w-full">
        @foreach ($catalogItems as $item)
            @php
                $displayType = is_array($item) ? ($item['type'] ?? 'product') : 'product';
                $product = is_array($item) ? ($item['product'] ?? null) : $item;
                $listedVariant = is_array($item) ? ($item['variant'] ?? null) : null;
                $variantImage = is_array($item) ? ($item['variant_image'] ?? null) : null;
                $variantLabel = is_array($item) ? ($item['variant_label'] ?? null) : null;

                if (! $product) {
                    continue;
                }

                $image = $product->primaryImage ?? $product->images->first();
                $secondaryImage = $displayType === 'variant' ? null : $product->images->skip(1)->first();

                if ($displayType === 'variant' && is_array($variantImage)) {
                    $imageUrl = $variantImage['thumbnail_url'] ?? $variantImage['url'] ?? ($image?->thumbnail_url ?: asset('storage/images/home/smart-watch3.jpg'));
                } else {
                    $imageUrl = $image?->thumbnail_url ?: asset('storage/images/home/smart-watch3.jpg');
                }

                $secondaryImageUrl = $secondaryImage?->thumbnail_url;
                $currency = $product->currency === 'GEL' ? '₾' : $product->currency;
                $basePrice = $product->price;
                $salePrice = $product->sale_price ?? null;
                $hasDiscount = $salePrice !== null && $basePrice !== null && $salePrice < $basePrice;
                $discountPercent = $hasDiscount ? (int) round((($basePrice - $salePrice) / $basePrice) * 100) : null;
                $featureBadges = [];
                if ($product->sim_support) {
                    $featureBadges[] = 'SIM Support';
                }
                if ($product->gps_features) {
                    $featureBadges[] = 'GPS';
                }
                if ($product->water_resistant) {
                    $featureBadges[] = $product->water_resistant;
                }
                if ($product->battery_capacity_mah) {
                    $featureBadges[] = $product->battery_capacity_mah . 'mAh';
                }
                if ($product->display_type) {
                    $featureBadges[] = $product->display_type;
                }
                $featureBadges = array_slice($featureBadges, 0, 2);

                if ($displayType === 'variant' && $listedVariant) {
                    $availableVariants = collect([$listedVariant])->filter(fn ($variant) => $variant->available_quantity > 0)->values();
                } else {
                    $availableVariants = $product->variants->filter(fn ($variant) => $variant->available_quantity > 0)->values();
                }

                $hasAvailableVariants = $availableVariants->isNotEmpty();
                $detailsUrl = route('products.show', $product);

                $displayName = $displayType === 'variant' && $variantLabel
                    ? $product->name . ' - ' . $variantLabel
                    : $product->name;
            @endphp
            <li>
                <div class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_28px_rgba(15,23,42,0.08)] transition duration-300 hover:-translate-y-1 hover:border-slate-300 hover:shadow-[0_18px_40px_rgba(15,23,42,0.14)]">
                    <a href="{{ route('products.show', $product) }}" class="block">
                        <div class="relative isolate overflow-hidden">
                            <div class="pointer-events-none absolute inset-x-0 top-0 z-10 flex items-start justify-between p-2 sm:p-3">
                                <div class="flex flex-wrap gap-1.5">
                                    @if ($product->featured)
                                        <span class="inline-flex items-center rounded-full border border-white/30 bg-slate-900/80 px-2 py-1 text-[10px] font-medium uppercase tracking-[0.12em] text-white">
                                            Featured
                                        </span>
                                    @endif
                                </div>

                                <div>
                                    @if ($hasDiscount)
                                        <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50/95 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-rose-700">
                                            -{{ $discountPercent }}%
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <img
                                src="{{ $imageUrl }}"
                                alt="{{ $image?->alt ?: $product->name }}"
                                loading="lazy"
                                decoding="async"
                                class="h-44 w-full object-contain transition duration-500 group-hover:scale-[1.06] {{ $secondaryImageUrl ? 'group-hover:opacity-0' : '' }}"
                            />

                            @if ($secondaryImageUrl)
                                <img
                                    src="{{ $secondaryImageUrl }}"
                                    alt="{{ $secondaryImage?->alt ?: $product->name }}"
                                    loading="lazy"
                                    decoding="async"
                                    class="absolute inset-0 h-44 w-full object-contain opacity-0 transition duration-500 group-hover:opacity-100"
                                />
                            @endif

                            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/10 to-transparent"></div>
                        </div>

                        <div class="space-y-3 p-3 sm:p-4">
                            <h3 class="line-clamp-2 text-sm font-semibold tracking-tight text-slate-900 sm:text-base [font-family:'Space_Grotesk',system-ui,sans-serif] group-hover:text-slate-700">
                                {{ $displayName }}
                            </h3>

                            @if ($displayType === 'variant' && $listedVariant && filled($listedVariant->color_name))
                                <div class="flex items-center gap-2">
                                    <span class="inline-block rounded-full border border-slate-300" style="width:12px;height:12px;background:{{ $listedVariant->color_hex ?: '#000000' }};"></span>
                                    <span class="text-xs font-medium uppercase tracking-[0.08em] text-slate-500">{{ $listedVariant->color_name }}</span>
                                </div>
                            @endif

                            @if ($product->short_description)
                                <p class="line-clamp-2 text-xs text-slate-500 sm:text-sm">{{ $product->short_description }}</p>
                            @endif

                            @if (!empty($featureBadges))
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($featureBadges as $badge)
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-medium uppercase tracking-[0.08em] text-slate-600 sm:text-[11px]">
                                            {{ $badge }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="border-t border-slate-100 pt-3">
                                @if ($hasDiscount)
                                    <div class="flex flex-wrap items-baseline gap-1.5 sm:gap-2">
                                        <p class="text-lg font-extrabold tracking-tight text-slate-900 sm:text-2xl [font-family:'Space_Grotesk',system-ui,sans-serif]">
                                            {{ number_format($salePrice, 2) }} {{ $currency }}
                                        </p>
                                        <p class="text-xs price-compare-old sm:text-sm">
                                            {{ number_format($basePrice, 2) }} {{ $currency }}
                                        </p>
                                    </div>
                                @else
                                    <p class="text-lg font-extrabold tracking-tight text-slate-900 sm:text-2xl [font-family:'Space_Grotesk',system-ui,sans-serif]">
                                        @if ($basePrice)
                                            {{ number_format($basePrice, 2) }} {{ $currency }}
                                        @else
                                            {{ __('ui.price_on_request') }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                    </a>
                    <div class="px-3 pb-3 sm:px-4 sm:pb-4">
                        <div class="grid gap-2 sm:grid-cols-2">
                            @if ($hasAvailableVariants)
                                <button
                                    type="button"
                                    data-product-quick-review-trigger
                                    data-product-quick-review-url="{{ route('products.quick-review', $product) }}"
                                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-full bg-gray-900 px-4 py-2 text-xs font-semibold text-white transition-colors group-hover:bg-primary-600"
                                >
                                    <i class="fa-solid fa-eye text-[10px]"></i>
                                    {{ app()->getLocale() === 'ka' ? 'სწრაფი ნახვა' : 'Quick View' }}
                                </button>
                            @else
                                <button disabled class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-full border border-red-700 bg-red-600 px-4 py-2 text-xs font-bold text-white shadow-sm">
                                    {{ app()->getLocale() === 'ka' ? 'ამოწურულია' : 'Out of Stock' }}
                                </button>
                            @endif

                            <a href="{{ $detailsUrl }}" class="inline-flex w-full items-center justify-center gap-1.5 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50">
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                {{ app()->getLocale() === 'ka' ? 'დეტალები' : 'Details' }}
                            </a>
                        </div>

                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
