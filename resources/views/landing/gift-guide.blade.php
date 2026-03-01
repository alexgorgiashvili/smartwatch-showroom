@extends('layouts.app')

@php $locale = app()->getLocale(); $ka = $locale === 'ka'; @endphp

@section('title',            $ka ? 'სმარტ საათი საჩუქრად ბავშვს — GPS · SIM · ასაკი | MyTechnic' : 'Smartwatch Gift for Kids — GPS · SIM · Age Guide | MyTechnic')
@section('meta_description', $ka ? 'ბავშვის სმარტ საათი საჩუქრად: 150₾, 250₾ ან 250₾+ — ასაკისა და ბიუჯეტის მიხედვით საუკეთესო მოდელი.' : 'Best kids smartwatch gift by budget: under 150₾, 150–250₾, or premium. GPS + SIM models for every age.')
@section('robots',           'index, follow')
@section('canonical',        url('/gift-guide'))
@section('og_type',          'article')
@section('og_title',         $ka ? 'სმარტ საათი საჩუქრად ბავშვს' : 'Smartwatch Gift for Kids')
@section('og_description',   $ka ? 'GPS + SIM საათი — სრულყოფილი საჩუქარი ბავშვისთვის.' : 'GPS + SIM smartwatch — the perfect gift for a child.')
@section('og_url',           url('/gift-guide'))

@push('json_ld')
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": {{ Js::from($ka ? 'ბავშვის სმარტ საათი საჩუქრად' : 'Kids Smartwatch Gift Guide') }},
  "description": {{ Js::from($ka ? 'ბიუჯეტის მიხედვით სეგმენტირებული სმარტ საათების სია.' : 'Kids smartwatch list segmented by budget.') }},
  "url": {{ Js::from(url('/gift-guide')) }},
  "numberOfItems": {{ $budget->count() + $mid->count() + $premium->count() }},
  "itemListElement": [
    @php $pos = 1; @endphp
    @foreach($budget->merge($mid)->merge($premium) as $p)
    {
      "@type": "ListItem",
      "position": {{ $pos++ }},
      "name": {{ Js::from($p->name) }},
      "url": {{ Js::from(route('products.show', $p)) }}
    }{{ !$loop->last ? ',' : '' }}
    @endforeach
  ]
}
</script>
@endpush

@section('content')

<section class="bg-gradient-to-br from-slate-900 to-slate-800 py-16 text-white">
    <div class="mx-auto max-w-3xl px-4 text-center">
        <p class="mb-2 text-xs font-medium uppercase tracking-widest text-primary-400">MyTechnic · Gift Guide</p>
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
            {{ $ka ? 'სმარტ საათი საჩუქრად ბავშვს' : 'Smartwatch Gift for Kids' }}
        </h1>
        <p class="mt-4 text-base text-slate-300">
            {{ $ka ? 'GPS + SIM — სრულყოფილი საჩუქარი. ასაკი, ბიუჯეტი — ჩვენ გირჩევთ.' : 'GPS + SIM — the perfect gift. Age, budget — we help you choose.' }}
        </p>
    </div>
</section>

<div class="mx-auto max-w-6xl px-4 py-14 space-y-16">

    {{-- Budget tier --}}
    @if($budget->isNotEmpty())
    <section>
        <div class="mb-6 flex items-center gap-3">
            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm">≤ 150 ₾</span>
            <h2 class="text-xl font-bold text-slate-900">{{ $ka ? 'ეკონომ საჩუქარი' : 'Budget Gift' }}</h2>
        </div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            @foreach($budget as $product)
                @include('partials.listing-card', ['product' => $product])
            @endforeach
        </div>
    </section>
    @endif

    {{-- Mid tier --}}
    @if($mid->isNotEmpty())
    <section>
        <div class="mb-6 flex items-center gap-3">
            <span class="rounded-full border border-primary-200 bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 shadow-sm">151–250 ₾</span>
            <h2 class="text-xl font-bold text-slate-900">{{ $ka ? 'საშუალო საჩუქარი' : 'Mid-Range Gift' }}</h2>
        </div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            @foreach($mid as $product)
                @include('partials.listing-card', ['product' => $product])
            @endforeach
        </div>
    </section>
    @endif

    {{-- Premium tier --}}
    @if($premium->isNotEmpty())
    <section>
        <div class="mb-6 flex items-center gap-3">
            <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 shadow-sm">250+ ₾</span>
            <h2 class="text-xl font-bold text-slate-900">{{ $ka ? 'პრემიუმ საჩუქარი' : 'Premium Gift' }}</h2>
        </div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            @foreach($premium as $product)
                @include('partials.listing-card', ['product' => $product])
            @endforeach
        </div>
    </section>
    @endif

    @if($budget->isEmpty() && $mid->isEmpty() && $premium->isEmpty())
        <p class="py-16 text-center text-slate-500">{{ $ka ? 'პროდუქტი ვერ მოიძებნა.' : 'No products found.' }}</p>
    @endif

    {{-- CTA --}}
    <section class="rounded-2xl bg-slate-900 px-8 py-10 text-center text-white">
        <h2 class="text-lg font-bold">{{ $ka ? 'ყველა მოდელი' : 'All Models' }}</h2>
        <p class="mt-2 text-sm text-slate-300">{{ $ka ? 'ნახეთ სრული კატალოგი.' : 'See our full catalog.' }}</p>
        <a href="{{ route('products.index') }}" class="mt-5 inline-block rounded-full bg-white px-7 py-2.5 text-sm font-semibold text-slate-900 hover:bg-primary-50 transition">
            {{ $ka ? 'კატალოგი →' : 'Browse →' }}
        </a>
    </section>

</div>
@endsection
