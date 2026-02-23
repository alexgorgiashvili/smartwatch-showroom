@extends('layouts.app')

@section('title', 'კალათა')

@section('content')
    <section class="bg-gray-50 py-8 sm:py-10">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

            {{-- Page heading --}}
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">კალათა</h1>
                    <p class="text-sm text-gray-500"><span data-cart-count>{{ $cartCount }}</span> პოზიცია</p>
                </div>
                <a href="{{ route('products.index') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                    <i class="fa-solid fa-arrow-left mr-1 text-xs"></i>კატალოგი
                </a>
            </div>

            {{-- Flash messages --}}
            @if(session('cart_status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('cart_status') }}
                </div>
            @endif
            @if(session('cart_error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ session('cart_error') }}
                </div>
            @endif

            @if($cartItems->isEmpty())
                {{-- Empty state --}}
                <div class="rounded-2xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm">
                    <i class="fa-solid fa-cart-shopping mb-4 text-4xl text-gray-300"></i>
                    <p class="text-lg font-semibold text-gray-800">კალათა ცარიელია</p>
                    <p class="mt-1 text-sm text-gray-500">დაამატეთ პროდუქტი და გააგრძელეთ შეძენა.</p>
                    <a href="{{ route('products.index') }}" class="mt-6 inline-flex items-center gap-2 rounded-full bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                        <i class="fa-solid fa-shop text-xs"></i>პროდუქტების ნახვა
                    </a>
                </div>
            @else
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start">

                    {{-- Cart items list --}}
                    <div class="flex-1 min-w-0">
                        <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            @foreach($cartItems as $item)
                                @php $sym = $item['currency'] === 'GEL' ? '₾' : $item['currency']; @endphp
                                <div class="flex items-center gap-4 p-4 sm:p-5" data-cart-row data-variant-id="{{ $item['variant']->id }}">

                                    {{-- Product image --}}
                                    <a href="{{ route('products.show', $item['product']) }}" class="block flex-shrink-0">
                                        <img
                                            src="{{ $item['image'] }}"
                                            alt="{{ $item['product']->name }}"
                                            class="h-16 w-16 rounded-xl border border-slate-100 object-cover sm:h-20 sm:w-20"
                                        >
                                    </a>

                                    {{-- Name + variant + unit price --}}
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('products.show', $item['product']) }}" class="block truncate text-sm font-semibold text-gray-900 hover:text-primary-600 sm:text-base">
                                            {{ $item['product']->name }}
                                        </a>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $item['variant']->name }}</p>
                                        <p class="mt-1 text-xs font-medium text-gray-600">{{ number_format($item['unit_price'], 2) }} {{ $sym }} / ცალი</p>
                                    </div>

                                    {{-- Qty + subtotal + remove --}}
                                    <div class="flex flex-shrink-0 flex-col items-end gap-2">
                                        {{-- Subtotal --}}
                                        <p class="text-sm font-bold text-gray-900" data-item-subtotal>{{ number_format($item['subtotal'], 2) }} {{ $sym }}</p>

                                        {{-- Qty update form --}}
                                        <form method="POST" action="{{ route('cart.update') }}" class="flex items-center gap-1.5" data-cart-update-form>
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="variant_id" value="{{ $item['variant']->id }}">
                                            <input
                                                type="number"
                                                name="quantity"
                                                value="{{ $item['quantity'] }}"
                                                min="1"
                                                max="{{ min((int) $item['variant']->quantity, 10) }}"
                                                data-cart-qty-input
                                                class="w-14 rounded-lg border border-gray-300 px-2 py-1 text-center text-sm focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400"
                                            >
                                            <button type="submit" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:border-primary-400 hover:text-primary-600">
                                                <i class="fa-solid fa-rotate-right"></i>
                                            </button>
                                        </form>

                                        {{-- Remove --}}
                                        <form method="POST" action="{{ route('cart.remove') }}">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="variant_id" value="{{ $item['variant']->id }}">
                                            <button type="submit" class="flex items-center gap-1 text-xs text-rose-500 hover:text-rose-700">
                                                <i class="fa-solid fa-trash-can text-[10px]"></i>წაშლა
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Order summary sidebar --}}
                    <div class="lg:w-72 xl:w-80">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="text-base font-bold text-gray-900">შეკვეთის შეჯამება</h2>

                            <dl class="mt-4 space-y-2 text-sm text-gray-700">
                                <div class="flex justify-between">
                                    <dt>პროდუქტები (<span data-cart-count>{{ $cartCount }}</span> ც.)</dt>
                                    <dd class="font-medium" data-cart-total>{{ number_format($cartTotal, 2) }} ₾</dd>
                                </div>
                                <div class="flex justify-between text-gray-500">
                                    <dt>მიტანა</dt>
                                    <dd>უფასო</dd>
                                </div>
                                <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold text-gray-900">
                                    <dt>სულ</dt>
                                    <dd class="text-primary-600" data-cart-total>{{ number_format($cartTotal, 2) }} ₾</dd>
                                </div>
                            </dl>

                            <a href="{{ route('checkout.index') }}" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                                <i class="fa-solid fa-lock text-xs"></i>გადახდაზე გადასვლა
                            </a>

                            <form method="POST" action="{{ route('cart.clear') }}" class="mt-3">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-full border border-slate-200 px-5 py-2 text-xs font-medium text-gray-500 hover:border-rose-300 hover:text-rose-500">
                                    <i class="fa-solid fa-trash text-[10px]"></i>კალათის გასუფთავება
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
<script>
    (function () {
        function showMessage(message, isError) {
            if (window.cartUi && typeof window.cartUi.showToast === 'function') {
                window.cartUi.showToast(message, isError);
            }
        }

        function updateBadges(count) {
            if (window.cartUi && typeof window.cartUi.updateBadges === 'function') {
                window.cartUi.updateBadges(count);
            }
        }

        function setCount(count) {
            document.querySelectorAll('[data-cart-count]').forEach(function (node) {
                node.textContent = count;
            });
        }

        function setTotals(totalText) {
            document.querySelectorAll('[data-cart-total]').forEach(function (node) {
                node.textContent = totalText;
            });
        }

        function submitUpdate(form) {
            var submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    showMessage(result.data.message || 'განახლება ვერ შესრულდა.', true);
                    return;
                }

                var row = form.closest('[data-cart-row]');
                if (row && result.data.item_subtotal_formatted) {
                    var subtotal = row.querySelector('[data-item-subtotal]');
                    if (subtotal) {
                        subtotal.textContent = result.data.item_subtotal_formatted;
                    }
                }

                if (typeof result.data.cart_count !== 'undefined') {
                    setCount(result.data.cart_count);
                    updateBadges(result.data.cart_count);
                }

                if (result.data.cart_total_formatted) {
                    setTotals(result.data.cart_total_formatted);
                }

                showMessage(result.data.message || 'კალათა განახლდა.', false);
            })
            .catch(function () {
                showMessage('ქსელური შეცდომა. სცადეთ თავიდან.', true);
            })
            .finally(function () {
                if (submitButton) submitButton.disabled = false;
            });
        }

        document.querySelectorAll('[data-cart-update-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitUpdate(form);
            });

            var quantityInput = form.querySelector('[data-cart-qty-input]');
            if (quantityInput) {
                quantityInput.addEventListener('change', function () {
                    submitUpdate(form);
                });
            }
        });
    }());
</script>
@endpush
