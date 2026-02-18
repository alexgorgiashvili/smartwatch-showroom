@extends('layouts.app')

@section('title', __('ui.brand'))

@section('header')
    <!-- Header component -->
@endsection

@section('content')
    <section class="relative overflow-hidden bg-white">
        <div class="absolute inset-0 bg-cover bg-right" style="background-image: url('{{ asset('storage/images/home/smart-watch3.jpg') }}');"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-white/95 via-white/85 to-white/20"></div>

        <div class="relative mx-auto w-screen max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8 lg:py-32">
            <div class="max-w-prose text-left">
                <h1 class="text-4xl font-bold text-gray-900 sm:text-5xl">
                    ბავშვებისთვის SIM-იანი სმარტ საათები საქართველოში
                </h1>

                <p class="mt-4 text-base text-pretty text-gray-700 sm:text-lg/relaxed">
                    დარეკვა, მდებარეობის მონიტორინგი და მუდმივი კავშირი. შერჩეული მოდელები და გამჭვირვალე მახასიათებლები.
                </p>

                <div class="mt-6 flex flex-wrap gap-4">
                    <a class="inline-block rounded border border-blue-600 bg-blue-600 px-5 py-3 font-medium text-white shadow-sm transition-colors hover:bg-blue-700" href="{{ route('products.index') }}">
                        მოდელების ნახვა
                    </a>

                </div>
            </div>
        </div>
    </section>

        <section class="py-8 bg-gray-50">
        <div class="max-w-screen-xl px-4 mx-auto">
            <div class="flex items-baseline justify-between mb-6">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight text-gray-900 sm:text-xl">პოპულარული მოდელები</h2>
                    <p class="max-w-md mt-1 text-xs text-gray-500 sm:text-sm">ყველაზე მოთხოვნადი სმარტ საათები ამ კვირაში</p>
                </div>
                <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-700 sm:text-sm">
                    ყველას ნახვა
                    <span aria-hidden="true">→</span>
                </a>
            </div>

            <div id="popular-splide" class="splide" aria-label="პოპულარული მოდელები">
                <div class="splide__track pb-10">
                    <ul class="splide__list">
                        @forelse ($featured as $product)
                            @php
                                $image = $product->primaryImage ?? $product->images->first();
                                $imageUrl = $image?->url ?: asset('storage/images/home/smart-watch3.jpg');
                                $currency = $product->currency === 'GEL' ? '₾' : $product->currency;
                                $basePrice = $product->price;
                                $salePrice = $product->sale_price ?? null;
                                $hasDiscount = $salePrice !== null
                                    && $basePrice !== null
                                    && $salePrice < $basePrice;
                                $discountPercent = $hasDiscount
                                    ? (int) round((($basePrice - $salePrice) / $basePrice) * 100)
                                    : null;
                            @endphp
                            <li class="splide__slide">
                                <div class="w-[170px] bg-white border border-gray-100 rounded-[40px] p-4 shadow-2xl shadow-blue-100/30 hover:shadow-2xl transition-all sm:w-[220px] lg:w-[260px]">
                                    <div class="relative group">
                                        @if ($hasDiscount)
                                            <span class="absolute top-3 right-3 z-10 rounded-full bg-orange-500/90 px-2.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-white shadow-sm">-{{ $discountPercent }}%</span>
                                        @endif
                                        <img src="{{ $imageUrl }}" alt="{{ $image?->alt ?: $product->name }}" class="w-full h-40 object-cover rounded-[28px] group-hover:scale-105 transition-transform duration-300 sm:h-44" />
                                    </div>
                                    <div class="mt-4">
                                        <h3 class="font-semibold text-gray-900 truncate">{{ $product->name }}</h3>
                                        <div class="mt-2">
                                            @if ($hasDiscount)
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xl font-extrabold text-blue-600 sm:text-2xl">
                                                        {{ number_format($salePrice, 2) }} {{ $currency }}
                                                    </span>
                                                    <span class="text-xs font-medium text-gray-400 line-through sm:text-sm">
                                                        {{ number_format($basePrice, 2) }} {{ $currency }}
                                                    </span>
                                                </div>
                                            @else
                                                <span class="text-xl font-extrabold text-blue-600 sm:text-2xl">
                                                    @if ($basePrice)
                                                        {{ number_format($basePrice, 2) }} {{ $currency }}
                                                    @else
                                                        ფასი მოთხოვნით
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                        <a class="mt-4 inline-flex w-full items-center justify-center rounded-full bg-gradient-to-r from-blue-500 to-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:from-blue-600 hover:to-blue-700" href="{{ route('products.show', $product) }}">
                                            დეტალურად
                                        </a>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="splide__slide">
                                <div class="w-[240px] rounded-[32px] border border-dashed border-gray-200 bg-white p-6 text-center text-sm text-gray-500">
                                    პოპულარული მოდელები მალე დაემატება.
                                </div>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="grid grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
                <div class="text-center">
                    <div class="inline-flex rounded-lg bg-gray-100 p-3 text-gray-700">
                        <i class="fa-solid fa-bolt text-2xl"></i>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-gray-900">სწრაფი რეაგირება</h3>
                    <p class="mt-2 text-sm text-pretty text-gray-700">შეტყობინებები რეალურ დროში</p>
                </div>

                <div class="text-center">
                    <div class="inline-flex rounded-lg bg-gray-100 p-3 text-gray-700">
                        <i class="fa-solid fa-shield-halved text-2xl"></i>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-gray-900">უსაფრთხოება</h3>
                    <p class="mt-2 text-sm text-pretty text-gray-700">პაროლები და მშობლის კონტროლი</p>
                </div>

                <div class="text-center">
                    <div class="inline-flex rounded-lg bg-gray-100 p-3 text-gray-700">
                        <i class="fa-solid fa-location-dot text-2xl"></i>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-gray-900">GPS ლოკაცია</h3>
                    <p class="mt-2 text-sm text-pretty text-gray-700">ბავშვის მდებარეობის მონიტორინგი</p>
                </div>
            </div>
        </div>
    </section>



    <style>
        #popular-splide .splide__pagination {
            margin-top: 16px;
            gap: 8px;
            display: flex;
            justify-content: center;
        }

        #popular-splide .splide__pagination__page {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #93c5fd;
            opacity: 1;
        }

        #popular-splide .splide__pagination__page.is-active {
            width: 24px;
            background: #2563eb;
        }
    </style>
@endsection

@section('footer')
    <!-- Footer component -->
@endsection
