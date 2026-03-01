@extends('layouts.app')

@section('title', 'Payment Failed')
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="bg-gray-50 py-14">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-rose-200 bg-white p-8 text-center shadow-sm">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">გადახდა ვერ შესრულდა</h1>
                <p class="mt-2 text-sm text-gray-600">გთხოვთ სცადოთ თავიდან ან გამოიყენოთ სხვა ბარათი.</p>

                @if (!empty($orderNumber))
                    <p class="mt-4 text-sm text-gray-700">შეკვეთის ნომერი: <span class="font-semibold">{{ $orderNumber }}</span></p>
                @endif

                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ url()->previous() }}" class="inline-flex items-center justify-center rounded-full bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                        თავიდან ცდა
                    </a>
                    <a href="{{ route('products.index') }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:border-primary-400 hover:text-primary-600">
                        კატალოგში დაბრუნება
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
