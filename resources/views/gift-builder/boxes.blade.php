@extends('layouts.app')

@section('title', app()->getLocale() === 'ka' ? 'მზა სასაჩუქრე ყუთები' : 'Ready-made Gift Boxes')
@section('meta_description', app()->getLocale() === 'ka' ? 'აირჩიეთ მზა სასაჩუქრე ყუთი ან ააწყვეთ საკუთარი.' : 'Choose a ready-made gift box or build your own.')

@section('content')
    @php($isKa = app()->getLocale() === 'ka')
    <section class="bg-slate-50 py-8 sm:py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl bg-gradient-to-br from-primary-700 to-primary-500 px-6 py-8 text-white shadow-lg sm:px-10 sm:py-10">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary-100">MyTechnic Gift Box</p>
                    <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">{{ $isKa ? 'მზა სასაჩუქრე ყუთები' : 'Ready-made Gift Boxes' }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-primary-50 sm:text-base">{{ $isKa ? 'აირჩიეთ მზა კომბინაცია ან დაიწყეთ ცარიელი ყუთიდან და ყველაფერი თავად შეარჩიეთ.' : 'Choose a ready-made combination or start with an empty box and select everything yourself.' }}</p>
                    <a href="{{ route('gift-builder.show') }}" class="mt-6 inline-flex items-center gap-2 rounded-full bg-white px-5 py-3 text-sm font-bold text-primary-700 shadow-sm hover:bg-primary-50">
                        <i class="fa-solid fa-wand-magic-sparkles text-xs"></i>
                        {{ $isKa ? 'თავად ავაწყობ' : 'Build my own' }}
                    </a>
                </div>
            </div>

            <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @forelse($readyBoxes as $box)
                    <article class="flex h-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                        <div class="grid aspect-[16/10] grid-cols-2 gap-1 bg-slate-100 p-1">
                            @foreach(collect($box['items'])->take(3) as $index => $product)
                                <div class="{{ $index === 0 ? 'row-span-2' : '' }} overflow-hidden rounded-xl bg-white">
                                    <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" loading="lazy" class="h-full w-full object-cover">
                                </div>
                            @endforeach
                        </div>
                        <div class="flex flex-1 flex-col p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-primary-600">{{ count($box['items']) }} {{ $isKa ? 'პროდუქტი' : 'items' }}</p>
                                    <h2 class="mt-1 text-xl font-extrabold text-gray-950">{{ $box['label'] }}</h2>
                                </div>
                                <p class="shrink-0 text-lg font-extrabold text-primary-700">{{ number_format($box['total'], 2) }} ₾</p>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-gray-600">{{ $box['description'] }}</p>
                            <ul class="mt-4 space-y-2 text-sm text-gray-700">
                                @foreach($box['items'] as $product)
                                    <li class="flex items-center gap-2">
                                        <i class="fa-solid fa-check text-xs text-emerald-500"></i>
                                        <span class="line-clamp-1">{{ $product['name'] }}</span>
                                    </li>
                                @endforeach
                                <li class="flex items-center gap-2">
                                    <i class="fa-solid fa-gift text-xs text-primary-500"></i>
                                    <span>{{ $box['packaging_label'] }}</span>
                                </li>
                            </ul>
                            <a href="{{ $box['builder_url'] }}" class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-600 px-5 py-3 text-sm font-bold text-white hover:bg-primary-700">
                                {{ $isKa ? 'ამ ყუთის არჩევა' : 'Choose this box' }}
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-gray-500 md:col-span-2 xl:col-span-3">
                        {{ $isKa ? 'მზა ყუთები მალე დაემატება.' : 'Ready-made boxes are coming soon.' }}
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
