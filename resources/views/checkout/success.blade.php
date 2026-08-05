@extends('layouts.app')

@section('title', __('storefront.payment.success_title'))
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="bg-gray-50 py-14">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-emerald-200 bg-white p-8 text-center shadow-sm">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <i class="fa-solid fa-check text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">{{ ($paymentMethod ?? '') === 'cod' ? __('storefront.payment.order_success_title') : __('storefront.payment.success_title') }}</h1>
                @if(($paymentMethod ?? '') === 'cod')
                    <p class="mt-2 text-sm text-gray-600">{{ __('storefront.payment.cod_success_text') }}</p>
                @else
                    <p class="mt-2 text-sm text-gray-600">{{ __('storefront.payment.success_text') }}</p>
                @endif

                @if (!empty($orderNumber))
                    <p class="mt-4 text-sm text-gray-700">{{ __('storefront.payment.order_number') }}: <span class="font-semibold">{{ $orderNumber }}</span></p>
                @endif

                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('products.index') }}" class="inline-flex items-center justify-center rounded-full bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                        {{ __('storefront.payment.back_to_catalog') }}
                    </a>
                    <a href="{{ route('home') }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:border-primary-400 hover:text-primary-600">
                        {{ __('storefront.payment.back_home') }}
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection

@if (!empty($purchaseEvent))
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.storefrontAnalytics) {
            return;
        }

        const eventKey = 'purchase:' + @js($purchaseEvent['transaction_id'] ?? $orderNumber);
        if (window.sessionStorage && window.sessionStorage.getItem(eventKey)) {
            return;
        }

        window.storefrontAnalytics.track('Purchase', @json($purchaseEvent));

        if (window.sessionStorage) {
            window.sessionStorage.setItem(eventKey, '1');
        }
    });
</script>
@endpush
@endif
