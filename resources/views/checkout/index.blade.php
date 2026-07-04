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
                                                    <div class="flex items-center gap-2 text-[11px] text-gray-500">
                                                        @if (!empty($item['color_hex']))
                                                            <span class="h-3 w-3 rounded-full border border-slate-200" style="background-color: {{ $item['color_hex'] }}"></span>
                                                        @endif
                                                        <span>{{ $item['variant_label'] }} • {{ $item['gift_role'] === 'main' ? 'მთავარი' : 'დამატებითი' }}</span>
                                                    </div>
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
                                        <div class="flex items-center gap-2 text-xs text-gray-600">
                                            @if (!empty($item['color_hex']))
                                                <span class="h-3 w-3 rounded-full border border-slate-200" style="background-color: {{ $item['color_hex'] }}"></span>
                                            @endif
                                            <span>{{ $item['variant_label'] }} • {{ $item['quantity'] }} ც</span>
                                        </div>
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
                                <label for="checkout-city-search" class="mb-1 block text-xs font-medium text-gray-500">
                                    ქალაქი
                                </label>
                                <div class="relative">
                                    <input
                                        type="text"
                                        id="checkout-city-search"
                                        placeholder="ქალაქი *"
                                        autocomplete="off"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 pr-12 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500"
                                    >
                                    <button
                                        type="button"
                                        id="checkout-city-toggle"
                                        class="absolute inline-flex items-center justify-center rounded-md text-gray-400 transition hover:bg-slate-50 hover:text-gray-600"
                                        style="top: 0; right: 0; bottom: 0; width: 2.75rem;"
                                        aria-label="ქალაქების სიის გახსნა"
                                    >
                                        <i class="fa-solid fa-chevron-down text-xs"></i>
                                    </button>
                                    <div id="checkout-city-results" class="absolute z-20 mt-1 hidden max-h-56 overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg" style="top: 100%; left: 0; right: 0;"></div>
                                </div>
                                <p class="mt-1 text-[11px] text-gray-400">აირჩიეთ სიიდან</p>
                            </div>

                            <textarea name="exact_address" rows="3" required placeholder="ზუსტი მისამართი *" class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500"></textarea>

                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-sm font-semibold text-gray-900">გადახდის მეთოდი</p>
                                <div class="mt-3 space-y-2">
                                    <label class="checkout-bog-option flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                                        <input type="radio" name="payment_type" value="1" checked class="h-4 w-4 flex-shrink-0 border-gray-300 text-primary-600 focus:ring-primary-500">
                                        <div class="checkout-bog-logo flex h-8 min-w-[112px] flex-shrink-0 items-center justify-center rounded-lg border border-orange-200 bg-orange-50 px-2 shadow-sm">
                                            <img src="{{ asset('images/payment-method/bog_geo_horizontal.png') }}" alt="საქართველოს ბანკი" class="max-h-5 w-full object-contain" onerror="this.style.display='none'">
                                        </div>
                                        <span class="text-sm text-gray-700">ონლაინ გადახდა</span>
                                    </label>
                                    <label id="courier-payment-option" class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 transition">
                                        <input type="radio" name="payment_type" value="2" class="h-4 w-4 flex-shrink-0 border-gray-300 text-primary-600 focus:ring-primary-500">
                                        <span>
                                            <span class="block text-sm font-medium text-gray-700">კურიერთან გადახდა</span>
                                            <span class="mt-0.5 block text-xs text-gray-500">ხელმისაწვდომია მხოლოდ თბილისის შეკვეთებისთვის</span>
                                        </span>
                                    </label>
                                </div>
                                <div id="courier-payment-notice" class="mt-3 hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800" role="status" aria-live="polite">
                                    <i class="fa-solid fa-location-dot mr-1.5" aria-hidden="true"></i>
                                    კურიერთან გადახდისთვის აირჩიეთ ქალაქი თბილისი. სხვა ქალაქებში შეგიძლიათ გადაიხადოთ ონლაინ.
                                </div>
                            </div>

                            <button id="checkout-submit" type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-600 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-700">
                                შეკვეთის დადასტურება
                            </button>
                            <p class="px-2 text-center text-xs leading-5 text-gray-500">
                                შეკვეთის დადასტურებით ეთანხმებით
                                <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="font-semibold text-primary-600 underline decoration-primary-200 underline-offset-2 hover:text-primary-700">მომსახურების პირობებს</a>
                                და
                                <a href="{{ route('privacy') }}" target="_blank" rel="noopener" class="font-semibold text-primary-600 underline decoration-primary-200 underline-offset-2 hover:text-primary-700">კონფიდენციალობის პოლიტიკას</a>.
                            </p>
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
        const cityToggleButton = document.getElementById('checkout-city-toggle');
        const courierPaymentInput = form?.querySelector('input[name="payment_type"][value="2"]');
        const onlinePaymentInput = form?.querySelector('input[name="payment_type"][value="1"]');
        const courierPaymentOption = document.getElementById('courier-payment-option');
        const courierPaymentNotice = document.getElementById('courier-payment-notice');
        const cities = @json($cities->map(fn ($city) => ['id' => $city->id, 'name' => $city->name])->values());
        const popularCityIds = @json($popularCityIds->values());
        const cityPageSize = 12;
        let cityVisibleLimit = cityPageSize;
        let cityCurrentQuery = '';

        if (!form) {
            return;
        }

        function isTbilisiSelected() {
            const selectedCity = cities.find(function (city) {
                return String(city.id) === String(cityIdInput?.value || '');
            });
            const normalizedName = (selectedCity?.name || '').trim().toLowerCase();

            return normalizedName === 'თბილისი'
                || normalizedName.startsWith('თბილისი >')
                || normalizedName === 'tbilisi'
                || normalizedName.startsWith('tbilisi >');
        }

        function syncCourierPaymentAvailability(showNotice = false) {
            const hasSelectedCity = Boolean(cityIdInput?.value);
            const courierAvailable = isTbilisiSelected();

            if (courierPaymentInput) {
                courierPaymentInput.disabled = hasSelectedCity && !courierAvailable;
            }
            courierPaymentOption?.classList.toggle('opacity-60', hasSelectedCity && !courierAvailable);
            courierPaymentOption?.classList.toggle('cursor-not-allowed', hasSelectedCity && !courierAvailable);

            if (hasSelectedCity && !courierAvailable && courierPaymentInput?.checked) {
                onlinePaymentInput.checked = true;
                showNotice = true;
            }

            courierPaymentNotice?.classList.toggle('hidden', !(showNotice || (hasSelectedCity && !courierAvailable)));
        }

        const phoneInput = form.querySelector('input[name="customer_phone"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');

                if (value.length > 0) {
                    if (value.startsWith('995')) {
                        value = value.substring(0, 12);
                    } else if (value.startsWith('5')) {
                        value = value.substring(0, 9);
                    } else if (value.startsWith('0') && value.length > 1) {
                        value = value.substring(1, 10);
                    }
                }

                e.target.value = value;
            });

            phoneInput.addEventListener('blur', function (e) {
                let value = e.target.value.replace(/\D/g, '');

                if (value.length === 9 && value.startsWith('5')) {
                    value = '995' + value;
                } else if (!(value.length === 12 && value.startsWith('995'))) {
                    value = '';
                }

                e.target.value = value;
            });
        }

        function filteredCities(query) {
            const normalized = (query || '').trim().toLowerCase();

            return cities.filter(function (city) {
                return normalized.length === 0 || city.name.toLowerCase().includes(normalized);
            });
        }

        function renderCityResults(query, resetLimit = true) {
            const normalized = (query || '').trim().toLowerCase();
            const previousScrollTop = cityResults ? cityResults.scrollTop : 0;

            if (resetLimit) {
                cityVisibleLimit = cityPageSize;
            }

            cityCurrentQuery = query || '';

            const matches = filteredCities(query);
            const visibleMatches = matches.slice(0, normalized.length === 0 ? cityVisibleLimit : Math.max(cityVisibleLimit, 40));

            if (visibleMatches.length === 0) {
                cityResults.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">ქალაქი ვერ მოიძებნა</div>';
                cityResults.classList.remove('hidden');
                return;
            }

            cityResults.innerHTML = visibleMatches
                .map(function (city) {
                    const isPopular = normalized.length === 0 && popularCityIds.includes(city.id);
                    const badge = isPopular
                        ? '<span class="ml-2 rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-semibold text-primary-600">პოპულარული</span>'
                        : '';

                    return '<button type="button" data-city-id="' + city.id + '" data-city-name="' + city.name + '" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"><span>' + city.name + '</span>' + badge + '</button>';
                })
                .join('');

            cityResults.classList.remove('hidden');

            if (!resetLimit) {
                cityResults.scrollTop = previousScrollTop;
            }
        }

        citySearchInput?.addEventListener('input', function () {
            cityIdInput.value = '';
            citySearchInput.setCustomValidity('');
            syncCourierPaymentAvailability();
            renderCityResults(citySearchInput.value, true);
        });

        citySearchInput?.addEventListener('focus', function () {
            renderCityResults(citySearchInput.value, true);
        });

        cityToggleButton?.addEventListener('click', function () {
            citySearchInput?.focus();
            renderCityResults(citySearchInput?.value || '', true);
        });

        cityResults?.addEventListener('scroll', function () {
            if (cityResults.classList.contains('hidden')) {
                return;
            }

            if (cityResults.scrollTop + cityResults.clientHeight < cityResults.scrollHeight - 16) {
                return;
            }

            const totalMatches = filteredCities(cityCurrentQuery).length;
            if (cityVisibleLimit >= totalMatches) {
                return;
            }

            cityVisibleLimit += cityPageSize;
            renderCityResults(cityCurrentQuery, false);
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
            syncCourierPaymentAvailability(false);
        });

        courierPaymentInput?.addEventListener('change', function () {
            syncCourierPaymentAvailability(!isTbilisiSelected());
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

            if (courierPaymentInput?.checked && !isTbilisiSelected()) {
                event.preventDefault();
                syncCourierPaymentAvailability(true);
                citySearchInput.focus();
                return;
            }

            const checkoutPhoneInput = form.querySelector('input[name="customer_phone"]');
            if (checkoutPhoneInput) {
                let phone = checkoutPhoneInput.value.replace(/\D/g, '');

                if (phone.length === 9 && phone.startsWith('5')) {
                    phone = '995' + phone;
                }

                checkoutPhoneInput.value = phone;
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
                    errorBox.textContent = 'გადასამისამართებელი ბმული არ დაბრუნდა.';
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
