@extends('layouts.app')

@section('title', app()->getLocale() === 'ka' ? 'ბავშვის SIM სმარტ საათი — GPS, 4G, მშობლის კონტროლი | MyTechnic' : 'Kids SIM Smartwatch — GPS, 4G, Parental Control | MyTechnic')

@section('meta_description', app()->getLocale() === 'ka' ? 'MyTechnic — ბავშვის სმარტ საათი SIM ბარათით. 4G LTE, GPS ტრეკინგი, SOS ღილაკი, ზარები — ტელეფონის გარეშე. ოფიციალური იმპორტიორი საქართველოში. უფასო მიტანა.' : 'MyTechnic — kids SIM smartwatch with 4G GPS tracking, SOS button, calls without a phone. Official importer in Georgia. Free delivery.')
@section('canonical', url('/'))
@section('og_title', app()->getLocale() === 'ka' ? 'ბავშვის SIM სმარტ საათი საქართველოში — MyTechnic' : 'Kids SIM Smartwatch in Georgia — MyTechnic')
@section('og_description', app()->getLocale() === 'ka' ? 'ბავშვის სმარტ საათი 4G GPS-ით. მდებარეობის კონტროლი, პირდაპირი ზარი, SOS — ტელეფონის გარეშე. ოფიციალური იმპორტიორი.' : 'Kids SIM smartwatch with 4G GPS tracking. Location control, direct calls, SOS — no phone needed. Official importer.')
@section('og_url', url('/'))
@section('og_image', asset('images/og-default.jpg'))
@section('og_image_alt', 'MyTechnic SIM სმარტ საათები — ბავშვთა უსაფრთხოება')

@push('json_ld')
@php
$_homeSchema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebSite',
            '@id' => url('/') . '/#website',
            'url' => url('/'),
            'name' => 'MyTechnic',
            'description' => 'SIM-იანი სმარტ საათები ბავშვებისთვის — 4G LTE, GPS ტრეკინგი, ზარი ტელეფონის გარეშე',
            'inLanguage' => ['ka', 'en'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => url('/products') . '?search={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            '@type' => 'Organization',
            '@id' => url('/') . '/#organization',
            'name' => 'MyTechnic',
            'url' => url('/'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset('images/og-default.jpg'),
            ],
        ],
    ],
];
@endphp
<script type="application/ld+json">{!! json_encode($_homeSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
@endpush

@section('content')

    {{-- ============================================================
     HERO — Split layout: typography left, product image right
============================================================ --}}
    <section
        class="relative overflow-hidden bg-[radial-gradient(ellipse_60%_80%_at_70%_40%,rgba(219,234,254,0.4),transparent_70%)] py-16 sm:py-20 lg:py-28">
        <div class="absolute inset-0 bg-cover bg-center lg:hidden"
            style="background-image: url('{{ asset('storage/images/home/smart-watch3.jpg') }}');"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-white/96 via-white/90 to-white/96 lg:hidden"></div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">

                {{-- Left: Copy --}}
                <div class="relative max-w-xl">
                    {{-- Micro-label --}}
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-gray-900 px-3 py-1 text-xs font-bold tracking-wide text-white"
                        data-reveal="fade-up" data-reveal-delay="0">
                        <span class="inline-block size-1.5 animate-pulse rounded-full bg-primary-400"></span>
                        4G LTE &middot; GPS &middot; Call &amp; Text
                    </span>

                    {{-- Headline --}}
                    <h1 class="mt-5 text-[clamp(2.2rem,5vw,3.5rem)] font-extrabold leading-[1.08] tracking-tight text-gray-900"
                        data-reveal="fade-up" data-reveal-delay="1">
                        @if (app()->getLocale() === 'ka')
                            ბავშვის SIM-იანი სმარტ საათი<br><span class="text-primary-600">GPS · ზარები · კონტროლი.</span>
                        @else
                            Kids SIM Smartwatch<br><span class="text-primary-600">GPS · Calls · Control.</span>
                        @endif
                    </h1>

                    {{-- Sub-copy --}}
                    <p class="hidden lg:block mt-5 text-base leading-relaxed text-gray-500 sm:text-lg">
                        @if (app()->getLocale() === 'ka')
                            დარეკვა, GPS მონიტორინგი, შეტყობინებები — სმარტ საათი, რომელსაც ტელეფონი არ სჭირდება.
                        @else
                            Calls, GPS tracking, messages — a smartwatch that works completely on its own.
                        @endif
                    </p>

                    {{-- CTAs --}}
                    <div class="mt-12 flex flex-wrap items-center gap-3" data-reveal="fade-up" data-reveal-delay="3">
                        <a href="{{ route('products.index') }}"
                            class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-7 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary-200 transition-all hover:-translate-y-0.5 hover:bg-primary-700 active:translate-y-0">
                            {{ app()->getLocale() === 'ka' ? 'მოდელების ნახვა' : 'Browse Models' }}
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>

                    </div>

                    {{-- Trust micro-row --}}
                    <div class="mt-6 flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-gray-600" data-reveal="fade-up"
                        data-reveal-delay="4">
                        <span class="flex items-center gap-1.5"><i
                                class="fa-solid fa-circle-check text-primary-500"></i>{{ app()->getLocale() === 'ka' ? 'უფასო მიტანა' : 'Free Delivery' }}</span>
                        <span class="flex items-center gap-1.5"><i
                                class="fa-solid fa-circle-check text-primary-500"></i>{{ app()->getLocale() === 'ka' ? 'გარანტია' : ' Warranty' }}</span>
                        <span class="flex items-center gap-1.5"><i
                                class="fa-solid fa-circle-check text-primary-500"></i>{{ app()->getLocale() === 'ka' ? 'ოფ. იმპორტიორი' : 'Official Importer' }}</span>
                    </div>
                </div>

                {{-- Right: Product image --}}
                <div class="relative hidden items-center justify-center lg:flex lg:justify-end" data-reveal="fade-up"
                    data-reveal-delay="2">
                    <div class="absolute inset-0 -z-10 scale-90 rounded-full bg-primary-100/60 blur-3xl"></div>
                    <img src="{{ asset('storage/images/home/smart-watch3.jpg') }}"
                        alt="{{ app()->getLocale() === 'ka' ? 'MyTechnic სმარტ საათი ბავშვებისთვის' : 'MyTechnic smartwatch for kids' }}"
                        class="relative mx-auto max-h-[400px] w-full max-w-[340px] object-contain drop-shadow-[0_32px_48px_rgba(15,23,42,0.18)] transition-transform duration-700 hover:scale-[1.02] lg:max-w-[420px]" />
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================================
     TRUST BAND — Dark .tech-surface, 4 feature tiles
============================================================ --}}
    <section class="tech-surface py-16" data-reveal="fade-up">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <p class="mb-10 text-center text-xs font-semibold uppercase tracking-[0.18em] text-white/50">
                {{ app()->getLocale() === 'ka' ? 'რატომ MyTechnic' : 'Why MyTechnic' }}
            </p>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-5 lg:gap-6">
                @php
                    $trustTiles = [
                        [
                            'icon' => 'fa-tower-broadcast',
                            'title' => app()->getLocale() === 'ka' ? '4G LTE + ზარები' : '4G LTE + Calls',
                            'body' =>
                                app()->getLocale() === 'ka'
                                    ? 'ტელეფონის გარეშე დარეკვა'
                                    : 'Call & receive without a phone',
                        ],
                        [
                            'icon' => 'fa-location-dot',
                            'title' => app()->getLocale() === 'ka' ? 'GPS რეალ-ტაიმ' : 'Real-Time GPS',
                            'body' =>
                                app()->getLocale() === 'ka'
                                    ? 'ცოცხალი რუქა MyTechnic აპიდან'
                                    : 'Live map from the MyTechnic app',
                        ],
                        [
                            'icon' => 'fa-shield-halved',
                            'title' => app()->getLocale() === 'ka' ? 'IP67 წყალგამძლე' : 'IP67 Waterproof',
                            'body' =>
                                app()->getLocale() === 'ka'
                                    ? 'ყოველდღიური გამოყენებისთვის'
                                    : 'Splashproof for daily use',
                        ],

                        [
                            'icon' => 'fa-truck-fast',
                            'title' => app()->getLocale() === 'ka' ? 'უფასო მიწოდება' : 'Free Delivery',
                            'body' =>
                                app()->getLocale() === 'ka'
                                    ? 'მთელი საქართველოს მასშტაბით'
                                    : 'Across all of Georgia',
                        ],
                    ];
                @endphp

                @foreach ($trustTiles as $i => $tile)
                    <div class="glass-card p-5 text-center lg:p-6" data-reveal="fade-up"
                        data-reveal-delay="{{ $i }}">
                        <div class="mx-auto mb-3 inline-flex rounded-xl bg-white/10 p-3">
                            <i class="fa-solid {{ $tile['icon'] }} text-xl text-white/90"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-white lg:text-base">{{ $tile['title'] }}</h3>
                        <p class="mt-1 text-xs leading-relaxed text-white/55 lg:text-sm">{{ $tile['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================================================
     FEATURED PRODUCTS — Splide carousel
============================================================ --}}
    <section class="bg-white py-14" data-reveal="fade-up">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            {{-- Section header --}}
            <div class="mb-8 flex items-end justify-between">
                <div>
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">
                        {{ app()->getLocale() === 'ka' ? 'ამ კვირის არჩევანი' : "This Week's Picks" }}
                    </p>
                    <h2 class="text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl">
                        {{ app()->getLocale() === 'ka' ? 'პოპულარული მოდელები' : 'Popular Models' }}
                    </h2>
                </div>
                <a href="{{ route('products.index') }}"
                    class="hidden items-center gap-1.5 rounded-full border border-gray-200 px-4 py-1.5 text-sm font-medium text-gray-600 transition-colors hover:border-primary-400 hover:text-primary-600 sm:inline-flex">
                    {{ app()->getLocale() === 'ka' ? 'ყველა მოდელი' : 'All Models' }}
                    <i class="fa-solid fa-arrow-right text-[11px]"></i>
                </a>
            </div>

            <div id="popular-splide" class="splide"
                aria-label="{{ app()->getLocale() === 'ka' ? 'პოპულარული მოდელები' : 'Popular models' }}"
                data-reveal="fade-up" data-reveal-delay="1">
                <div class="splide__track pb-10">
                    <ul class="splide__list">
                        @forelse ($featured as $product)
                            @php
                                $image = $product->primaryImage ?? $product->images->first();
                                $imageUrl = $image?->thumbnail_url ?: asset('storage/images/home/smart-watch3.jpg');
                                $currency = $product->currency === 'GEL' ? '₾' : $product->currency;
                                $basePrice = $product->price;
                                $salePrice = $product->sale_price ?? null;
                                $hasDiscount = $salePrice !== null && $basePrice !== null && $salePrice < $basePrice;
                                $discountPct = $hasDiscount
                                    ? (int) round((($basePrice - $salePrice) / $basePrice) * 100)
                                    : null;
                            @endphp
                            <li class="splide__slide">
                                <div
                                    class="group w-[164px] overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 shadow-[0_8px_24px_rgba(15,23,42,0.08)] ring-1 ring-white/50 backdrop-blur-sm transition duration-300 hover:-translate-y-1 hover:border-slate-300 hover:shadow-[0_16px_36px_rgba(15,23,42,0.13)] sm:w-[200px] lg:w-[230px]">

                                    <a href="{{ route('products.show', $product) }}" class="block">
                                        {{-- Image --}}
                                        <div class="relative aspect-square overflow-hidden rounded-t-2xl bg-gray-50">
                                            @if ($hasDiscount)
                                                <span
                                                    class="absolute right-2.5 top-2.5 z-10 rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-600 ring-1 ring-rose-200">-{{ $discountPct }}%</span>
                                            @endif
                                            <img src="{{ $imageUrl }}" alt="{{ $image?->alt ?: $product->name }}"
                                                class="absolute inset-0 h-full w-full object-cover transition-all duration-500 group-hover:scale-[1.06]" />
                                        </div>

                                        {{-- Body --}}
                                        <div class="p-3.5 lg:p-4">
                                            <h3
                                                class="truncate text-sm font-semibold tracking-tight text-gray-900 [font-family:'Space_Grotesk',system-ui,sans-serif]">
                                                {{ $product->name }}</h3>
                                            <div class="mt-2">
                                                @if ($hasDiscount)
                                                    <div class="flex items-baseline gap-1.5">
                                                        <span
                                                            class="text-lg font-extrabold tracking-tight text-primary-600 [font-family:'Space_Grotesk',system-ui,sans-serif] sm:text-xl">{{ number_format($salePrice, 2) }}
                                                            {{ $currency }}</span>
                                                        <span
                                                            class="text-xs text-gray-400 line-through">{{ number_format($basePrice, 2) }}</span>
                                                    </div>
                                                @else
                                                    <span
                                                        class="text-lg font-extrabold tracking-tight text-gray-900 [font-family:'Space_Grotesk',system-ui,sans-serif] sm:text-xl">
                                                        @if ($basePrice)
                                                            {{ number_format($basePrice, 2) }} {{ $currency }}
                                                        @else
                                                            {{ app()->getLocale() === 'ka' ? 'ფასი მოთხოვნით' : 'Price on request' }}
                                                        @endif
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </a>

                                    {{-- Cart button --}}
                                    @php $firstInStock = $product->variants->firstWhere('quantity', '>', 0); @endphp
                                    <div class="px-3.5 pb-3.5 lg:px-4 lg:pb-4">
                                        @if ($firstInStock)
                                            <form method="POST" action="{{ route('cart.add') }}" data-cart-form>
                                                @csrf
                                                <input type="hidden" name="variant_id" value="{{ $firstInStock->id }}">
                                                <input type="hidden" name="quantity" value="1">
                                                <button type="submit"
                                                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-full bg-gray-900 px-4 py-2 text-xs font-semibold text-white transition-colors group-hover:bg-primary-600">
                                                    <i class="fa-solid fa-cart-shopping text-[10px]"></i>
                                                    {{ app()->getLocale() === 'ka' ? 'კალათაში' : 'Add to Cart' }}
                                                </button>
                                            </form>
                                        @else
                                            <button disabled
                                                class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-full bg-gray-100 px-4 py-2 text-xs font-semibold text-gray-400">
                                                {{ app()->getLocale() === 'ka' ? 'ამოიწურა' : 'Out of Stock' }}
                                            </button>
                                        @endif
                                    </div>

                                </div>
                            </li>
                        @empty
                            <li class="splide__slide">
                                <div
                                    class="w-[240px] rounded-2xl border border-dashed border-gray-200 bg-white p-6 text-center text-sm text-gray-500">
                                    {{ app()->getLocale() === 'ka' ? 'პოპულარული მოდელები მალე დაემატება.' : 'Popular models coming soon.' }}
                                </div>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="mt-5 text-center sm:hidden" data-reveal="fade-up" data-reveal-delay="2">
                <a href="{{ route('products.index') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:border-primary-300 hover:text-primary-600">
                    {{ app()->getLocale() === 'ka' ? 'ყველა მოდელის ნახვა' : 'View All Models' }}
                    <i class="fa-solid fa-arrow-right text-[11px]"></i>
                </a>
            </div>

        </div>
    </section>

    {{-- ============================================================
     GUIDES SECTION — Quick links to landing pages & blog
============================================================ --}}
    <section class="bg-slate-50 py-14 border-y border-slate-100" data-reveal="fade-up">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 text-center">
                <p class="mb-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">
                    {{ app()->getLocale() === 'ka' ? 'სახელმძღვანელოები' : 'Guides' }}
                </p>
                <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">
                    {{ app()->getLocale() === 'ka' ? 'გზამკვლევები და სტატიები' : 'Guides & Articles' }}
                </h2>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <a href="{{ route('landing.sim-guide') }}"
                   class="group flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 transition group-hover:bg-primary-100">
                        <i class="fa-solid fa-sim-card text-base"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ app()->getLocale() === 'ka' ? 'SIM ბარათის გზამკვლევი' : 'SIM Card Guide' }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">{{ app()->getLocale() === 'ka' ? 'Magti, Silknet, Cellfie — რომელი?' : 'Magti, Silknet, Cellfie — which to choose?' }}</p>
                    </div>
                    <i class="fa-solid fa-arrow-right ml-auto text-xs text-gray-300 transition group-hover:text-primary-500"></i>
                </a>
                <a href="{{ route('landing.gift-guide') }}"
                   class="group flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition group-hover:bg-amber-100">
                        <i class="fa-solid fa-gift text-base"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ app()->getLocale() === 'ka' ? 'საჩუქრის გზამკვლევი' : 'Gift Guide' }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">{{ app()->getLocale() === 'ka' ? 'ბიუჯეტის მიხედვით — 150₾, 250₾, 250₾+' : 'By budget — 150₾, 250₾, 250₾+' }}</p>
                    </div>
                    <i class="fa-solid fa-arrow-right ml-auto text-xs text-gray-300 transition group-hover:text-primary-500"></i>
                </a>
            </div>

            <div class="mt-5 text-center">
                <a href="{{ route('blog.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-primary-600">
                    <i class="fa-solid fa-newspaper text-xs"></i>
                    {{ app()->getLocale() === 'ka' ? 'სტატიები და რჩევები →' : 'Articles & Tips →' }}
                </a>
            </div>
        </div>
    </section>

    {{-- ============================================================
     FINAL CTA — Support strip
============================================================ --}}
    <section class="bg-gray-950 py-14 text-white" data-reveal="fade-up">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-8 lg:grid-cols-[1.3fr_1fr]">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-primary-300">
                        {{ app()->getLocale() === 'ka' ? 'ყიდვამდე კონსულტაცია' : 'Pre-Purchase Support' }}</p>
                    <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">
                        {{ app()->getLocale() === 'ka' ? 'არ ხარ დარწმუნებული მოდელში?' : 'Not sure which model is right?' }}
                    </h2>
                    <p class="mt-3 max-w-xl text-sm text-white/70 sm:text-base">
                        {{ app()->getLocale() === 'ka' ? 'ჩვენი გუნდი დაგეხმარება ასაკის, ბიუჯეტის და საჭირო ფუნქციების მიხედვით საუკეთესო ვარიანტის შერჩევაში.' : 'Our team helps you choose the best watch based on age, budget, and required features.' }}
                    </p>

                    <div class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-xs text-white/70 sm:text-sm">
                        <span class="inline-flex items-center gap-2"><i
                                class="fa-solid fa-circle-check text-primary-300"></i>{{ app()->getLocale() === 'ka' ? 'სწრაფი პასუხი' : 'Fast Response' }}</span>
                        <span class="inline-flex items-center gap-2"><i
                                class="fa-solid fa-circle-check text-primary-300"></i>{{ app()->getLocale() === 'ka' ? 'რეალური რჩევა' : 'Practical Advice' }}</span>
                        <span class="inline-flex items-center gap-2"><i
                                class="fa-solid fa-circle-check text-primary-300"></i>{{ app()->getLocale() === 'ka' ? 'მოდელის შედარება' : 'Model Comparison' }}</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <div class="space-y-3">
                        <a href="{{ route('contact') }}"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-600 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-700">
                            {{ app()->getLocale() === 'ka' ? 'კონსულტაციის მიღება' : 'Get Consultation' }}
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                        <a href="{{ route('products.index') }}"
                            class="inline-flex w-full items-center justify-center rounded-full border border-white/20 px-5 py-3 text-sm font-semibold text-white transition-colors hover:border-white/40 hover:bg-white/10">
                            {{ app()->getLocale() === 'ka' ? 'კატალოგის ნახვა' : 'Open Catalog' }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Splide + page styles --}}
    <style>
        #popular-splide {
            position: relative;
            padding-left: 2.75rem;
            padding-right: 2.75rem;
        }

        #popular-splide .splide__arrows {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }

        #popular-splide .splide__pagination {
            margin-top: 8px;
            gap: 6px;
            display: flex;
            justify-content: center;
        }

        #popular-splide .splide__pagination__page {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #ddd6fe;
            opacity: 1;
            transition: width .2s ease, background .2s ease;
        }

        #popular-splide .splide__pagination__page.is-active {
            width: 20px;
            background: #7c3aed;
        }

        #popular-splide .splide__arrow {
            pointer-events: auto;
            top: 50%;
            transform: translateY(-50%);
            background: #fff;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .12);
            border-radius: 999px;
            width: 2.25rem;
            height: 2.25rem;
            opacity: 1;
            transition: box-shadow .2s, transform .15s;
            z-index: 10;
        }

        #popular-splide .splide__arrow--prev {
            left: .25rem;
        }

        #popular-splide .splide__arrow--next {
            right: .25rem;
        }

        #popular-splide .splide__arrow:hover {
            box-shadow: 0 4px 16px rgba(15, 23, 42, .18);
            transform: translateY(-50%) scale(1.07);
        }

        #popular-splide .splide__arrow svg {
            width: .9rem;
            fill: #374151;
        }

        @media (max-width: 640px) {
            #popular-splide {
                padding-left: 2.25rem;
                padding-right: 2.25rem;
            }

            #popular-splide .splide__arrow {
                width: 2rem;
                height: 2rem;
            }
        }
    </style>

@endsection

@push('scripts')
    <script>
        (function() {
            var SLIDE_UP = ['translate-y-4'];
            var SLIDE_LEFT = ['translate-x-4'];

            function init(el) {
                el.classList.add('transition-all', 'duration-700', 'opacity-0');
                if ((el.dataset.reveal || '') === 'fade-left') el.classList.add(...SLIDE_LEFT);
                else el.classList.add(...SLIDE_UP);
            }

            function show(el) {
                var delay = Math.min(parseInt(el.dataset.revealDelay || '0', 10), 4) * 110;
                setTimeout(function() {
                    el.classList.remove('opacity-0', ...SLIDE_UP, ...SLIDE_LEFT);
                }, delay);
            }

            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        show(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.08
            });

            document.querySelectorAll('[data-reveal]').forEach(function(el) {
                init(el);
                observer.observe(el);
            });
        }());
    </script>
@endpush
