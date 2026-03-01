@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $ka     = $locale === 'ka';
    $wordCount = str_word_count(strip_tags($article->body ?? ''));
    $readMin   = max(1, (int) ceil($wordCount / 200));

    $gradients = [
        'from-indigo-600 to-purple-600',
        'from-teal-500 to-violet-600',
        'from-rose-500 to-orange-500',
        'from-emerald-500 to-teal-600',
        'from-violet-600 to-purple-700',
        'from-purple-500 to-pink-500',
    ];
    $gradient = $gradients[crc32($article->slug) % count($gradients)];
@endphp

@section('title',            $article->meta_title)
@section('meta_description', $article->meta_description)
@section('robots',           'index, follow')
@section('canonical',        route('blog.show', $article))
@section('og_type',          'article')
@section('og_title',         $article->title)
@section('og_description',   $article->meta_description)
@section('og_url',           route('blog.show', $article))
@if($article->cover_image)
@section('og_image',         asset('storage/' . $article->cover_image))
@section('og_image_alt',     $article->title)
@endif

@push('head_meta')
<meta property="article:published_time" content="{{ $article->published_at?->toIso8601String() }}" />
<meta property="article:modified_time" content="{{ $article->updated_at->toIso8601String() }}" />
<meta property="article:author" content="MyTechnic" />
<meta property="article:section" content="{{ $ka ? 'სმარტ საათები' : 'Smartwatches' }}" />
@endpush

@push('json_ld')
{{-- BreadcrumbList --}}
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": {{ Js::from($ka ? 'მთავარი' : 'Home') }}, "item": "{{ url('/') }}" },
    { "@type": "ListItem", "position": 2, "name": {{ Js::from($ka ? 'ბლოგი' : 'Blog') }}, "item": "{{ route('blog.index') }}" },
    { "@type": "ListItem", "position": 3, "name": {{ Js::from($article->title) }} }
  ]
}
</script>

{{-- Article / HowTo / ItemList --}}
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": {{ Js::from($article->schema_type ?? 'Article') }},
  "headline": {{ Js::from($article->title) }},
  "description": {{ Js::from($article->meta_description) }},
  "url": {{ Js::from(route('blog.show', $article)) }},
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": {{ Js::from(route('blog.show', $article)) }}
  },
  "datePublished": "{{ $article->published_at?->toIso8601String() }}",
  "dateModified": "{{ $article->updated_at->toIso8601String() }}",
  "inLanguage": "{{ $locale }}",
  "wordCount": {{ $wordCount }},
  "author": {
    "@type": "Organization",
    "name": "MyTechnic",
    "url": "{{ url('/') }}"
  },
  "publisher": {
    "@type": "Organization",
    "name": "MyTechnic",
    "url": "{{ url('/') }}",
    "logo": {
      "@type": "ImageObject",
      "url": "{{ asset('images/logo.png') }}"
    }
  }@if($article->cover_image),
  "image": {
    "@type": "ImageObject",
    "url": "{{ asset('storage/' . $article->cover_image) }}",
    "width": 1200,
    "height": 630
  }@endif
}
</script>
@endpush

@section('content')

{{-- Breadcrumb --}}
<nav class="border-b border-slate-100 bg-white">
    <div class="mx-auto max-w-5xl px-4 py-3">
        <ol class="flex flex-wrap items-center gap-1 text-xs text-slate-500">
            <li><a href="{{ url('/') }}" class="hover:text-slate-800">{{ $ka ? 'მთავარი' : 'Home' }}</a></li>
            <li class="text-slate-300">/</li>
            <li><a href="{{ route('blog.index') }}" class="hover:text-slate-800">{{ $ka ? 'ბლოგი' : 'Blog' }}</a></li>
            <li class="text-slate-300">/</li>
            <li class="text-slate-800 font-medium truncate max-w-[200px]">{{ $article->title }}</li>
        </ol>
    </div>
</nav>

<article class="relative mx-auto max-w-5xl px-4 py-10 sm:py-14">
    <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-52 bg-gradient-to-b from-primary-100/70 via-primary-50/40 to-transparent"></div>

    {{-- Cover --}}
    @if($article->cover_image)
        <div class="mx-auto mb-8 max-w-4xl overflow-hidden rounded-2xl ring-1 ring-slate-200/80 shadow-sm">
            <img src="{{ asset('storage/' . $article->cover_image) }}" alt="{{ $article->title }}"
                 class="h-56 w-full object-cover sm:h-72 lg:h-80" loading="lazy" />
        </div>
    @else
        <div class="mx-auto mb-8 flex h-48 max-w-4xl items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br {{ $gradient }} ring-1 ring-black/5 shadow-sm sm:h-64 lg:h-72">
            <svg class="h-16 w-16 text-white/25" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
    @endif

    {{-- Header --}}
    <header class="mx-auto mb-8 max-w-3xl rounded-2xl border border-slate-200/70 bg-white/90 px-5 py-5 shadow-sm backdrop-blur-sm sm:mb-10 sm:px-7 sm:py-6">
        <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-primary-200 bg-primary-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-primary-700">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-primary-500"></span>
            {{ $article->schema_type ?? 'Article' }}
        </div>
        <div class="mb-3 flex items-center gap-3 text-xs text-slate-400">
            <time datetime="{{ $article->published_at?->toDateString() }}">{{ $article->published_at?->translatedFormat('d F, Y') }}</time>
            <span class="text-slate-300">·</span>
            <span class="inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ $readMin }} {{ $ka ? 'წთ კითხვა' : 'min read' }}
            </span>
        </div>
        <h1 class="text-2xl font-extrabold leading-tight text-slate-900 sm:text-3xl lg:text-4xl">{{ $article->title }}</h1>
        @if($article->excerpt)
            <p class="mt-4 text-base leading-relaxed text-slate-500 sm:text-lg">{{ $article->excerpt }}</p>
        @endif
    </header>

    {{-- Body --}}
    <div class="article-content prose prose-slate prose-sm mx-auto max-w-3xl rounded-2xl border border-slate-200/80 bg-white px-5 py-6 shadow-sm sm:prose-base sm:px-8 sm:py-8 lg:prose-lg lg:px-10 lg:py-10 prose-headings:font-bold prose-headings:text-slate-900 prose-h2:mt-10 prose-h2:mb-4 prose-h2:border-l-4 prose-h2:border-primary-500 prose-h2:pl-3 prose-h3:text-primary-800 prose-p:text-slate-700 prose-strong:text-slate-900 prose-a:text-primary-700 prose-a:no-underline hover:prose-a:underline prose-a:decoration-primary-300 prose-li:marker:text-primary-500 prose-blockquote:border-primary-300 prose-blockquote:bg-primary-50/70 prose-blockquote:px-4 prose-blockquote:py-2 prose-code:rounded prose-code:bg-primary-50 prose-code:px-1 prose-code:text-primary-700 prose-img:rounded-xl">
        {!! $article->body !!}
    </div>

    {{-- Related --}}
    @if($related->isNotEmpty())
    <section class="mt-16 border-t border-slate-100 pt-10">
        <h2 class="mb-6 text-lg font-bold text-slate-900">{{ $ka ? 'სხვა სტატიები' : 'Related Articles' }}</h2>
        <div class="grid gap-5 sm:grid-cols-3">
            @foreach($related as $rel)
            @php $relGradient = $gradients[crc32($rel->slug) % count($gradients)]; @endphp
            <a href="{{ route('blog.show', $rel) }}" class="group block overflow-hidden rounded-xl border border-slate-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-primary-200 hover:bg-slate-50 hover:shadow-sm">
                @if($rel->cover_image)
                    <img src="{{ asset('storage/' . $rel->cover_image) }}" alt="{{ $rel->title }}" class="h-28 w-full object-cover" loading="lazy" />
                @else
                    <div class="flex h-28 w-full items-center justify-center bg-gradient-to-br {{ $relGradient }}">
                        <svg class="h-8 w-8 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                @endif
                <div class="p-4">
                    <p class="text-xs text-slate-400 mb-1">{{ $rel->published_at?->translatedFormat('d M') }}</p>
                    <h3 class="text-sm font-semibold text-slate-900 group-hover:text-primary-700 line-clamp-3 leading-snug">{{ $rel->title }}</h3>
                </div>
            </a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- CTA --}}
    <section class="mt-14 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-primary-900 px-8 py-8 text-center text-white shadow-sm">
        <h2 class="text-base font-bold">{{ $ka ? 'ბავშვის SIM სმარტ საათი — ახლა შეუკვეთე' : 'Kids SIM Smartwatch — Order Now' }}</h2>
        <p class="mt-2 text-sm text-slate-300">{{ $ka ? 'სწრაფი მიწოდება მთელ საქართველოში.' : 'Fast delivery across Georgia.' }}</p>
        <a href="{{ route('products.index') }}" class="mt-4 inline-block rounded-full bg-white px-6 py-2.5 text-sm font-semibold text-slate-900 hover:bg-primary-50 transition">
            {{ $ka ? 'კატალოგი →' : 'Browse →' }}
        </a>
    </section>

</article>
@endsection
