@extends('layouts.app')

@php
$locale = app()->getLocale();
$ka = $locale === 'ka';
@endphp

@section('title', $ka
    ? "ბავშვის SIM სმარტ საათი {$cityName}ში — GPS, 4G, უფასო მიტანა | MyTechnic"
    : "Kids SIM Smartwatch in {$cityName} — GPS, 4G, Free Delivery | MyTechnic")

@section('meta_description', $ka
    ? "შეიძინეთ ბავშვის სმარტ საათი {$cityName}ში. 4G LTE, GPS ტრეკინგი, SOS ღილაკი. უფასო მიტანა {$cityName}ში და საქართველოს მასშტაბით. MyTechnic — ოფიციალური იმპორტიორი."
    : "Buy kids smartwatch in {$cityName}. 4G LTE, GPS tracking, SOS button. Free delivery in {$cityName} and across Georgia. MyTechnic — official importer.")

@section('canonical', route('landing.city', $city))
@section('og_type', 'website')
@section('og_title', $ka ? "ბავშვის სმარტ საათი {$cityName}ში" : "Kids Smartwatch in {$cityName}")
@section('og_description', $ka
    ? "{$cityName}ში ბავშვის სმარტ საათების ოფიციალური იმპორტიორი. GPS ტრეკინგი, 4G LTE, უფასო მიტანა."
    : "Official importer of kids smartwatches in {$cityName}. GPS tracking, 4G LTE, free delivery.")
@section('og_url', route('landing.city', $city))

@push('json_ld')
@php
$_citySchema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $ka ? "ბავშვის სმარტ საათი {$cityName}ში" : "Kids Smartwatch in {$cityName}",
    'description' => $ka
        ? "{$cityName}ში ბავშვის სმარტ საათების ოფიციალური იმპორტიორი. GPS ტრეკინგი, 4G LTE, უფასო მიტანა."
        : "Official importer of kids smartwatches in {$cityName}. GPS tracking, 4G LTE, free delivery.",
    'url' => route('landing.city', $city),
    'breadcrumb' => [
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => $ka ? 'მთავარი' : 'Home',
                'item' => url('/'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $ka ? "{$cityName}" : "{$cityName}",
            ],
        ],
    ],
];
@endphp
<script type="application/ld+json">{!! json_encode($_citySchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
@endpush

@section('content')

{{-- Hero Section --}}
<section class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 py-20 text-white">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(139,92,246,0.15),transparent_50%)]"></div>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="relative text-center">
            <p class="mb-3 text-xs font-medium uppercase tracking-widest text-primary-400">
                MyTechnic · {{ $cityName }}
            </p>
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
                @if($ka)
                    ბავშვის სმარტ საათი<br>
                    <span class="text-primary-400">{{ $cityName }}ში</span>
                @else
                    Kids Smartwatch<br>
                    <span class="text-primary-400">in {{ $cityName }}</span>
                @endif
            </h1>
            <p class="mt-6 mx-auto max-w-2xl text-lg text-slate-300 leading-relaxed">
                @if($ka)
                    4G LTE, GPS ტრეკინგი, SOS ღილაკი. უფასო მიტანა {{ $cityName }}ში და საქართველოს მასშტაბით. ოფიციალური იმპორტიორი.
                @else
                    4G LTE, GPS tracking, SOS button. Free delivery in {{ $cityName }} and across Georgia. Official importer.
                @endif
            </p>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('products.index') }}"
                   class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-8 py-3 font-semibold text-white shadow-lg transition hover:bg-primary-700">
                    <i class="fa-solid fa-store"></i>
                    {{ $ka ? 'ყველა პროდუქტი' : 'All Products' }}
                </a>
                <a href="{{ route('contact') }}"
                   class="inline-flex items-center gap-2 rounded-full border-2 border-white/30 bg-white/10 px-8 py-3 font-semibold text-white backdrop-blur-sm transition hover:bg-white/20">
                    <i class="fa-solid fa-phone"></i>
                    {{ $ka ? 'დაგვიკავშირდით' : 'Contact Us' }}
                </a>
            </div>
        </div>
    </div>
</section>

{{-- Features Section --}}
<section class="py-16 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-slate-900">
                {{ $ka ? 'რატომ MyTechnic?' : 'Why MyTechnic?' }}
            </h2>
            <p class="mt-4 text-lg text-slate-600">
                {{ $ka ? 'ოფიციალური იმპორტიორი საქართველოში' : 'Official importer in Georgia' }}
            </p>
        </div>

        <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
            {{-- Feature 1 --}}
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center transition hover:shadow-lg">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary-100">
                    <i class="fa-solid fa-truck-fast text-2xl text-primary-600"></i>
                </div>
                <h3 class="mb-2 text-lg font-semibold text-slate-900">
                    {{ $ka ? 'უფასო მიტანა' : 'Free Delivery' }}
                </h3>
                <p class="text-sm text-slate-600">
                    {{ $ka ? $cityName . '-ში და საქართველოს მასშტაბით' : 'In ' . $cityName . ' and across Georgia' }}
                </p>
            </div>

            {{-- Feature 2 --}}
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center transition hover:shadow-lg">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                    <i class="fa-solid fa-shield-halved text-2xl text-green-600"></i>
                </div>
                <h3 class="mb-2 text-lg font-semibold text-slate-900">
                    {{ $ka ? 'ოფიციალური გარანტია' : 'Official Warranty' }}
                </h3>
                <p class="text-sm text-slate-600">
                    {{ $ka ? '12 თვე გარანტია' : '12 months warranty' }}
                </p>
            </div>

            {{-- Feature 3 --}}
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center transition hover:shadow-lg">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100">
                    <i class="fa-solid fa-headset text-2xl text-blue-600"></i>
                </div>
                <h3 class="mb-2 text-lg font-semibold text-slate-900">
                    {{ $ka ? '24/7 მხარდაჭერა' : '24/7 Support' }}
                </h3>
                <p class="text-sm text-slate-600">
                    {{ $ka ? 'ქართულ ენაზე' : 'In Georgian language' }}
                </p>
            </div>

            {{-- Feature 4 --}}
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center transition hover:shadow-lg">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
                    <i class="fa-solid fa-certificate text-2xl text-amber-600"></i>
                </div>
                <h3 class="mb-2 text-lg font-semibold text-slate-900">
                    {{ $ka ? 'ორიგინალური პროდუქტი' : 'Original Product' }}
                </h3>
                <p class="text-sm text-slate-600">
                    {{ $ka ? '100% ორიგინალი' : '100% authentic' }}
                </p>
            </div>
        </div>
    </div>
</section>

{{-- Products Section --}}
@if($products->isNotEmpty())
<section class="py-16 bg-slate-50">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-slate-900">
                {{ $ka ? 'პოპულარული მოდელები' : 'Popular Models' }}
            </h2>
            <p class="mt-4 text-lg text-slate-600">
                {{ $ka ? 'ხელმისაწვდომია ' . $cityName . '-ში' : 'Available in ' . $cityName }}
            </p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($products as $product)
            <div class="group rounded-2xl border border-slate-200 bg-white p-4 transition hover:shadow-xl">
                <a href="{{ route('products.show', $product) }}" class="block">
                    <div class="aspect-square overflow-hidden rounded-xl bg-slate-100 mb-4">
                        @php
                            $firstImage = $product->images->first();
                        @endphp
                        @if($firstImage)
                        <img src="{{ $firstImage->url }}"
                             alt="{{ $firstImage->alt ?? $product->name }}"
                             class="h-full w-full object-cover transition group-hover:scale-105"
                             loading="lazy"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="flex h-full items-center justify-center text-4xl font-bold text-slate-300" style="display:none;">
                            {{ substr($product->name, 0, 1) }}
                        </div>
                        @else
                        <div class="flex h-full items-center justify-center text-4xl font-bold text-slate-300">
                            {{ substr($product->name, 0, 1) }}
                        </div>
                        @endif
                    </div>

                    <h3 class="mb-2 font-semibold text-slate-900 line-clamp-2">
                        {{ $product->name }}
                    </h3>

                    @if($product->sale_price)
                    <div class="flex items-baseline gap-2">
                        <span class="text-xl font-bold text-primary-600">{{ number_format((float)($product->sale_price ?? 0), 0) }}₾</span>
                        <span class="text-sm text-slate-400 line-through">{{ number_format((float)($product->price ?? 0), 0) }}₾</span>
                    </div>
                    @else
                    <div class="text-xl font-bold text-slate-900">{{ number_format((float)($product->price ?? 0), 0) }}₾</div>
                    @endif
                </a>
            </div>
            @endforeach
        </div>

        <div class="mt-12 text-center">
            <a href="{{ route('products.index') }}"
               class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-8 py-3 font-semibold text-white transition hover:bg-slate-800">
                {{ $ka ? 'ყველა პროდუქტის ნახვა' : 'View All Products' }}
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>
@endif

{{-- Local Info Section --}}
<section class="py-16 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl font-bold text-slate-900 mb-6">
                    {{ $ka ? 'მიტანა ' . $cityName . '-ში' : 'Delivery in ' . $cityName }}
                </h2>
                <div class="prose prose-slate max-w-none">
                    @if($ka)
                    <p class="text-lg text-slate-600 leading-relaxed">
                        MyTechnic უზრუნველყოფს სწრაფ და უფასო მიტანას {{ $cityName }}-ში.
                        შეკვეთის განთავსებიდან 1-2 სამუშაო დღეში მიიღებთ თქვენს პროდუქტს.
                    </p>
                    <ul class="mt-4 space-y-2">
                        <li class="flex items-start gap-3">
                            <i class="fa-solid fa-check text-green-600 mt-1"></i>
                            <span>უფასო მიტანა {{ $cityName }}-ის ნებისმიერ მისამართზე</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i class="fa-solid fa-check text-green-600 mt-1"></i>
                            <span>კურიერთან გადახდა შესაძლებელია</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i class="fa-solid fa-check text-green-600 mt-1"></i>
                            <span>პროდუქტის შემოწმება მიტანისას</span>
                        </li>
                    </ul>
                    @else
                    <p class="text-lg text-slate-600 leading-relaxed">
                        MyTechnic provides fast and free delivery in {{ $cityName }}.
                        You will receive your product within 1-2 business days after placing the order.
                    </p>
                    <ul class="mt-4 space-y-2">
                        <li class="flex items-start gap-3">
                            <i class="fa-solid fa-check text-green-600 mt-1"></i>
                            <span>Free delivery to any address in {{ $cityName }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i class="fa-solid fa-check text-green-600 mt-1"></i>
                            <span>Cash on delivery available</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i class="fa-solid fa-check text-green-600 mt-1"></i>
                            <span>Product inspection upon delivery</span>
                        </li>
                    </ul>
                    @endif
                </div>
            </div>

            <div>
                <h2 class="text-3xl font-bold text-slate-900 mb-6">
                    {{ $ka ? 'დაგვიკავშირდით' : 'Contact Us' }}
                </h2>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-8">
                    <div class="space-y-4">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100">
                                <i class="fa-solid fa-phone text-primary-600"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-900">{{ $ka ? 'ტელეფონი' : 'Phone' }}</h3>
                                <p class="text-slate-600">+995 XXX XXX XXX</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                                <i class="fa-brands fa-whatsapp text-green-600"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-900">WhatsApp</h3>
                                <p class="text-slate-600">{{ $ka ? 'დაწერეთ WhatsApp-ზე' : 'Message on WhatsApp' }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100">
                                <i class="fa-solid fa-location-dot text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-900">{{ $ka ? 'მისამართი' : 'Address' }}</h3>
                                <p class="text-slate-600">{{ $cityName }}, {{ $ka ? 'საქართველო' : 'Georgia' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <a href="{{ route('contact') }}"
                           class="block w-full rounded-full bg-primary-600 px-6 py-3 text-center font-semibold text-white transition hover:bg-primary-700">
                            {{ $ka ? 'კონტაქტის ფორმა' : 'Contact Form' }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- CTA Section --}}
<section class="bg-gradient-to-br from-primary-600 to-primary-800 py-16 text-white">
    <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold sm:text-4xl">
            {{ $ka ? 'მზად ხართ შეკვეთისთვის?' : 'Ready to Order?' }}
        </h2>
        <p class="mt-4 text-lg text-primary-100">
            {{ $ka
                ? 'აირჩიეთ თქვენთვის შესაფერისი მოდელი და მიიღეთ უფასო მიტანა ' . $cityName . '-ში'
                : 'Choose the right model for you and get free delivery in ' . $cityName }}
        </p>
        <div class="mt-8">
            <a href="{{ route('products.index') }}"
               class="inline-flex items-center gap-2 rounded-full bg-white px-8 py-4 font-bold text-primary-600 shadow-xl transition hover:bg-slate-50">
                {{ $ka ? 'კატალოგის ნახვა' : 'View Catalog' }}
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

@endsection
