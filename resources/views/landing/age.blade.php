@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $title  = $locale === 'ka' ? ($config['title_ka']  ?? '') : ($config['title_en']  ?? '');
    $intro  = $locale === 'ka' ? ($config['intro_ka']  ?? '') : ($config['intro_en']  ?? '');
    $meta   = $locale === 'ka' ? ($config['meta_ka']   ?? $intro) : ($config['meta_en']   ?? $intro);
    $bullets = $locale === 'ka' ? ($config['bullet_ka'] ?? []) : ($config['bullet_en'] ?? []);
    $faqs   = $config['faqs'] ?? [];
    $canonicalUrl = url('/smartwatches/bavshvis-saati-' . $range);
@endphp

@section('title', $title . ' | MyTechnic')
@section('meta_description', $meta)
@section('robots', 'index, follow')
@section('canonical', $canonicalUrl)
@section('og_type', 'website')
@section('og_title', $title)
@section('og_description', $meta)
@section('og_url', $canonicalUrl)

@push('json_ld')
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "name": {{ Js::from($title) }},
  "description": {{ Js::from($meta) }},
  "url": {{ Js::from($canonicalUrl) }},
  "inLanguage": "ka",
  "publisher": {
    "@type": "Organization",
    "name": "MyTechnic",
    "url": "{{ url('/') }}"
  }
}
</script>
@if(!empty($faqs))
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    @foreach($faqs as $i => $faq)
    {
      "@type": "Question",
      "name": {{ Js::from($faq['q']) }},
      "acceptedAnswer": { "@type": "Answer", "text": {{ Js::from($faq['a']) }} }
    }{{ !$loop->last ? ',' : '' }}
    @endforeach
  ]
}
</script>
@endif
@endpush

@section('content')
{{-- ── Hero ─────────────────────────────────────────────────────────── --}}
<section class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 py-16 text-white">
    <div class="mx-auto max-w-4xl px-4 text-center">
        <p class="mb-2 text-xs font-medium uppercase tracking-widest text-primary-400">MyTechnic · სმარტ საათები</p>
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl md:text-5xl">{{ $title }}</h1>
        <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">{{ $intro }}</p>
        @if(!empty($bullets))
            <ul class="mx-auto mt-8 inline-grid max-w-xl grid-cols-1 gap-y-2 sm:grid-cols-2 text-left">
                @foreach($bullets as $b)
                <li class="flex items-start gap-2 text-sm text-slate-200">
                    <i class="fa-solid fa-circle-check mt-0.5 text-primary-400 flex-shrink-0"></i>
                    {{ $b }}
                </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>

{{-- ── Product Grid ─────────────────────────────────────────────────── --}}
<section class="py-14">
    <div class="mx-auto max-w-6xl px-4">
        @if($products->isEmpty())
            <p class="text-center text-slate-500 py-16">{{ $locale === 'ka' ? 'ახლა პროდუქტი არ არის ხელმისაწვდომი.' : 'No products available right now.' }}</p>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                @foreach($products as $product)
                    @include('partials.listing-card', ['product' => $product])
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- ── FAQ ──────────────────────────────────────────────────────────── --}}
@if(!empty($faqs))
<section class="bg-slate-50 py-14">
    <div class="mx-auto max-w-3xl px-4">
        <h2 class="mb-8 text-center text-2xl font-bold text-slate-900">
            {{ $locale === 'ka' ? 'ხშირად დასმული კითხვები' : 'Frequently Asked Questions' }}
        </h2>
        <div class="space-y-3" x-data="{open: null}">
            @foreach($faqs as $i => $faq)
            <div class="rounded-xl border border-slate-200 bg-white">
                <button class="flex w-full items-center justify-between px-5 py-4 text-left text-sm font-semibold text-slate-900"
                        @click="open = open === {{ $i }} ? null : {{ $i }}">
                    <span>{{ $faq['q'] }}</span>
                    <i class="fa-solid fa-chevron-down text-xs text-slate-500 transition-transform" :class="open === {{ $i }} ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open === {{ $i }}" x-collapse class="px-5 pb-4 text-sm text-slate-600 leading-relaxed">
                    {{ $faq['a'] }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ── CTA ───────────────────────────────────────────────────────────── --}}
<section class="py-12">
    <div class="mx-auto max-w-2xl px-4 text-center">
        <h2 class="text-xl font-bold text-slate-900">{{ $locale === 'ka' ? 'ყველა მოდელი' : 'All Models' }}</h2>
        <p class="mt-2 text-sm text-slate-500">{{ $locale === 'ka' ? 'ნახეთ სრული კატალოგი სხვადასხვა ასაკობრივ ჯგუფზე.' : 'Browse our full catalog across all age groups.' }}</p>
        <a href="{{ route('products.index') }}" class="mt-6 inline-block rounded-full bg-slate-900 px-7 py-3 text-sm font-semibold text-white hover:bg-primary-600 transition">
            {{ $locale === 'ka' ? 'კატალოგი →' : 'View Catalog →' }}
        </a>
    </div>
</section>
@endsection
