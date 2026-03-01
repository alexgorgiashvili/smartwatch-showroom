@extends('layouts.app')

@php $locale = app()->getLocale(); $ka = $locale === 'ka'; @endphp

@section('title',            $ka ? 'ბლოგი — სმარტ საათები · GPS · ბავშვების ტექნოლოგია | MyTechnic' : 'Blog — Smartwatches · GPS · Kids Tech | MyTechnic')
@section('meta_description', $ka ? 'სტატიები ბავშვის GPS სმარტ საათების შესახებ: SIM ბარათი, ასაკი, ფუნქციები, შედარება.' : 'Articles about kids GPS smartwatches: SIM cards, age guides, features, comparisons.')
@section('robots',           'index, follow')
@section('canonical',        url('/blog'))
@section('og_type',          'website')
@section('og_title',         $ka ? 'MyTechnic ბლოგი — სმარტ საათები' : 'MyTechnic Blog — Smartwatches')
@section('og_description',   $ka ? 'სტატიები ბავშვის GPS სმარტ საათების შესახებ.' : 'Articles about kids GPS smartwatches.')
@section('og_url',           url('/blog'))

@push('json_ld')
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Blog",
  "name": {{ Js::from($ka ? 'MyTechnic ბლოგი' : 'MyTechnic Blog') }},
  "url": {{ Js::from(url('/blog')) }},
  "publisher": { "@type": "Organization", "name": "MyTechnic", "url": "{{ url('/') }}" }
}
</script>
@endpush

@section('content')

@php
    $gradients = [
        'from-indigo-600 to-purple-600',
        'from-teal-500 to-violet-600',
        'from-rose-500 to-orange-500',
        'from-emerald-500 to-teal-600',
        'from-violet-600 to-purple-700',
        'from-purple-500 to-pink-500',
        'from-amber-500 to-red-500',
    ];
@endphp

<section class="bg-gradient-to-br from-slate-900 to-slate-800 py-16 text-white">
    <div class="mx-auto max-w-3xl px-4 text-center">
        <p class="mb-2 text-xs font-medium uppercase tracking-widest text-primary-400">MyTechnic</p>
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
            {{ $ka ? 'ბლოგი — სმარტ საათები და ბავშვების ტექნოლოგია' : 'Blog — Smartwatches & Kids Tech' }}
        </h1>
        <p class="mt-3 text-base text-slate-300 max-w-xl mx-auto">
            {{ $ka ? 'სახელმძღვანელოები, შედარებები და რჩევები GPS სმარტ საათების შესახებ.' : 'Guides, comparisons and tips about GPS smartwatches.' }}
        </p>
    </div>
</section>

<div class="mx-auto max-w-5xl px-4 py-14">
    @if($articles->isEmpty())
        <p class="py-20 text-center text-slate-500">{{ $ka ? 'სტატიები მალე დაემატება.' : 'Articles coming soon.' }}</p>
    @else
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($articles as $article)
            @php $gradient = $gradients[$loop->index % count($gradients)]; @endphp
            <article class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                <a href="{{ route('blog.show', $article) }}" class="block overflow-hidden">
                    @if($article->cover_image)
                        <img src="{{ asset('storage/' . $article->cover_image) }}" alt="{{ $article->title }}"
                             class="h-44 w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy" />
                    @else
                        {{-- Gradient placeholder with watch icon --}}
                        <div class="relative flex h-44 w-full items-center justify-center bg-gradient-to-br {{ $gradient }} transition duration-300 group-hover:scale-105">
                            <svg class="h-12 w-12 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="absolute bottom-3 left-4 right-4 text-xs font-semibold text-white/70 line-clamp-2 leading-tight">
                                {{ $article->title }}
                            </span>
                        </div>
                    @endif
                </a>
                <div class="flex flex-1 flex-col p-5 space-y-3">
                    <div class="flex items-center gap-2 text-xs text-slate-400">
                        <time datetime="{{ $article->published_at?->toDateString() }}">
                            {{ $article->published_at?->translatedFormat('d M, Y') }}
                        </time>
                        @php
                            $wordCount = str_word_count(strip_tags($article->body ?? ''));
                            $readMin   = max(1, (int) ceil($wordCount / 200));
                        @endphp
                        <span class="text-slate-300">·</span>
                        <span>{{ $readMin }} {{ $ka ? 'წთ' : 'min' }}</span>
                    </div>
                    <h2 class="text-sm font-semibold text-slate-900 leading-snug group-hover:text-primary-700">
                        <a href="{{ route('blog.show', $article) }}">{{ $article->title }}</a>
                    </h2>
                    @if($article->excerpt)
                        <p class="text-xs text-slate-500 line-clamp-3 leading-relaxed">{{ $article->excerpt }}</p>
                    @endif
                    <div class="mt-auto pt-2">
                        <a href="{{ route('blog.show', $article) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-900 hover:text-primary-600 transition">
                            {{ $ka ? 'წაიკითხე' : 'Read more' }}
                            <svg class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </a>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
