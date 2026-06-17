@extends('layouts.app')

@section('title', 'შეკვეთის გაფორმება')
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="bg-gray-50 py-8 sm:py-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center gap-2 text-sm text-gray-600">
                <a href="{{ route('cart.index') }}" class="hover:text-primary-600">კალათაში დაბრუნება</a>
            </div>

            <div class="grid gap-6 lg:grid-cols-12">
                <div class="min-w-0 lg:col-span-5">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <h1 class="text-xl font-bold text-gray-900">შეკვეთის დეტალები</h1>

                        <div class="mt-4 space-y-3">
                            @foreach($giftGroups as $group)
                                @php
                                    $groupSym = ($group['currency'] ?? 'GEL') === 'GEL' ? '₾' : ($group['currency'] ?? 'GEL');
                                @endphp
                                <div class="rounded-xl border border-primary-100 bg-primary-50/50 p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-[11px] font-semibold uppercase text-primary-700">სასაჩუქრე ყუთი</p>
                                            <p class="text-sm font-bold text-gray-900">სასაჩუქრე ყუთი</p>
                                            <p class="mt-1 text-xs text-gray-600">{{ $group['items_count'] }} პროდუქტი • {{ $group['packaging_label'] }}</p>
                                        </div>
                                        <p class="shrink-0 text-sm font-bold text-primary-700">{{ number_format($group['total'], 2) }} {{ $groupSym }}</p>
                                    </div>

                                    <div class="mt-3 space-y-2">
                                        @foreach($group['items'] as $item)
                                            <div class="flex items-center gap-3 rounded-lg bg-white p-2">
                                                <img src="{{ $item['image'] }}" alt="{{ $item['product']->name }}" class="h-12 w-12 rounded-md border border-slate-200 object-cover" />
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-xs font-semibold text-gray-900">{{ $item['product']->name }}</p>
                                                    <p class="text-[11px] text-gray-500">{{ $item['variant']->name }} • {{ $item['gift_role'] === 'main' ? 'მთავარი' : 'დამატებითი' }}</p>
                                                </div>
                                                <p class="text-xs font-semibold text-gray-900">{{ number_format($item['subtotal'], 2) }} {{ $groupSym }}</p>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if($group['message'])
                                        <p class="mt-3 rounded-lg border border-primary-100 bg-white px-3 py-2 text-xs text-gray-600">“{{ $group['message'] }}”</p>
                                    @endif
                                </div>
                            @endforeach

                            @foreach($cartItems as $item)
                                <div class="flex items-start gap-3 rounded-xl border border-slate-200 p-3">
                                    <img src="{{ $item['image'] }}" alt="{{ $item['product']->name }}" class="h-16 w-16 rounded-lg border border-slate-200 object-cover" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $item['product']->name }}</p>
                                        <p class="mt-1 text-[11px] font-semibold text-primary-600">{{ $item['fulfillment_label'] }}</p>
                                        <p class="text-xs text-gray-600">{{ $item['variant']->name }} • {{ $item['quantity'] }} ც</p>
                                        <p class="mt-1 text-sm font-semibold text-primary-600">{{ number_format($item['subtotal'], 2) }} {{ $item['currency'] === 'GEL' ? '₾' : $item['currency'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between text-sm text-gray-700">
                                <span>სულ რაოდენობა</span>
                                <span class="font-semibold">{{ $cartCount }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-base text-gray-900">
                                <span class="font-semibold">სულ გადასახდელი</span>
                                <span class="text-right text-xl font-extrabold text-primary-600 sm:text-2xl">{{ number_format($cartTotal, 2) }} {{ $currencySymbol }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="min-w-0 lg:col-span-7">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="text-xl font-bold text-gray-900">მომხმარებლის მონაცემები</h2>

                        <div id="checkout-error" class="mt-4 hidden rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"></div>

                        <form id="checkout-form" class="mt-5 space-y-4">
                            @csrf

                            <div class="grid gap-3 sm:grid-cols-2">
                                <input type="text" name="customer_name" required placeholder="სახელი და გვარი *" class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500">
                                <input type="tel" name="customer_phone" required inputmode="tel" pattern="(995[0-9]{9}|5[0-9]{8})" maxlength="12" placeholder="ტელეფონი (5XXXXXXX ან 9955XXXXXXX) *" class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <input type="text" name="personal_number" required inputmode="numeric" pattern="[0-9]{11}" maxlength="11" placeholder="პირადი ნომერი *" class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500">

                            <div class="relative" id="checkout-city-picker">
                                <input type="hidden" name="city_id" id="checkout-city-id" required>
                                <input
                                    type="text"
                                    id="checkout-city-search"
                                    placeholder="ქალაქი *"
                                    autocomplete="off"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500"
                                >
                                <div id="checkout-city-results" class="absolute z-20 mt-1 hidden max-h-56 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"></div>
                            </div>

                            <textarea name="exact_address" rows="3" required placeholder="ზუსტი მისამართი *" class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500"></textarea>

                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-sm font-semibold text-gray-900">გადახდის მეთოდი</p>
                                <div class="mt-3 space-y-2">
                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                                        <input type="radio" name="payment_type" value="1" checked class="h-4 w-4 flex-shrink-0 border-gray-300 text-primary-600 focus:ring-primary-500">
                                        <div class="flex h-7 w-[100px] flex-shrink-0 items-center rounded border border-slate-200 bg-white px-1.5">
                                            <img src="{{ asset('images/payment-method/bog_geo_horizontal.png') }}" alt="BOG" class="h-full w-full object-contain" onerror="this.style.display='none'">
                                        </div>
                                        <span class="text-sm text-gray-700">ონლაინ გადახდა</span>
                                    </label>
                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                                        <input type="radio" name="payment_type" value="2" class="h-4 w-4 flex-shrink-0 border-gray-300 text-primary-600 focus:ring-primary-500">
                                        <span class="text-sm text-gray-700">კურიერთან გადახდა</span>
                                    </label>
                                </div>
                            </div>

                            <button id="checkout-submit" type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-600 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-700">
                                შეკვეთის დადასტურება
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@php
    $checkoutAnalyticsCurrency = $currencySymbol === '₾' ? 'GEL' : $currencySymbol;
    $checkoutAnalyticsContentIds = collect($cartItems)
        ->map(fn ($item) => (string) ($item['product']->id ?? $item['variant']->id ?? ''))
        ->filter()
        ->values();
    $checkoutAnalyticsContents = collect($cartItems)
        ->map(function ($item) {
            $quantity = (int) ($item['quantity'] ?? 1);
            $subtotal = (float) ($item['subtotal'] ?? 0);

            return array_filter([
                'id' => (string) ($item['product']->id ?? $item['variant']->id ?? ''),
                'quantity' => $quantity,
                'item_price' => $quantity > 0 ? round($subtotal / $quantity, 2) : null,
            ], fn ($value) => $value !== null && $value !== '');
        })
        ->values();
    $checkoutAnalyticsItems = collect($cartItems)
        ->map(function ($item) {
            $quantity = (int) ($item['quantity'] ?? 1);
            $subtotal = (float) ($item['subtotal'] ?? 0);

            return array_filter([
                'item_id' => (string) ($item['product']->id ?? $item['variant']->id ?? ''),
                'item_name' => $item['product']->name ?? null,
                'price' => $quantity > 0 ? round($subtotal / $quantity, 2) : null,
                'quantity' => $quantity,
            ], fn ($value) => $value !== null && $value !== '');
        })
        ->values();
@endphp

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.storefrontAnalytics) {
            const checkoutKey = ['initiate-checkout', @js((int) $cartCount), @js(number_format((float) $cartTotal, 2, '.', '')), @js($checkoutAnalyticsCurrency)].join(':');
            if (window.sessionStorage && !window.sessionStorage.getItem(checkoutKey)) {
                window.storefrontAnalytics.track('InitiateCheckout', {
                    currency: @js($checkoutAnalyticsCurrency),
                    value: @js((float) $cartTotal),
                    num_items: @js((int) $cartCount),
                    content_type: 'product',
                    content_ids: @json($checkoutAnalyticsContentIds),
                    contents: @json($checkoutAnalyticsContents),
                    items: @json($checkoutAnalyticsItems)
                });
                window.sessionStorage.setItem(checkoutKey, '1');
            }
        }

        const form = document.getElementById('checkout-form');
        const errorBox = document.getElementById('checkout-error');
        const submitButton = document.getElementById('checkout-submit');
        const citySearchInput = document.getElementById('checkout-city-search');
        const cityIdInput = document.getElementById('checkout-city-id');
        const cityResults = document.getElementById('checkout-city-results');
        const cities = @json($cities->map(fn ($city) => ['id' => $city->id, 'name' => $city->name])->values());

        if (!form) {
            return;
        }

        // Phone number formatting
        const phoneInput = form.querySelector('input[name="customer_phone"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');

                // Format as 995XXXXXXX or 5XXXXXXX
                if (value.length > 0) {
                    if (value.startsWith('995')) {
                        value = value.substring(0, 12);
                    } else if (value.startsWith('5')) {
                        value = value.substring(0, 9);
                    } else if (value.startsWith('0') && value.length > 1) {
                        value = value.substring(1, 10);
                    } else {
                        value = value;
                    }
                }

                e.target.value = value;
            });

            phoneInput.addEventListener('blur', function (e) {
                let value = e.target.value.replace(/\D/g, '');

                // Format on blur
                if (value.length === 9 && value.startsWith('5')) {
                    value = '995' + value;
                } else if (value.length === 12 && value.startsWith('995')) {
                    value = value;
                } else {
                    value = '';
                }

                e.target.value = value;
            });
        }

        function renderCityResults(query) {
            const normalized = (query || '').trim().toLowerCase();
            if (normalized.length === 0) {
                cityResults.classList.add('hidden');
                cityResults.innerHTML = '';
                return;
            }

            const matches = cities
                .filter(city => city.name.toLowerCase().includes(normalized))
                .slice(0, 40);

            if (matches.length === 0) {
                cityResults.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">ქალაქი ვერ მოიძებნა</div>';
                cityResults.classList.remove('hidden');
                return;
            }

            cityResults.innerHTML = matches
                .map(city => `<button type="button" data-city-id="${city.id}" data-city-name="${city.name}" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100">${city.name}</button>`)
                .join('');

            cityResults.classList.remove('hidden');
        }

        citySearchInput?.addEventListener('input', function () {
            cityIdInput.value = '';
            citySearchInput.setCustomValidity('');
            renderCityResults(citySearchInput.value);
        });

        cityResults?.addEventListener('click', function (event) {
            const target = event.target.closest('[data-city-id]');
            if (!target) {
                return;
            }

            cityIdInput.value = target.getAttribute('data-city-id') || '';
            citySearchInput.value = target.getAttribute('data-city-name') || '';
            citySearchInput.setCustomValidity('');
            cityResults.classList.add('hidden');
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('#checkout-city-picker')) {
                cityResults?.classList.add('hidden');
            }
        });

        form.addEventListener('submit', async function (event) {
            if (!cityIdInput.value) {
                event.preventDefault();
                citySearchInput.setCustomValidity('აირჩიეთ ქალაქი სიიდან');
                citySearchInput.reportValidity();
                return;
            }

            // Format phone number before submission
            const phoneInput = form.querySelector('input[name="customer_phone"]');
            if (phoneInput) {
                let phone = phoneInput.value.replace(/\D/g, '');

                // If starts with 5, add 995
                if (phone.length === 9 && phone.startsWith('5')) {
                    phone = '995' + phone;
                }

                phoneInput.value = phone;
            }

            event.preventDefault();
            errorBox.classList.add('hidden');
            errorBox.textContent = '';

            submitButton.disabled = true;
            submitButton.classList.add('opacity-60', 'cursor-not-allowed');

            const payload = Object.fromEntries(new FormData(form).entries());

            try {
                const response = await fetch('{{ route('payment.validate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (!response.ok) {
                    errorBox.textContent = data.message || 'გადახდის ინიციალიზაცია ვერ შესრულდა.';
                    errorBox.classList.remove('hidden');
                    return;
                }

                if (!data.redirect_url) {
                    errorBox.textContent = 'გადამისამართების ბმული არ დაბრუნდა.';
                    errorBox.classList.remove('hidden');
                    return;
                }

                window.location.href = data.redirect_url;
            } catch (error) {
                errorBox.textContent = 'ქსელური შეცდომა. სცადეთ თავიდან.';
                errorBox.classList.remove('hidden');
            } finally {
                submitButton.disabled = false;
                submitButton.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        });
    });
</script>
@endpush
