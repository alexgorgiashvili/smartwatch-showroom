@extends('layouts.app')

@section('title', 'Checkout')
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
                            @foreach($cartItems as $item)
                                <div class="flex items-start gap-3 rounded-xl border border-slate-200 p-3">
                                    <img src="{{ $item['image'] }}" alt="{{ $item['product']->name }}" class="h-16 w-16 rounded-lg border border-slate-200 object-cover" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $item['product']->name }}</p>
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
                                <input type="text" name="customer_phone" required placeholder="ტელეფონი *" class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500">
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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
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
                    errorBox.textContent = 'Redirect URL არ დაბრუნდა.';
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
