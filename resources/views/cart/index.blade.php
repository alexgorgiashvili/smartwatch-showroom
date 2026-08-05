@extends('layouts.app')

@section('title', __('storefront.cart.title'))
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="bg-gray-50 py-8 sm:py-10">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

            {{-- Page heading --}}
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ __('storefront.cart.title') }}</h1>
                    <p class="text-sm text-gray-500">{{ trans_choice('storefront.common.items_count', $cartCount, ['count' => $cartCount]) }}</p>
                </div>
                <a href="{{ route('products.index') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                    <i class="fa-solid fa-arrow-left mr-1 text-xs"></i>{{ __('storefront.cart.catalog') }}
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

            @if($cartItems->isEmpty() && $giftGroups->isEmpty())
                {{-- Empty state --}}
                <div class="rounded-2xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm">
                    <i class="fa-solid fa-cart-shopping mb-4 text-4xl text-gray-300"></i>
                    <p class="text-lg font-semibold text-gray-800">{{ __('storefront.cart.empty_title') }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ __('storefront.cart.empty_text') }}</p>
                    <a href="{{ route('products.index') }}" class="mt-6 inline-flex items-center gap-2 rounded-full bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                        <i class="fa-solid fa-shop text-xs"></i>{{ __('storefront.cart.view_products') }}
                    </a>
                </div>
            @else
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start">

                    {{-- Cart items list --}}
                    <div class="flex-1 min-w-0">
                        @if($giftGroups->isNotEmpty())
                            <div class="mb-5 space-y-4">
                                @foreach($giftGroups as $group)
                                    @php
                                        $groupSym = ($group['currency'] ?? 'GEL') === 'GEL' ? '₾' : ($group['currency'] ?? 'GEL');
                                    @endphp
                                    <article class="overflow-hidden rounded-2xl border border-primary-100 bg-white shadow-sm">
                                        <div class="flex flex-col gap-3 border-b border-primary-100 bg-primary-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-wide text-primary-700">{{ __('storefront.cart.gift_box') }}</p>
                                                <h2 class="text-base font-bold text-gray-900">{{ __('storefront.cart.gift_box') }}</h2>
                                                <p class="mt-0.5 text-xs text-gray-600">{{ trans_choice('storefront.common.products_count', $group['items_count'], ['count' => $group['items_count']]) }} • {{ $group['packaging_label'] }}</p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                @if (config('gift_builder.enabled', false))
                                                <a href="{{ route('gift-builder.show') }}" class="inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 hover:border-primary-300 hover:bg-primary-50">
                                                    <i class="fa-solid fa-pen text-[10px]"></i> {{ __('storefront.common.edit') }}
                                                </a>
                                                @endif
                                                <form method="POST" action="{{ route('cart.gift-groups.remove', $group['id']) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-600 hover:border-rose-300 hover:bg-rose-50">
                                                        <i class="fa-solid fa-trash-can text-[10px]"></i> {{ __('storefront.common.remove') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="divide-y divide-slate-100">
                                            @foreach($group['items'] as $item)
                                                <div class="flex items-center gap-4 p-4">
                                                    <a href="{{ route('products.show', $item['product']) }}" class="block flex-shrink-0">
                                                        <img src="{{ $item['image'] }}" alt="{{ $item['product']->name }}" loading="lazy" decoding="async" class="h-16 w-16 rounded-xl border border-slate-100 object-cover">
                                                    </a>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <a href="{{ route('products.show', $item['product']) }}" class="truncate text-sm font-semibold text-gray-900 hover:text-primary-600 sm:text-base">
                                                                {{ $item['product']->name }}
                                                            </a>
                                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-600">{{ $item['gift_role'] === 'main' ? __('storefront.common.main') : __('storefront.common.addon') }}</span>
                                                        </div>
                                                        <div class="mt-0.5 flex items-center gap-2 text-xs text-gray-500">
                                                            @if (!empty($item['color_hex']))
                                                                <span class="h-3 w-3 rounded-full border border-slate-200" style="background-color: {{ $item['color_hex'] }}"></span>
                                                            @endif
                                                            <span>{{ $item['variant_label'] }}</span>
                                                        </div>
                                                    </div>
                                                    <p class="shrink-0 text-sm font-bold text-gray-900">{{ number_format($item['subtotal'], 2) }} {{ $groupSym }}</p>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="space-y-2 bg-slate-50 px-4 py-3 text-sm text-gray-700">
                                            <div class="flex justify-between">
                                                <span>{{ __('storefront.common.products') }}</span>
                                                <span>{{ number_format($group['items_subtotal'], 2) }} {{ $groupSym }}</span>
                                            </div>
                                            @if((float) $group['packaging_amount'] > 0)
                                                <div class="flex justify-between">
                                                    <span>{{ $group['packaging_label'] }}</span>
                                                    <span>{{ number_format($group['packaging_amount'], 2) }} {{ $groupSym }}</span>
                                                </div>
                                            @endif
                                            @if((float) $group['discount_amount'] > 0)
                                                <div class="flex justify-between text-emerald-700">
                                                    <span>{{ __('storefront.cart.gift_discount') }}</span>
                                                    <span>-{{ number_format($group['discount_amount'], 2) }} {{ $groupSym }}</span>
                                                </div>
                                            @endif
                                            @if($group['message'])
                                                <p class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-gray-600">“{{ $group['message'] }}”</p>
                                            @endif
                                            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold text-gray-900">
                                                <span>{{ __('storefront.cart.box_total') }}</span>
                                                <span class="text-primary-600">{{ number_format($group['total'], 2) }} {{ $groupSym }}</span>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif

                        @if($cartItems->isNotEmpty())
                        <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            @foreach($cartItems as $item)
                                @php $sym = $item['currency'] === 'GEL' ? '₾' : $item['currency']; @endphp
                                <div class="flex items-center gap-4 p-4 sm:p-5" data-cart-row data-variant-id="{{ $item['variant']->id }}">

                                    {{-- Product image --}}
                                    <a href="{{ route('products.show', $item['product']) }}" class="block flex-shrink-0">
                                        <img
                                            src="{{ $item['image'] }}"
                                            alt="{{ $item['product']->name }}"
                                            loading="lazy"
                                            decoding="async"
                                            class="h-16 w-16 rounded-xl border border-slate-100 object-cover sm:h-20 sm:w-20"
                                        >
                                    </a>

                                    {{-- Name + variant + unit price --}}
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('products.show', $item['product']) }}" class="block truncate text-sm font-semibold text-gray-900 hover:text-primary-600 sm:text-base">
                                            {{ $item['product']->name }}
                                        </a>
                                        <div class="mt-0.5 flex items-center gap-2 text-xs text-gray-500">
                                            @if (!empty($item['color_hex']))
                                                <span class="h-3 w-3 rounded-full border border-slate-200" style="background-color: {{ $item['color_hex'] }}"></span>
                                            @endif
                                            <span>{{ $item['variant_label'] }}</span>
                                        </div>
                                        @php
                                            $switchableVariants = $item['product']->variants
                                                ->filter(fn ($variant) => $variant->available_quantity > 0)
                                                ->values();
                                        @endphp
                                        @if ($switchableVariants->count() > 1)
                                            <form method="POST" action="{{ route('cart.replace-variant') }}" class="mt-2" data-cart-variant-form>
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="current_variant_id" value="{{ $item['variant']->id }}">
                                                <label class="mb-1 block text-[11px] font-medium text-gray-500">
                                                    {{ __('storefront.cart.change_color') }}
                                                </label>
                                                <select
                                                    name="new_variant_id"
                                                    data-cart-variant-select
                                                    class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400"
                                                >
                                                    @foreach ($switchableVariants as $variantOption)
                                                        @php
                                                            $variantOptionName = $variantOption->localizedName();
                                                            $variantOptionColor = $variantOption->localizedColorName();
                                                            $variantOptionLabel = filled($variantOptionName) && filled($variantOptionColor) && !str_contains(mb_strtolower($variantOptionName), mb_strtolower($variantOptionColor))
                                                                ? $variantOptionName . ' • ' . $variantOptionColor
                                                                : ($variantOptionColor ?: $variantOptionName ?: __('storefront.common.color_variant'));
                                                        @endphp
                                                        <option value="{{ $variantOption->id }}" {{ $variantOption->id === $item['variant']->id ? 'selected' : '' }}>
                                                            {{ $variantOptionLabel }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @endif
                                        <p class="mt-1 text-[11px] font-semibold text-primary-600">{{ $item['fulfillment_label'] }}</p>
                                        <p class="mt-1 text-xs font-medium text-gray-600">{{ number_format($item['unit_price'], 2) }} {{ $sym }} / {{ __('storefront.cart.per_item') }}</p>
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
                                            <button
                                                type="button"
                                                data-cart-qty-minus
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-sm font-semibold text-gray-700 hover:border-primary-400 hover:text-primary-600"
                                                aria-label="{{ __('storefront.cart.decrease_quantity') }}"
                                            >
                                                −
                                            </button>
                                            <input
                                                type="number"
                                                name="quantity"
                                                value="{{ $item['quantity'] }}"
                                                min="1"
                                                max="{{ min((int) $item['variant']->available_quantity, 10) }}"
                                                data-cart-qty-input
                                                class="w-14 rounded-lg border border-gray-300 px-2 py-1 text-center text-sm focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400"
                                            >
                                            <button
                                                type="button"
                                                data-cart-qty-plus
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-sm font-semibold text-gray-700 hover:border-primary-400 hover:text-primary-600"
                                                aria-label="{{ __('storefront.cart.increase_quantity') }}"
                                            >
                                                +
                                            </button>
                                        </form>

                                        {{-- Remove --}}
                                        <form method="POST" action="{{ route('cart.remove') }}">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="variant_id" value="{{ $item['variant']->id }}">
                                            <button type="submit" class="flex items-center gap-1 text-xs text-rose-500 hover:text-rose-700">
                                                <i class="fa-solid fa-trash-can text-[10px]"></i>{{ __('storefront.common.remove') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Order summary sidebar --}}
                    <div class="lg:w-72 xl:w-80">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="text-base font-bold text-gray-900">{{ __('storefront.cart.order_summary') }}</h2>

                            <dl class="mt-4 space-y-2 text-sm text-gray-700">
                                <div class="flex justify-between">
                                    <dt>{{ __('storefront.common.products') }} (<span data-cart-count>{{ $cartCount }}</span> {{ __('storefront.checkout.unit_short') }})</dt>
                                    <dd class="font-medium" data-cart-total>{{ number_format($cartTotal, 2) }} ₾</dd>
                                </div>
                                <div class="flex justify-between text-gray-500">
                                    <dt>{{ __('storefront.cart.delivery') }}</dt>
                                    <dd>{{ __('storefront.common.free') }}</dd>
                                </div>
                                <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold text-gray-900">
                                    <dt>{{ __('storefront.common.total') }}</dt>
                                    <dd class="text-primary-600" data-cart-total>{{ number_format($cartTotal, 2) }} ₾</dd>
                                </div>
                            </dl>

                            <a href="{{ route('checkout.index') }}" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                                <i class="fa-solid fa-lock text-xs"></i>{{ __('storefront.cart.continue') }}
                            </a>

                            <form method="POST" action="{{ route('cart.clear') }}" class="mt-3">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-full border border-slate-200 px-5 py-2 text-xs font-medium text-gray-500 hover:border-rose-300 hover:text-rose-500">
                                    <i class="fa-solid fa-trash text-[10px]"></i>{{ __('storefront.cart.clear') }}
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
<style>
    [data-cart-qty-input]::-webkit-outer-spin-button,
    [data-cart-qty-input]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    [data-cart-qty-input] {
        -moz-appearance: textfield;
        appearance: textfield;
    }

    [data-cart-variant-select],
    [data-cart-variant-select] option,
    [data-cart-variant-select] optgroup {
        background-color: #ffffff !important;
        color: #1f2937 !important;
    }
</style>
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
                    showMessage(result.data.message || @js(__('storefront.messages.update_failed')), true);
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

                showMessage(result.data.message || @js(__('storefront.messages.cart_updated')), false);
            })
            .catch(function () {
                showMessage(@js(__('storefront.messages.network_error')), true);
            })
            .finally(function () {
                if (submitButton) submitButton.disabled = false;
            });
        }

        function clampQuantity(input) {
            var min = parseInt(input.getAttribute('min') || '1', 10);
            var max = parseInt(input.getAttribute('max') || '10', 10);
            var value = parseInt(input.value || String(min), 10);

            if (isNaN(value)) {
                value = min;
            }

            if (!isNaN(min) && value < min) {
                value = min;
            }

            if (!isNaN(max) && value > max) {
                value = max;
            }

            input.value = String(value);
            return value;
        }

        function scheduleUpdate(form) {
            if (form._cartUpdateTimer) {
                clearTimeout(form._cartUpdateTimer);
            }

            form._cartUpdateTimer = setTimeout(function () {
                var quantityInput = form.querySelector('[data-cart-qty-input]');
                if (!quantityInput) {
                    return;
                }

                var rawValue = (quantityInput.value || '').trim();
                if (rawValue === '') {
                    return;
                }

                clampQuantity(quantityInput);
                submitUpdate(form);
            }, 350);
        }

        document.querySelectorAll('[data-cart-update-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var inputOnSubmit = form.querySelector('[data-cart-qty-input]');
                if (inputOnSubmit) {
                    clampQuantity(inputOnSubmit);
                }
                submitUpdate(form);
            });

            var quantityInput = form.querySelector('[data-cart-qty-input]');
            if (quantityInput) {
                quantityInput.addEventListener('input', function () {
                    if ((quantityInput.value || '').trim() === '') {
                        if (form._cartUpdateTimer) {
                            clearTimeout(form._cartUpdateTimer);
                        }
                        return;
                    }

                    scheduleUpdate(form);
                });

                quantityInput.addEventListener('change', function () {
                    clampQuantity(quantityInput);
                    submitUpdate(form);
                });
            }

            var minusButton = form.querySelector('[data-cart-qty-minus]');
            if (minusButton && quantityInput) {
                minusButton.addEventListener('click', function () {
                    var current = clampQuantity(quantityInput);
                    quantityInput.value = String(Math.max(1, current - 1));
                    submitUpdate(form);
                });
            }

            var plusButton = form.querySelector('[data-cart-qty-plus]');
            if (plusButton && quantityInput) {
                plusButton.addEventListener('click', function () {
                    var current = clampQuantity(quantityInput);
                    var max = parseInt(quantityInput.getAttribute('max') || '10', 10);
                    quantityInput.value = String(Math.min(isNaN(max) ? 10 : max, current + 1));
                    submitUpdate(form);
                });
            }
        });

        document.querySelectorAll('[data-cart-variant-form]').forEach(function (form) {
            var select = form.querySelector('select[name="new_variant_id"]');
            if (!select) {
                return;
            }

            select.addEventListener('change', function () {
                var currentVariantInput = form.querySelector('input[name="current_variant_id"]');
                if (!currentVariantInput || !select.value || select.value === currentVariantInput.value) {
                    return;
                }

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
                        showMessage(result.data.message || @js(__('storefront.messages.color_change_failed')), true);
                        select.value = currentVariantInput.value;
                        return;
                    }

                    showMessage(result.data.message || @js(__('storefront.messages.color_changed')), false);
                    window.location.reload();
                })
                .catch(function () {
                    showMessage(@js(__('storefront.messages.network_error')), true);
                    select.value = currentVariantInput.value;
                });
            });
        });
    }());
</script>
@endpush
