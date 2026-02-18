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
                    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($products as $product)
                            @php
                                $image = $product->primaryImage ?? $product->images->first();
                                $imageUrl = $image?->url ?: asset('storage/images/home/smart-watch3.jpg');
                                $currency = $product->currency === 'GEL' ? '₾' : $product->currency;
                                $basePrice = $product->price;
                                $salePrice = $product->sale_price ?? null;
                                $hasDiscount = $salePrice !== null && $basePrice !== null && $salePrice < $basePrice;
                                $discountPercent = $hasDiscount ? (int) round((($basePrice - $salePrice) / $basePrice) * 100) : null;
                            @endphp
                            <li>
                                <a href="{{ route('products.show', $product) }}" class="group block overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm transition hover:shadow-lg">
                                    <div class="relative">
                                        @if ($hasDiscount)
                                            <span class="absolute right-3 top-3 z-10 rounded-full bg-orange-500 px-3 py-1 text-xs font-semibold text-white shadow-md">
                                                -{{ $discountPercent }}%
                                            </span>
                                        @endif
                                        @if ($product->featured)
                                            <span class="absolute left-3 top-3 z-10 rounded-full bg-blue-500 px-3 py-1 text-xs font-semibold text-white shadow-md">
                                                <i class="fa-solid fa-star"></i>
                                            </span>
                                        @endif
                                        <img
                                            src="{{ $imageUrl }}"
                                            alt="{{ $image?->alt ?: $product->name }}"
                                            class="h-64 w-full object-cover transition duration-500 group-hover:scale-105"
                                        />
                                    </div>

                                    <div class="p-4">
                                        <h3 class="text-base font-semibold text-gray-900 group-hover:text-blue-600">
                                            {{ $product->name }}
                                        </h3>

                                        @if ($product->short_description)
                                            <p class="mt-2 text-sm text-gray-500 line-clamp-2">{{ $product->short_description }}</p>
                                        @endif

                                        <div class="mt-3 flex items-center gap-4 text-xs text-gray-500">
                                            @if ($product->sim_support)
                                                <span class="flex items-center gap-1">
                                                    <i class="fa-solid fa-sim-card text-blue-600"></i>
                                                    <span>SIM</span>
                                                </span>
                                            @endif
                                            @if ($product->gps_features)
                                                <span class="flex items-center gap-1">
                                                    <i class="fa-solid fa-location-dot text-green-600"></i>
                                                    <span>GPS</span>
                                                </span>
                                            @endif
                                            @if ($product->warranty_months)
                                                <span class="flex items-center gap-1">
                                                    <i class="fa-solid fa-shield text-purple-600"></i>
                                                    <span>{{ $product->warranty_months }} {{ __('ui.months') }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-4 border-t border-gray-100 pt-4">
                                            @if ($hasDiscount)
                                                <div class="flex items-center gap-2">
                                                    <p class="text-xl font-bold text-blue-600">
                                                        {{ number_format($salePrice, 2) }} {{ $currency }}
                                                    </p>
                                                    <p class="text-sm text-gray-400 line-through">
                                                        {{ number_format($basePrice, 2) }} {{ $currency }}
                                                    </p>
                                                </div>
                                            @else
                                                <p class="text-xl font-bold text-blue-600">
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

