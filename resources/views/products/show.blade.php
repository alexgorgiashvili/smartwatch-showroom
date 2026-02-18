@extends('layouts.app')

@section('title', isset($product) ? $product->name : 'Product')

@section('header')
    <!-- Header component -->
@endsection

@section('content')
    <section class="bg-white">
        <div class="mx-auto max-w-screen-xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Breadcrumbs -->
            <nav class="mb-8 flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="{{ route('home') }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                            <i class="fa-solid fa-house mr-2"></i>
                            {{ __('ui.nav_home') }}
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="mx-1 h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                            <a href="{{ route('products.index') }}" class="text-sm font-medium text-gray-700 hover:text-blue-600">
                                {{ __('ui.nav_catalog') }}
                            </a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <svg class="mx-1 h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                            <span class="text-sm font-medium text-gray-500">{{ $product->name }}</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <!-- Product Grid: Image Gallery + Details -->
            <div class="grid gap-8 md:grid-cols-2">
                <!-- Image Gallery with Splide Carousel -->
                <div>
                    @if ($product->images->isNotEmpty())
                        <div id="product-splide" class="splide" aria-label="Product images">
                            <div class="splide__track">
                                <ul class="splide__list">
                                    @foreach ($product->images as $image)
                                        <li class="splide__slide">
                                            <img
                                                src="{{ $image->url }}"
                                                alt="{{ $image->alt }}"
                                                class="h-96 w-full rounded-lg object-cover shadow-md"
                                            />
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="splide__progress mt-2">
                                <div class="splide__progress__bar h-1 bg-blue-600"></div>
                            </div>
                        </div>
                    @else
                        <div class="flex h-96 w-full items-center justify-center rounded-lg bg-gray-100">
                            <div class="text-center text-gray-600">
                                <i class="fa-solid fa-image text-4xl mb-2"></i>
                                <p class="text-sm">{{ __('ui.no_image') }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Product Details -->
                <div>
                    <!-- Featured Badge -->
                    @if ($product->featured)
                        <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-blue-100 px-4 py-2">
                            <i class="fa-solid fa-star text-blue-600"></i>
                            <span class="text-sm font-medium text-blue-600">{{ __('ui.sort_featured') }}</span>
                        </div>
                    @endif

                    <!-- Product Name -->
                    <h1 class="mb-2 text-4xl font-bold text-gray-900">{{ $product->name }}</h1>

                    <!-- Short Description -->
                    @if ($product->short_description)
                        <p class="mb-6 text-lg text-gray-600">{{ $product->short_description }}</p>
                    @endif

                    <!-- Price Section -->
                    <div class="mb-8 rounded-lg border border-gray-200 bg-gray-50 p-6">
                        @php
                            $basePrice = $product->price;
                            $salePrice = $product->sale_price ?? null;
                            $hasDiscount = $salePrice !== null && $basePrice !== null && $salePrice < $basePrice;
                            $discountPercent = $hasDiscount ? (int) round((($basePrice - $salePrice) / $basePrice) * 100) : null;
                            $currency = $product->currency === 'GEL' ? '₾' : $product->currency;
                        @endphp

                        @if ($hasDiscount)
                            <div class="flex items-center gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">{{ __('ui.product_price') }}</p>
                                    <p class="text-4xl font-bold text-blue-600">
                                        {{ number_format($salePrice, 2) }} {{ $currency }}
                                    </p>
                                </div>
                                <div class="rounded-lg bg-orange-100 px-4 py-2 text-center">
                                    <p class="text-2xl font-bold text-orange-600">-{{ $discountPercent }}%</p>
                                    <p class="text-xs text-orange-700 line-through">
                                        {{ number_format($basePrice, 2) }}
                                    </p>
                                </div>
                            </div>
                        @elseif ($basePrice)
                            <p class="text-sm text-gray-600 mb-2">{{ __('ui.product_price') }}</p>
                            <p class="text-4xl font-bold text-blue-600">{{ number_format($basePrice, 2) }} {{ $currency }}</p>
                        @else
                            <p class="text-lg font-semibold text-gray-700">{{ __('ui.price_on_request') }}</p>
                        @endif
                    </div>

                    <!-- Quick Specs -->
                    <div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @if ($product->sim_support)
                            <div class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3">
                                <i class="fa-solid fa-sim-card text-xl text-blue-600"></i>
                                <div>
                                    <p class="text-xs text-gray-600">SIM</p>
                                    <p class="font-semibold text-gray-900">{{ __('ui.yes') }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($product->gps_features)
                            <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-3">
                                <i class="fa-solid fa-location-dot text-xl text-green-600"></i>
                                <div>
                                    <p class="text-xs text-gray-600">GPS</p>
                                    <p class="font-semibold text-gray-900">{{ __('ui.yes') }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($product->water_resistant)
                            <div class="flex items-center gap-2 rounded-lg border border-purple-200 bg-purple-50 p-3">
                                <i class="fa-solid fa-droplet text-xl text-purple-600"></i>
                                <div>
                                    <p class="text-xs text-gray-600">{{ __('ui.product_water') }}</p>
                                    <p class="font-semibold text-gray-900">{{ __('ui.yes') }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($product->battery_life_hours)
                            <div class="flex items-center gap-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3">
                                <i class="fa-solid fa-battery-three-quarters text-xl text-yellow-600"></i>
                                <div>
                                    <p class="text-xs text-gray-600">{{ __('ui.product_battery') }}</p>
                                    <p class="font-semibold text-gray-900">{{ $product->battery_life_hours }}h</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Inquiry Form CTA Button -->
                    <button
                        type="button"
                        onclick="document.getElementById('inquiry-form-section').scrollIntoView({ behavior: 'smooth' })"
                        class="w-full rounded-lg bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4 text-lg font-semibold text-white shadow-md hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        <i class="fa-solid fa-message mr-2"></i>
                        {{ __('ui.form_submit') }}
                    </button>
                </div>
            </div>

            <!-- Full Specifications Table -->
            <div class="mt-12 rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="mb-6 text-2xl font-bold text-gray-900">{{ __('ui.product_specs') }}</h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <tbody>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ __('ui.product_sim') }}</td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ $product->sim_support ? __('ui.yes') : __('ui.no') }}
                                </td>
                            </tr>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ __('ui.product_gps') }}</td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ $product->gps_features ? __('ui.yes') : __('ui.no') }}
                                </td>
                            </tr>
                            @if ($product->water_resistant)
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="px-4 py-3 font-semibold text-gray-900">{{ __('ui.product_water') }}</td>
                                    <td class="px-4 py-3 text-gray-700">✓ {{ __('ui.yes') }}</td>
                                </tr>
                            @endif
                            @if ($product->battery_life_hours)
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="px-4 py-3 font-semibold text-gray-900">{{ __('ui.product_battery') }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $product->battery_life_hours }} {{ __('ui.hours') }}</td>
                                </tr>
                            @endif
                            @if ($product->warranty_months)
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="px-4 py-3 font-semibold text-gray-900">{{ __('ui.product_warranty') }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $product->warranty_months }} {{ __('ui.months') }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Full Description -->
            @if ($product->description)
                <div class="mt-12">
                    <h2 class="mb-6 text-2xl font-bold text-gray-900">{{ __('ui.product_description') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        {!! nl2br(e($product->description)) !!}
                    </div>
                </div>
            @endif

            <!-- Inquiry Form Section -->
            <div id="inquiry-form-section" class="mt-12 rounded-lg border border-gray-200 bg-gray-50 p-8">
                <h2 class="mb-6 text-2xl font-bold text-gray-900">{{ __('ui.section_contact') }}</h2>
                <p class="mb-6 text-gray-600">{{ __('ui.section_contact_sub') }}</p>

                <form method="POST" action="{{ route('inquiries.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                    @if (session('status'))
                        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-green-700">
                            <i class="fa-solid fa-check-circle mr-2"></i>
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
                            <ul class="space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li><i class="fa-solid fa-exclamation-circle mr-2"></i>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-2">
                                {{ __('ui.form_name') }}
                                <span class="text-red-600">*</span>
                            </label>
                            <input
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                placeholder="მაკსიმე"
                                required
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-2">
                                {{ __('ui.form_phone') }}
                                <span class="text-red-600">*</span>
                            </label>
                            <input
                                type="text"
                                name="phone"
                                value="{{ old('phone') }}"
                                placeholder="+995 555 123 456"
                                required
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-2">
                            {{ __('ui.form_email') }}
                            {{-- <span class="text-gray-500 text-xs">(არასავალდებულო)</span> --}}
                        </label>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="example@mail.com"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-2">
                            {{ __('ui.form_message') }}
                        </label>
                        <textarea
                            name="message"
                            rows="4"
                            placeholder="დათხოვე რაითაც დაგეხმარებოდნ..."
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500"
                        ></textarea>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-blue-600 px-6 py-3 text-lg font-semibold text-white shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        <i class="fa-solid fa-paper-plane mr-2"></i>
                        {{ __('ui.form_submit') }}
                    </button>
                </form>
            </div>

            <!-- Back to Catalog Button -->
            <div class="mt-8 text-center">
                <a
                    href="{{ route('products.index') }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-gray-200 px-6 py-3 font-semibold text-gray-900 hover:bg-gray-300"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    {{ __('ui.product_back') }}
                </a>
            </div>
        </div>
    </section>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                new Splide('#product-splide', {
                    type: 'slide',
                    perPage: 1,
                    autoplay: false,
                    pagination: true,
                    arrows: true,
                    speed: 400,
                }).mount();
            });
        </script>
    @endpush
@endsection

@section('footer')
    <!-- Footer component -->
@endsection
