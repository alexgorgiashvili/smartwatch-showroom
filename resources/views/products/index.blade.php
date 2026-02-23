@extends('layouts.app')

@section('title', __('ui.catalog_title'))

@section('header')
    <!-- Header component -->
@endsection

@section('content')
    <!-- Trust Signals Bar -->
    <section class="border-b border-gray-100 bg-gradient-to-r from-blue-50 to-white">
        <div class="mx-auto max-w-screen-xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="flex items-center justify-center gap-3 sm:justify-start">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100">
                        <i class="fa-solid fa-truck-fast text-xl text-blue-600"></i>
                    </div>
                    <div class="text-center sm:text-left">
                        <p class="text-sm font-semibold text-gray-900">{{ __('ui.trust_shipping') }}</p>
                        <p class="text-xs text-gray-600">{{ __('ui.trust_shipping_text') }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-center gap-3 sm:justify-start">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                        <i class="fa-solid fa-shield-halved text-xl text-green-600"></i>
                    </div>
                    <div class="text-center sm:text-left">
                        <p class="text-sm font-semibold text-gray-900">{{ __('ui.trust_warranty') }}</p>
                        <p class="text-xs text-gray-600">{{ __('ui.trust_warranty_text') }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-center gap-3 sm:justify-start">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100">
                        <i class="fa-solid fa-headset text-xl text-purple-600"></i>
                    </div>
                    <div class="text-center sm:text-left">
                        <p class="text-sm font-semibold text-gray-900">{{ __('ui.trust_support') }}</p>
                        <p class="text-xs text-gray-600">{{ __('ui.trust_support_text') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section class="bg-white">
        <div class="mx-auto max-w-screen-xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
            <!-- Search Bar -->
            <div class="mx-auto mt-8 max-w-2xl">
                <form action="{{ route('products.index') }}" method="GET" class="flex gap-2">
                    <div class="relative flex-1">
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="{{ __('ui.search_placeholder') }}"
                            class="w-full rounded-lg border-gray-300 py-3 pl-10 pr-4 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fa-solid fa-magnifying-glass text-gray-400"></i>
                        </span>
                    </div>
                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-6 py-3 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        {{ __('ui.search') }}
                    </button>
                    @if ($search || $category)
                        <a
                            href="{{ route('products.index') }}"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            title="{{ __('ui.filter_reset') }}"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    @endif
                    <input type="hidden" name="category" value="{{ $category }}">
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                </form>
            </div>

            <!-- Category Pills/Tabs -->
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                <a
                    href="{{ route('products.index', ['search' => $search, 'sort' => request('sort')]) }}"
                    class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-medium transition {{ !$category ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >
                    <i class="fa-solid fa-border-all"></i>
                    {{ __('ui.category_all') }}
                </a>
                <a
                    href="{{ route('products.index', ['category' => 'sim', 'search' => $search, 'sort' => request('sort')]) }}"
                    class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-medium transition {{ $category === 'sim' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >
                    <i class="fa-solid fa-sim-card"></i>
                    {{ __('ui.category_sim') }}
                </a>
                <a
                    href="{{ route('products.index', ['category' => 'gps', 'search' => $search, 'sort' => request('sort')]) }}"
                    class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-medium transition {{ $category === 'gps' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >
                    <i class="fa-solid fa-location-dot"></i>
                    {{ __('ui.category_gps') }}
                </a>
                <a
                    href="{{ route('products.index', ['category' => 'new', 'search' => $search, 'sort' => request('sort')]) }}"
                    class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-medium transition {{ $category === 'new' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >
                    <i class="fa-solid fa-sparkles"></i>
                    {{ __('ui.category_new') }}
                </a>
            </div>

            <div class="mt-8 sm:mt-12">
                <div class="mb-8 flex items-center justify-between">
                    <p class="text-sm text-gray-600">
                        @if ($products->count() === 1)
                            {{ __('ui.products_count_singular', ['count' => $products->count()]) }}
                        @else
                            {{ __('ui.products_count', ['count' => $products->count()]) }}
                        @endif
                    </p>

                    <div class="flex items-center gap-2">
                        <label for="sort" class="text-sm font-medium text-gray-700">{{ __('ui.sort_by') }}:</label>
                        <select
                            id="sort"
                            name="sort"
                            onchange="window.location.href='{{ route('products.index', ['search' => $search, 'category' => $category]) }}&sort=' + this.value"
                            class="rounded-lg border-gray-300 text-sm"
                        >
                            <option value="featured" {{ request('sort') === 'featured' || !request('sort') ? 'selected' : '' }}>{{ __('ui.sort_featured') }}</option>
                            <option value="price_low" {{ request('sort') === 'price_low' ? 'selected' : '' }}>{{ __('ui.sort_price_low') }}</option>
                            <option value="price_high" {{ request('sort') === 'price_high' ? 'selected' : '' }}>{{ __('ui.sort_price_high') }}</option>
                            <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>{{ __('ui.sort_newest') }}</option>
                        </select>
                    </div>
                </div>

                @if ($products->isEmpty())
                    <div class="mt-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ __('ui.no_products') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('ui.no_products_text') }}</p>
                        <div class="mt-6">
                            <a href="{{ route('products.index') }}" class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                                <i class="fa-solid fa-arrow-rotate-left mr-2"></i>
                                {{ __('ui.filter_reset') }}
                            </a>
                        </div>
                    </div>
                @else
                    <ul class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($products as $product)
                            @php
                                $image = $product->primaryImage ?? $product->images->first();
                                $secondaryImage = $product->images->skip(1)->first();
                                $imageUrl = $image?->url ?: asset('storage/images/home/smart-watch3.jpg');
                                $secondaryImageUrl = $secondaryImage?->url;
                                $currency = $product->currency === 'GEL' ? '₾' : $product->currency;
                                $basePrice = $product->price;
                                $salePrice = $product->sale_price ?? null;
                                $hasDiscount = $salePrice !== null && $basePrice !== null && $salePrice < $basePrice;
                                $discountPercent = $hasDiscount ? (int) round((($basePrice - $salePrice) / $basePrice) * 100) : null;
                                $isNewArrival = $product->created_at && $product->created_at->greaterThan(now()->subDays(30));

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
                            @endphp
                            <li>
                                <div class="group overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 shadow-[0_10px_28px_rgba(15,23,42,0.08)] ring-1 ring-white/50 backdrop-blur-sm transition duration-300 hover:-translate-y-1 hover:border-slate-300 hover:shadow-[0_18px_40px_rgba(15,23,42,0.14)]">
                                    <a href="{{ route('products.show', $product) }}" class="block">
                                    <div class="relative isolate overflow-hidden">
                                        <div class="pointer-events-none absolute inset-x-0 top-0 z-10 flex items-start justify-between p-2 sm:p-3">
                                            <div class="flex flex-wrap gap-1.5">
                                                @if ($product->featured)
                                                    <span class="inline-flex items-center rounded-full border border-white/30 bg-slate-900/80 px-2 py-1 text-[10px] font-medium uppercase tracking-[0.12em] text-white">
                                                        Featured
                                                    </span>
                                                @elseif ($isNewArrival)
                                                    <span class="inline-flex items-center rounded-full border border-white/30 bg-white/85 px-2 py-1 text-[10px] font-medium uppercase tracking-[0.12em] text-slate-900">
                                                        New Arrival
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
                                            class="h-44 w-full object-cover transition duration-500 group-hover:scale-[1.06] {{ $secondaryImageUrl ? 'group-hover:opacity-0' : '' }}"
                                        />

                                        @if ($secondaryImageUrl)
                                            <img
                                                src="{{ $secondaryImageUrl }}"
                                                alt="{{ $secondaryImage?->alt ?: $product->name }}"
                                                class="absolute inset-0 h-44 w-full object-cover opacity-0 transition duration-500 group-hover:opacity-100"
                                            />
                                        @endif

                                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/10 to-transparent"></div>
                                    </div>

                                    <div class="space-y-3 p-3 sm:p-4">
                                        <h3 class="line-clamp-2 text-sm font-semibold tracking-tight text-slate-900 sm:text-base [font-family:'Space_Grotesk',system-ui,sans-serif] group-hover:text-slate-700">
                                            {{ $product->name }}
                                        </h3>

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
                                                    <p class="text-xs text-slate-400 line-through sm:text-sm">
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
                                    @php $firstInStock = $product->variants->firstWhere('quantity', '>', 0); @endphp
                                    <div class="px-3 pb-3 sm:px-4 sm:pb-4">
                                        @if($firstInStock)
                                            <form method="POST" action="{{ route('cart.add') }}" data-cart-form>
                                                @csrf
                                                <input type="hidden" name="variant_id" value="{{ $firstInStock->id }}">
                                                <input type="hidden" name="quantity" value="1">
                                                <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-full bg-gray-900 px-4 py-2 text-xs font-semibold text-white transition-colors group-hover:bg-primary-600">
                                                    <i class="fa-solid fa-cart-shopping text-[10px]"></i>
                                                    {{ app()->getLocale() === 'ka' ? 'კალათაში' : 'Add to Cart' }}
                                                </button>
                                            </form>
                                        @else
                                            <button disabled class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-full bg-gray-100 px-4 py-2 text-xs font-semibold text-gray-400">
                                                {{ app()->getLocale() === 'ka' ? 'მარაგი ამოიწურა' : 'Out of Stock' }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>
@endsection

@section('footer')
    <!-- Footer component -->
@endsection

