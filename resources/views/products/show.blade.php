@extends('layouts.app')

@section('title', $product->meta_title ?? ($product->name . ' — MyTechnic'))
@section('meta_description', $product->meta_description ?? $product->short_description ?? '')
@section('canonical', url('/products/' . $product->slug))
@section('og_type', 'product')
@section('og_title', $product->meta_title ?? $product->name)
@section('og_description', $product->meta_description ?? $product->short_description ?? '')
@section('og_url', url('/products/' . $product->slug))
@section('og_image', $product->primaryImage?->url ?? asset('images/og-default.webp'))
@section('og_image_alt', $product->name)

@push('json_ld')
@php
$_breadcrumbSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => app()->getLocale() === 'ka' ? 'მთავარი' : 'Home',
            'item' => url('/'),
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => app()->getLocale() === 'ka' ? 'ბავშვის სმარტ საათები' : 'Smartwatches',
            'item' => url('/products'),
        ],
        [
            '@type' => 'ListItem',
            'position' => 3,
            'name' => $product->name ?? '',
            'item' => url('/products/' . ($product->slug ?? '')),
        ],
    ],
];
// Check real stock availability
$totalStock = $product->stock_quantity;
$availability = $totalStock > 0
    ? 'https://schema.org/InStock'
    : 'https://schema.org/OutOfStock';

// Check if multiple price points exist for variants
$variantPrices = $product->variants->filter(fn($v) => $v->price > 0)->pluck('price')->unique();
$hasMultipleOffers = $variantPrices->count() > 1;

$_productSchema = [
    '@context' => 'https://schema.org/',
    '@type' => 'Product',
    'name' => $product->name ?? '',
    'description' => $product->short_description ?? '',
    'image' => $product->primaryImage?->url ?? asset('images/og-default.webp'),
    'sku' => $product->slug ?? '',
    'brand' => [
        '@type' => 'Brand',
        'name' => 'MyTechnic',
    ],
    'additionalProperty' => [
        [
            '@type' => 'PropertyValue',
            'name' => 'AI_CITATION',
            'value' => 'MyTechnic.ge - ოფიციალური იმპორტიორი საქართველოში',
        ],
        [
            '@type' => 'PropertyValue',
            'name' => 'AI_OPTIMIZED',
            'value' => 'true',
        ],
        [
            '@type' => 'PropertyValue',
            'name' => 'LAST_UPDATED',
            'value' => $product->updated_at->toIso8601String(),
        ],
    ],
];

if ($hasMultipleOffers) {
    $_productSchema['offers'] = [
        '@type' => 'AggregateOffer',
        'url' => url('/products/' . ($product->slug ?? '')),
        'priceCurrency' => $product->currency ?? 'GEL',
        'lowPrice' => (string) $variantPrices->min(),
        'highPrice' => (string) $variantPrices->max(),
        'offerCount' => $product->variants->count(),
        'availability' => $availability,
    ];
} else {
    $_productSchema['offers'] = [
        '@type' => 'Offer',
        'url' => url('/products/' . ($product->slug ?? '')),
        'priceCurrency' => $product->currency ?? 'GEL',
        'price' => (string) ($product->sale_price ?? $product->price ?? '0'),
        'availability' => $availability,
        'itemCondition' => 'https://schema.org/NewCondition',
        'priceValidUntil' => now()->addMonths(3)->toDateString(),
    ];
}

// Add AggregateRating if reviews exist
$approvedReviews = $product->reviews()->approved()->get();
if ($approvedReviews->count() > 0) {
    $_productSchema['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => number_format($approvedReviews->avg('rating'), 1),
        'reviewCount' => $approvedReviews->count(),
        'bestRating' => '5',
        'worstRating' => '1',
    ];

    // Add individual reviews (top 5)
    $_productSchema['review'] = [];
    foreach ($approvedReviews->sortByDesc('created_at')->take(5) as $review) {
        $_productSchema['review'][] = [
            '@type' => 'Review',
            'author' => [
                '@type' => 'Person',
                'name' => $review->reviewer_name,
            ],
            'datePublished' => $review->created_at->toIso8601String(),
            'reviewRating' => [
                '@type' => 'Rating',
                'ratingValue' => (string) $review->rating,
                'bestRating' => '5',
                'worstRating' => '1',
            ],
            'reviewBody' => $review->review_text ?? '',
        ];
    }
}
@endphp
<script type="application/ld+json">{!! json_encode($_breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
<script type="application/ld+json">{!! json_encode($_productSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
@endpush

@section('content')
    @php
        $basePrice = $product->price;
        $salePrice = $product->sale_price ?? null;
        $hasDiscount = $salePrice !== null && $basePrice !== null && $salePrice < $basePrice;
        $discountPercent = $hasDiscount ? (int) round((($basePrice - $salePrice) / $basePrice) * 100) : null;
        $currency = $product->currency === 'GEL' ? '₾' : $product->currency;
        $defaultVariant = $product->variants->first(fn ($variant) => $variant->available_quantity > 0) ?? $product->variants->first();
        $giftBuilderEligible = $defaultVariant
            && $product->gift_builder_enabled
            && $product->fulfillment_mode === 'local_stock'
            && in_array($product->gift_builder_role, ['main', 'both'], true);
        $colorVariants = $product->variants
            ->filter(fn ($variant) => filled($variant->color_name) && filled($variant->color_hex))
            ->unique(fn ($variant) => strtoupper($variant->color_hex) . '|' . mb_strtolower($variant->color_name))
            ->values();
        $defaultColor = $defaultVariant
            ? $colorVariants->firstWhere('id', $defaultVariant->id)
            : null;
        if (! $defaultColor) {
            $defaultColor = $colorVariants->first();
        }
        $selectedColorId = $defaultColor?->id;
        $variantImageMap = $variantImageMap ?? [];
        $thumbnailPaths = $product->images
            ->map(function ($image) {
                $thumbnailPath = ltrim((string) ($image->thumbnail_path ?? ''), '/');
                if ($thumbnailPath === '') {
                    return null;
                }

                return str_starts_with($thumbnailPath, 'storage/')
                    ? substr($thumbnailPath, 8)
                    : $thumbnailPath;
            })
            ->filter()
            ->values()
            ->all();
        $galleryImages = $product->images
            ->filter(function ($image) use ($thumbnailPaths) {
                $path = (string) ($image->path ?? '');
                if ($path === '') {
                    return false;
                }

                $normalizedPath = ltrim($path, '/');
                $filename = strtolower(pathinfo($normalizedPath, PATHINFO_FILENAME));
                if (str_ends_with($filename, '_thumb')) {
                    return false;
                }

                $storagePath = str_starts_with($normalizedPath, 'storage/')
                    ? substr($normalizedPath, 8)
                    : $normalizedPath;

                if (in_array($storagePath, $thumbnailPaths, true)) {
                    return false;
                }

                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                    return true;
                }

                if (str_starts_with($normalizedPath, 'storage/')) {
                    $normalizedPath = substr($normalizedPath, 8);
                }

                return \Illuminate\Support\Facades\Storage::disk('public')->exists($normalizedPath);
            })
            ->unique(fn ($image) => ltrim((string) ($image->path ?? ''), '/'))
            ->values();
    @endphp

    <section class="bg-gray-50 py-8 sm:py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <nav class="mb-6 flex" aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-1 text-sm text-gray-500">
                    <li>
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 hover:text-primary-600">
                            <i class="fa-solid fa-house text-xs"></i>{{ __('ui.nav_home') }}
                        </a>
                    </li>
                    <li><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i></li>
                    <li>
                        <a href="{{ route('products.index') }}" class="hover:text-primary-600">{{ __('ui.nav_catalog') }}</a>
                    </li>
                    <li><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i></li>
                    <li class="min-w-0 break-words text-gray-700">{{ $product->name }}</li>
                </ol>
            </nav>

            <div class="grid gap-6 lg:grid-cols-12 lg:gap-8">
                <div class="contents lg:block lg:space-y-6 lg:col-span-7">
                    @if ($galleryImages->isNotEmpty())
                        <div class="order-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:order-none">
                            <div id="product-splide" class="splide" aria-label="Product images">
                                <div class="splide__track">
                                    <ul class="splide__list">
                                        @foreach ($galleryImages as $image)
                                            <li class="splide__slide">
												<button
													type="button"
													class="group relative block w-full"
													data-product-lightbox
													data-index="{{ $loop->index }}"
													data-src="{{ $image->url }}"
													data-alt="{{ $image->alt ?: $product->name }}"
													aria-label="სურათის გადიდება"
												>
													<img
														src="{{ $image->url }}"
														alt="{{ $image->alt ?: $product->name }}"
														class="h-[340px] w-full cursor-zoom-in object-contain sm:h-[460px]"
														decoding="async"
														loading="{{ $loop->first ? 'eager' : 'lazy' }}"
														@if($loop->first) fetchpriority="high" @endif
													/>
													<span class="pointer-events-none absolute bottom-3 right-3 inline-flex size-10 items-center justify-center rounded-full bg-black/40 text-white opacity-0 backdrop-blur transition group-hover:opacity-100 group-focus-visible:opacity-100">
														<i class="fa-solid fa-up-right-and-down-left-from-center text-sm"></i>
													</span>
												</button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="order-1 flex h-[340px] w-full items-center justify-center rounded-2xl border border-slate-200 bg-white sm:h-[460px] lg:order-none">
                            <div class="text-center text-gray-500">
                                <i class="fa-solid fa-image mb-2 text-4xl"></i>
                                <p class="text-sm">{{ __('ui.no_image') }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="order-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:order-none">
                        <h2 class="mb-4 text-xl font-bold text-gray-900">{{ __('ui.product_specs') }}</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <tbody>
                                    <tr class="border-b border-gray-100">
                                        <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.product_sim') }}</td>
                                        <td class="px-2 py-3 text-gray-700">{{ $product->sim_support ? __('ui.yes') : __('ui.no') }}</td>
                                    </tr>
                                    <tr class="border-b border-gray-100">
                                        <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.product_gps') }}</td>
                                        <td class="px-2 py-3 text-gray-700">{{ $product->gps_features ? __('ui.yes') : __('ui.no') }}</td>
                                    </tr>
                                    @if ($product->water_resistant)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.product_water') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ __('ui.yes') }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->battery_life_label)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.product_battery') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->battery_life_label }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->warranty_months)
                                        <tr>
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.product_warranty') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->warranty_months }} {{ __('ui.months') }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->operating_system)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.spec_os') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->operating_system }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->screen_size)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.spec_screen_size') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->screen_size }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->display_type)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.spec_display_type') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->display_type }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->screen_resolution)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.spec_resolution') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->screen_resolution }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->battery_capacity_mah)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.spec_battery_cap') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->battery_capacity_mah }} mAh</td>
                                        </tr>
                                    @endif
                                    @if ($product->charging_time_hours)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.spec_charging') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->charging_time_hours }} h</td>
                                        </tr>
                                    @endif
                                    @if ($product->case_material)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.spec_case') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->case_material }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->band_material)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.spec_band') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->band_material }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->camera)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.spec_camera') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->camera }}</td>
                                        </tr>
                                    @endif
                                    @if (is_array($product->functions) && $product->functions !== [])
                                        <tr>
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.spec_functions') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ implode(', ', $product->functions) }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if($product->sim_support)
                    <div class="order-4 flex flex-wrap gap-2 lg:order-none">
                        <a href="{{ route('landing.sim-guide') }}"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-medium text-primary-700 hover:bg-primary-100 transition">
                            <i class="fa-solid fa-sim-card text-[10px]"></i>
                            {{ app()->getLocale() === 'ka' ? 'SIM ბარათის გზამკვლევი →' : 'SIM Card Guide →' }}
                        </a>
                        <a href="{{ route('blog.index') }}"
                           class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100 transition">
                            <i class="fa-solid fa-newspaper text-[10px]"></i>
                            {{ app()->getLocale() === 'ka' ? 'სტატიები და რჩევები →' : 'Articles & Tips →' }}
                        </a>
                    </div>
                    @endif

                    @if ($product->description)
                        <div class="order-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:order-none">
                            <h2 class="mb-4 text-xl font-bold text-gray-900">{{ __('ui.product_description') }}</h2>
                            <div class="prose prose-sm max-w-none text-gray-700">{!! nl2br(e($product->description)) !!}</div>
                        </div>
                    @endif
                </div>

                <div class="contents lg:block lg:space-y-6 lg:col-span-5">
                    <div class="order-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:order-none lg:sticky lg:top-24">
                        @if ($product->featured)
                            <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-600">
                                <i class="fa-solid fa-star"></i>{{ __('ui.sort_featured') }}
                            </div>
                        @endif

                        <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl">{{ $product->name }}</h1>

                        @if ($product->short_description)
                            <p class="mt-3 text-sm leading-relaxed text-gray-600 sm:text-base">{{ $product->short_description }}</p>
                        @endif

                        <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="mb-1 text-xs uppercase tracking-wide text-gray-500">{{ __('ui.product_price') }}</p>
                            @if ($hasDiscount)
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-3xl font-extrabold tracking-tight text-primary-600 [font-family:'Space_Grotesk',system-ui,sans-serif]">{{ number_format($salePrice, 2) }} {{ $currency }}</span>
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">-{{ $discountPercent }}%</span>
                                </div>
                                <p class="mt-2 text-sm price-compare-old inline-flex">{{ number_format($basePrice, 2) }} {{ $currency }}</p>
                            @elseif ($basePrice)
                                <p class="text-3xl font-extrabold tracking-tight text-gray-900 [font-family:'Space_Grotesk',system-ui,sans-serif]">{{ number_format($basePrice, 2) }} {{ $currency }}</p>
                            @else
                                <p class="text-lg font-semibold text-gray-700">{{ __('ui.price_on_request') }}</p>
                            @endif
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-2.5">
                            @if ($product->sim_support)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">SIM</p>
                                    <p>{{ __('ui.yes') }}</p>
                                </div>
                            @endif
                            @if ($product->gps_features)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">GPS</p>
                                    <p>{{ __('ui.yes') }}</p>
                                </div>
                            @endif
                            @if ($product->water_resistant)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">{{ __('ui.product_water') }}</p>
                                    <p>{{ __('ui.yes') }}</p>
                                </div>
                            @endif
                            @if ($product->battery_life_label)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">{{ __('ui.product_battery') }}</p>
                                    <p>{{ $product->battery_life_label }}</p>
                                </div>
                            @endif
                            @if ($product->screen_size)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">{{ __('ui.spec_screen_size') }}</p>
                                    <p>{{ $product->screen_size }}</p>
                                </div>
                            @endif
                            @if ($product->display_type)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">{{ __('ui.spec_display_type') }}</p>
                                    <p>{{ $product->display_type }}</p>
                                </div>
                            @endif

                        </div>

                        @if($colorVariants->isNotEmpty())
                            <div class="mt-6 rounded-xl border border-slate-200 bg-white p-4">
                                <p class="text-xs uppercase tracking-wide text-gray-500">{{ app()->getLocale() === 'ka' ? 'ფერი' : 'Color' }}</p>
                                <p id="selected-color-label" class="mt-1 text-sm font-semibold text-gray-900">
                                    {{ app()->getLocale() === 'ka' ? 'არჩეული' : 'Selected' }}: {{ $defaultColor->color_name }}
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-2.5">
                                    @foreach($colorVariants as $index => $variantColor)
                                        @php
                                            $variantImage = $variantImageMap[$variantColor->id] ?? null;
                                        @endphp
                                        <button
                                            type="button"
                                            class="product-color-swatch relative inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 transition focus:outline-none focus:ring-2 focus:ring-primary-500 {{ $selectedColorId === $variantColor->id ? 'ring-2 ring-primary-500 ring-offset-2' : '' }} {{ $variantColor->available_quantity <= 0 ? 'opacity-45' : '' }}"
                                            style="background-color: {{ $variantColor->color_hex }};"
                                            title="{{ $variantColor->color_name }}"
                                            data-color-name="{{ $variantColor->color_name }}"
                                            data-color-hex="{{ strtoupper($variantColor->color_hex) }}"
                                            data-variant-id="{{ $variantColor->id }}"
                                            data-stock="{{ (int) $variantColor->available_quantity }}"
                                            data-image-index="{{ $variantImage['index'] ?? '' }}"
                                            data-image-url="{{ $variantImage['thumbnail_url'] ?? $variantImage['url'] ?? '' }}"
                                            data-image-alt="{{ $variantImage['alt'] ?? $product->name }}"
                                            aria-pressed="{{ $selectedColorId === $variantColor->id ? 'true' : 'false' }}"
                                            aria-label="{{ $variantColor->color_name }}"
                                        >
                                            @if($variantColor->available_quantity <= 0)
                                                <span class="pointer-events-none absolute h-px w-9 rotate-45 bg-slate-600" aria-hidden="true"></span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                                <p id="selected-variant-stock" class="mt-3 text-sm" role="status" aria-live="polite"></p>
                            </div>
                        @endif

                        <div class="mt-6 space-y-2.5">
                            @if(session('cart_error'))
                                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                    {{ session('cart_error') }}
                                </div>
                            @endif

                            @if($defaultVariant)
                                <form method="POST" action="{{ route('cart.add') }}" id="add-to-cart-form" data-cart-form data-analytics-item-id="{{ $product->id }}" data-analytics-item-name="{{ $product->name }}" data-analytics-price="{{ (float) ($salePrice ?? $basePrice ?? 0) }}" data-analytics-currency="{{ $product->currency ?: 'GEL' }}" class="space-y-2">
                                    @csrf
                                    <input type="hidden" name="variant_id" id="selected-variant-id" value="{{ $defaultVariant->id }}">

                                    <div class="flex items-center gap-2">
                                        <label for="cart-quantity" class="text-sm font-semibold text-gray-700">რაოდენობა</label>
                                        <input
                                            id="cart-quantity"
                                            type="number"
                                            name="quantity"
                                            min="1"
                                            max="{{ max(1, min(10, (int) $defaultVariant->available_quantity)) }}"
                                            value="1"
                                            class="w-20 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                        >
                                    </div>

                                    <div class="space-y-2">
                                        <button
                                            type="submit"
                                            name="post_add_action"
                                            value="cart"
                                            data-purchase-button
                                            class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-600 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-700"
                                        >
                                            <i class="fa-solid fa-cart-shopping text-xs"></i>კალათაში დამატება
                                        </button>
                                        <button
                                            type="submit"
                                            name="post_add_action"
                                            value="checkout"
                                            data-purchase-button
                                            class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-primary-200 bg-primary-50 px-5 py-3 text-sm font-semibold text-primary-700 transition-colors hover:border-primary-300 hover:bg-primary-100"
                                        >
                                            <i class="fa-solid fa-bag-shopping text-xs"></i>შეკვეთის გაფორმება
                                        </button>
                                    </div>
                                </form>
                            @endif
                        @if($giftBuilderEligible && config('gift_builder.enabled', false))
                            <a
                                href="{{ route('gift-builder.show', ['product' => $product->slug, 'variant_id' => $defaultVariant->id]) }}"
                                id="build-gift-link"
                                    data-base-url="{{ route('gift-builder.show', ['product' => $product->slug]) }}"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-primary-200 bg-primary-50 px-5 py-3 text-sm font-semibold text-primary-700 transition-colors hover:border-primary-300 hover:bg-primary-100"
                                >
                                    <i class="fa-solid fa-box-open text-xs"></i>{{ app()->getLocale() === 'ka' ? 'საჩუქრად აწყობა' : 'Build this as a gift' }}
                                </a>
                            @endif
                            <button
                                type="button"
                                onclick="document.getElementById('inquiry-form-section').scrollIntoView({ behavior: 'smooth' })"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-gray-700 transition-colors hover:border-primary-400 hover:text-primary-600"
                            >
                                <i class="fa-solid fa-message text-xs"></i>{{ __('ui.form_submit') }}
                            </button>
                            <a href="{{ route('products.index') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-gray-700 transition-colors hover:border-primary-400 hover:text-primary-600">
                                <i class="fa-solid fa-arrow-left text-xs"></i>{{ __('ui.product_back') }}
                            </a>
                        </div>
                    </div>
                    <div id="inquiry-form-section" class="order-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:order-none">
                        <h2 class="text-xl font-bold text-gray-900">{{ __('ui.section_contact') }}</h2>
                        <p class="mt-2 text-sm text-gray-600">{{ __('ui.section_contact_sub') }}</p>

                        <form method="POST" action="{{ route('inquiries.store') }}" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <input type="hidden" name="selected_color" id="selected-color-input" value="{{ $defaultColor?->color_name }}">
                            <div aria-hidden="true" style="display:none;">
                                <label for="inquiry-website">Website</label>
                                <input id="inquiry-website" type="text" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            @if (session('status'))
                                <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                                    <i class="fa-solid fa-check-circle mr-2"></i>{{ session('status') }}
                                </div>
                            @endif

                            @if ($errors->any())
                                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                    <ul class="space-y-1">
                                        @foreach ($errors->all() as $error)
                                            <li><i class="fa-solid fa-exclamation-circle mr-2"></i>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="grid gap-3 sm:grid-cols-2">
                                <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('ui.form_name') }} *" required class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500" />
                                <input type="text" name="phone" value="{{ old('phone') }}" placeholder="{{ __('ui.form_phone') }} *" required class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500" />
                            </div>

                            <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('ui.form_email') }}" class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500" />
                            <textarea name="message" rows="4" placeholder="{{ __('ui.form_message') }}" class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500">{{ old('message') }}</textarea>

                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-gray-900 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-600">
                                <i class="fa-solid fa-paper-plane text-xs"></i>{{ __('ui.form_submit') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            @if(isset($relatedProducts) && $relatedProducts->isNotEmpty())
                <div class="mt-10">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-bold text-gray-900">{{ app()->getLocale() === 'ka' ? 'მსგავსი პროდუქტები' : 'Related Products' }}</h2>
                        <a href="{{ route('products.index') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">{{ app()->getLocale() === 'ka' ? 'ყველას ნახვა' : 'View all' }}</a>
                    </div>

                    <div id="related-products-splide" class="splide" aria-label="Related products">
                        <div class="splide__track">
                            <ul class="splide__list">
                                @foreach($relatedProducts as $related)
                                    @php
                                        $relatedImage = $related->primaryImage;
                                        $relatedBase = $related->price;
                                        $relatedSale = $related->sale_price ?? null;
                                        $relatedDiscount = $relatedSale !== null && $relatedBase !== null && $relatedSale < $relatedBase;
                                        $relatedCurrency = $related->currency === 'GEL' ? '₾' : $related->currency;
                                    @endphp
                                    <li class="splide__slide">
                                        <a href="{{ route('products.show', $related) }}" class="group block overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:-translate-y-1 hover:shadow-md">
                                            <div class="aspect-square overflow-hidden bg-gray-100">
                                                <img src="{{ $relatedImage?->url ?: asset('storage/images/home/smart-watch3.jpg') }}" alt="{{ $related->name }}" class="h-full w-full object-contain transition-transform duration-500 group-hover:scale-105" />
                                            </div>
                                            <div class="p-3">
                                                <h3 class="truncate text-sm font-semibold text-gray-900">{{ $related->name }}</h3>
                                                <div class="mt-1">
                                                    @if($relatedDiscount)
                                                        <p class="text-sm font-bold text-primary-600">{{ number_format($relatedSale, 2) }} {{ $relatedCurrency }}</p>
                                                        <p class="text-xs price-compare-old inline-flex">{{ number_format($relatedBase, 2) }} {{ $relatedCurrency }}</p>
                                                    @elseif($relatedBase)
                                                        <p class="text-sm font-bold text-gray-900">{{ number_format($relatedBase, 2) }} {{ $relatedCurrency }}</p>
                                                    @else
                                                        <p class="text-sm font-semibold text-gray-600">{{ __('ui.price_on_request') }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.storefrontAnalytics) {
                window.storefrontAnalytics.track('ViewContent', {
                    content_ids: [@js((string) $product->id)],
                    content_name: @js($product->name),
                    content_type: 'product',
                    value: @js((float) ($salePrice ?? $basePrice ?? 0)),
                    currency: @js($product->currency ?: 'GEL'),
                    contents: [{
                        id: @js((string) $product->id),
                        quantity: 1,
                        item_price: @js((float) ($salePrice ?? $basePrice ?? 0))
                    }],
                    items: [{
                        item_id: @js((string) $product->id),
                        item_name: @js($product->name),
                        price: @js((float) ($salePrice ?? $basePrice ?? 0)),
                        quantity: 1
                    }]
                });
            }

            const swatches = Array.from(document.querySelectorAll('.product-color-swatch'));
            if (!swatches.length) {
                return;
            }

            const selectedLabel = document.getElementById('selected-color-label');
            const selectedInput = document.getElementById('selected-color-input');
            const selectedVariantInput = document.getElementById('selected-variant-id');
            const quantityInput = document.getElementById('cart-quantity');
            const stockStatus = document.getElementById('selected-variant-stock');
            const purchaseButtons = Array.from(document.querySelectorAll('[data-purchase-button]'));
            const giftLink = document.getElementById('build-gift-link');
            const galleryRoot = document.getElementById('product-splide');
            const gallerySplide = galleryRoot && galleryRoot.__splide ? galleryRoot.__splide : null;
            const initialSwatch = swatches.find((swatch) => swatch.getAttribute('aria-pressed') === 'true') || swatches[0];
            const defaultImageIndex = initialSwatch ? Number.parseInt(initialSwatch.dataset.imageIndex || '0', 10) : 0;

            const syncGalleryImage = (targetSwatch) => {
                if (!gallerySplide || !targetSwatch) {
                    return;
                }

                const imageIndex = Number.parseInt(targetSwatch.dataset.imageIndex || '', 10);
                if (Number.isFinite(imageIndex)) {
                    gallerySplide.go(imageIndex);
                } else if (Number.isFinite(defaultImageIndex)) {
                    gallerySplide.go(defaultImageIndex);
                }
            };

            const setActive = (targetSwatch) => {
                swatches.forEach((swatch) => {
                    swatch.setAttribute('aria-pressed', 'false');
                    swatch.classList.remove('ring-2', 'ring-primary-500', 'ring-offset-2');
                });
                targetSwatch.classList.add('ring-2', 'ring-primary-500', 'ring-offset-2');
                targetSwatch.setAttribute('aria-pressed', 'true');

                const colorName = targetSwatch.dataset.colorName || '';
                const selectedText = "{{ app()->getLocale() === 'ka' ? 'არჩეული' : 'Selected' }}";
                if (selectedLabel) {
                    selectedLabel.textContent = `${selectedText}: ${colorName}`;
                }
                if (selectedInput) {
                    selectedInput.value = colorName;
                }

                if (selectedVariantInput && targetSwatch.dataset.variantId) {
                    selectedVariantInput.value = targetSwatch.dataset.variantId;
                }
                if (giftLink && targetSwatch.dataset.variantId) {
                    const baseUrl = giftLink.getAttribute('data-base-url') || giftLink.href;
                    giftLink.href = `${baseUrl}&variant_id=${encodeURIComponent(targetSwatch.dataset.variantId)}`;
                }

                const availableStock = Math.max(0, parseInt(targetSwatch.dataset.stock || '0', 10) || 0);
                const isOutOfStock = availableStock <= 0;
                if (quantityInput) {
                    quantityInput.disabled = isOutOfStock;
                    quantityInput.max = String(Math.max(1, Math.min(10, availableStock)));
                    if (!isOutOfStock && parseInt(quantityInput.value, 10) > availableStock) {
                        quantityInput.value = String(Math.min(10, availableStock));
                    }
                }
                purchaseButtons.forEach((button) => {
                    button.disabled = isOutOfStock;
                    button.classList.toggle('cursor-not-allowed', isOutOfStock);
                    button.classList.toggle('opacity-50', isOutOfStock);
                });
                if (stockStatus) {
                    stockStatus.textContent = isOutOfStock
                        ? @js(app()->getLocale() === 'ka' ? 'არჩეული ფერი მარაგში არ არის.' : 'The selected color is out of stock.')
                        : @js(app()->getLocale() === 'ka' ? 'არჩეული ფერი მარაგშია.' : 'The selected color is in stock.');
                    stockStatus.className = `mt-3 text-sm font-medium ${isOutOfStock ? 'text-red-600' : 'text-emerald-600'}`;
                }

                syncGalleryImage(targetSwatch);
            };

            swatches.forEach((swatch) => {
                swatch.addEventListener('click', function () {
                    setActive(this);
                });
            });

            if (initialSwatch) {
                setActive(initialSwatch);
            }
        });
    </script>
@endpush
