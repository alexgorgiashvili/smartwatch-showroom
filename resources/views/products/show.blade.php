@extends('layouts.app')

@section('title', isset($product) ? $product->name : 'Product')

@section('content')
    @php
        $basePrice = $product->price;
        $salePrice = $product->sale_price ?? null;
        $hasDiscount = $salePrice !== null && $basePrice !== null && $salePrice < $basePrice;
        $discountPercent = $hasDiscount ? (int) round((($basePrice - $salePrice) / $basePrice) * 100) : null;
        $currency = $product->currency === 'GEL' ? '₾' : $product->currency;
        $defaultVariant = $product->variants->first(fn ($variant) => $variant->quantity > 0) ?? $product->variants->first();
        $colorVariants = $product->variants
            ->filter(fn ($variant) => filled($variant->color_name) && filled($variant->color_hex))
            ->unique(fn ($variant) => strtoupper($variant->color_hex) . '|' . mb_strtolower($variant->color_name))
            ->values();
        $defaultColor = $colorVariants->first();
    @endphp

    <section class="bg-gray-50 py-8 sm:py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <nav class="mb-6 flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center gap-1 text-sm text-gray-500">
                    <li>
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 hover:text-primary-600">
                            <i class="fa-solid fa-house text-xs"></i>{{ __('ui.nav_home') }}
                        </a>
                    </li>
                    <li><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i></li>
                    <li>
                        <a href="{{ route('products.index') }}" class="hover:text-primary-600">{{ __('ui.nav_catalog') }}</a>
                    </li>
                    <li><i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i></li>
                    <li class="truncate text-gray-700">{{ $product->name }}</li>
                </ol>
            </nav>

            <div class="grid gap-6 lg:grid-cols-12 lg:gap-8">
                <div class="lg:col-span-7">
                    @if ($product->images->isNotEmpty())
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div id="product-splide" class="splide" aria-label="Product images">
                                <div class="splide__track">
                                    <ul class="splide__list">
                                        @foreach ($product->images as $image)
                                            <li class="splide__slide">
                                                <img src="{{ $image->url }}" alt="{{ $image->alt ?: $product->name }}" class="h-[340px] w-full object-cover sm:h-[460px]" />
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex h-[340px] w-full items-center justify-center rounded-2xl border border-slate-200 bg-white sm:h-[460px]">
                            <div class="text-center text-gray-500">
                                <i class="fa-solid fa-image mb-2 text-4xl"></i>
                                <p class="text-sm">{{ __('ui.no_image') }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-5">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:sticky lg:top-24">
                        @if ($product->featured)
                            <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-600">
                                <i class="fa-solid fa-star"></i>{{ __('ui.sort_featured') }}
                            </div>
                        @endif

                        <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl">{{ $product->name }}</h1>

                        @if ($product->short_description)
                            <p class="mt-3 text-sm leading-relaxed text-gray-600 sm:text-base">{{ $product->short_description }}</p>
                        @endif

                        <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="mb-1 text-xs uppercase tracking-wide text-gray-500">{{ __('ui.product_price') }}</p>
                            @if ($hasDiscount)
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-3xl font-extrabold tracking-tight text-primary-600 [font-family:'Space_Grotesk',system-ui,sans-serif]">{{ number_format($salePrice, 2) }} {{ $currency }}</span>
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">-{{ $discountPercent }}%</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-400 line-through">{{ number_format($basePrice, 2) }} {{ $currency }}</p>
                            @elseif ($basePrice)
                                <p class="text-3xl font-extrabold tracking-tight text-gray-900 [font-family:'Space_Grotesk',system-ui,sans-serif]">{{ number_format($basePrice, 2) }} {{ $currency }}</p>
                            @else
                                <p class="text-lg font-semibold text-gray-700">{{ __('ui.price_on_request') }}</p>
                            @endif
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-2.5">
                            @if ($product->sim_support)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">SIM</p>
                                    <p>{{ __('ui.yes') }}</p>
                                </div>
                            @endif
                            @if ($product->gps_features)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">GPS</p>
                                    <p>{{ __('ui.yes') }}</p>
                                </div>
                            @endif
                            @if ($product->water_resistant)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">{{ __('ui.product_water') }}</p>
                                    <p>{{ __('ui.yes') }}</p>
                                </div>
                            @endif
                            @if ($product->battery_life_hours)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">{{ __('ui.product_battery') }}</p>
                                    <p>{{ $product->battery_life_hours }} {{ __('ui.hours') }}</p>
                                </div>
                            @endif
                            @if ($product->screen_size)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">Screen</p>
                                    <p>{{ $product->screen_size }}</p>
                                </div>
                            @endif
                            @if ($product->display_type)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">Display</p>
                                    <p>{{ $product->display_type }}</p>
                                </div>
                            @endif
                            @if ($product->battery_capacity_mah)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs text-gray-600">
                                    <p class="font-semibold text-gray-900">Battery Capacity</p>
                                    <p>{{ $product->battery_capacity_mah }} mAh</p>
                                </div>
                            @endif
                        </div>

                        @if($colorVariants->isNotEmpty())
                            <div class="mt-6 rounded-xl border border-slate-200 bg-white p-4">
                                <p class="text-xs uppercase tracking-wide text-gray-500">{{ app()->getLocale() === 'ka' ? 'ფერი' : 'Color' }}</p>
                                <p id="selected-color-label" class="mt-1 text-sm font-semibold text-gray-900">
                                    {{ app()->getLocale() === 'ka' ? 'არჩეული' : 'Selected' }}: {{ $defaultColor->color_name }}
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-2.5">
                                    @foreach($colorVariants as $index => $variantColor)
                                        <button
                                            type="button"
                                            class="product-color-swatch relative inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 transition focus:outline-none focus:ring-2 focus:ring-primary-500 {{ $index === 0 ? 'ring-2 ring-primary-500 ring-offset-2' : '' }}"
                                            style="background-color: {{ $variantColor->color_hex }};"
                                            title="{{ $variantColor->color_name }}"
                                            data-color-name="{{ $variantColor->color_name }}"
                                            data-color-hex="{{ strtoupper($variantColor->color_hex) }}"
                                            data-variant-id="{{ $variantColor->id }}"
                                            data-stock="{{ (int) $variantColor->quantity }}"
                                            aria-label="{{ $variantColor->color_name }}"
                                        ></button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="mt-6 space-y-2.5">
                            @if(session('cart_error'))
                                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                    {{ session('cart_error') }}
                                </div>
                            @endif

                            @if($defaultVariant)
                                <form method="POST" action="{{ route('cart.add') }}" id="add-to-cart-form" data-cart-form class="space-y-2">
                                    @csrf
                                    <input type="hidden" name="variant_id" id="selected-variant-id" value="{{ $defaultVariant->id }}">

                                    <div class="flex items-center gap-2">
                                        <label for="cart-quantity" class="text-sm font-semibold text-gray-700">რაოდენობა</label>
                                        <input
                                            id="cart-quantity"
                                            type="number"
                                            name="quantity"
                                            min="1"
                                            max="{{ max(1, min(10, (int) $defaultVariant->quantity)) }}"
                                            value="1"
                                            class="w-20 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                        >
                                    </div>

                                    <button
                                        type="submit"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-600 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-700"
                                    >
                                        <i class="fa-solid fa-cart-shopping text-xs"></i>კალათაში დამატება
                                    </button>
                                </form>
                            @endif
                            <button
                                type="button"
                                onclick="document.getElementById('inquiry-form-section').scrollIntoView({ behavior: 'smooth' })"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-gray-700 transition-colors hover:border-primary-400 hover:text-primary-600"
                            >
                                <i class="fa-solid fa-message text-xs"></i>{{ __('ui.form_submit') }}
                            </button>
                            <a href="{{ route('products.index') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-gray-700 transition-colors hover:border-primary-400 hover:text-primary-600">
                                <i class="fa-solid fa-arrow-left text-xs"></i>{{ __('ui.product_back') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-12">
                <div class="lg:col-span-7">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="mb-4 text-xl font-bold text-gray-900">{{ __('ui.product_specs') }}</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <tbody>
                                    <tr class="border-b border-gray-100">
                                        <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.product_sim') }}</td>
                                        <td class="px-2 py-3 text-gray-700">{{ $product->sim_support ? __('ui.yes') : __('ui.no') }}</td>
                                    </tr>
                                    <tr class="border-b border-gray-100">
                                        <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.product_gps') }}</td>
                                        <td class="px-2 py-3 text-gray-700">{{ $product->gps_features ? __('ui.yes') : __('ui.no') }}</td>
                                    </tr>
                                    @if ($product->water_resistant)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.product_water') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ __('ui.yes') }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->battery_life_hours)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.product_battery') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->battery_life_hours }} {{ __('ui.hours') }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->warranty_months)
                                        <tr>
                                            <td class="px-2 py-3 font-semibold text-gray-900">{{ __('ui.product_warranty') }}</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->warranty_months }} {{ __('ui.months') }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->operating_system)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">Operating System</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->operating_system }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->screen_size)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">Screen Size</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->screen_size }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->display_type)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">Display Type</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->display_type }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->screen_resolution)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">Screen Resolution</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->screen_resolution }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->battery_capacity_mah)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">Battery Capacity</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->battery_capacity_mah }} mAh</td>
                                        </tr>
                                    @endif
                                    @if ($product->charging_time_hours)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">Charging Time</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->charging_time_hours }} h</td>
                                        </tr>
                                    @endif
                                    @if ($product->case_material)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">Case Material</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->case_material }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->band_material)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">Band Material</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->band_material }}</td>
                                        </tr>
                                    @endif
                                    @if ($product->camera)
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-3 font-semibold text-gray-900">Camera</td>
                                            <td class="px-2 py-3 text-gray-700">{{ $product->camera }}</td>
                                        </tr>
                                    @endif
                                    @if (is_array($product->functions) && $product->functions !== [])
                                        <tr>
                                            <td class="px-2 py-3 font-semibold text-gray-900">Functions</td>
                                            <td class="px-2 py-3 text-gray-700">{{ implode(', ', $product->functions) }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($product->description)
                        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <h2 class="mb-4 text-xl font-bold text-gray-900">{{ __('ui.product_description') }}</h2>
                            <div class="prose prose-sm max-w-none text-gray-700">{!! nl2br(e($product->description)) !!}</div>
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-5">
                    <div id="inquiry-form-section" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="text-xl font-bold text-gray-900">{{ __('ui.section_contact') }}</h2>
                        <p class="mt-2 text-sm text-gray-600">{{ __('ui.section_contact_sub') }}</p>

                        <form method="POST" action="{{ route('inquiries.store') }}" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <input type="hidden" name="selected_color" id="selected-color-input" value="{{ $defaultColor?->color_name }}">

                            @if (session('status'))
                                <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                                    <i class="fa-solid fa-check-circle mr-2"></i>{{ session('status') }}
                                </div>
                            @endif

                            @if ($errors->any())
                                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                    <ul class="space-y-1">
                                        @foreach ($errors->all() as $error)
                                            <li><i class="fa-solid fa-exclamation-circle mr-2"></i>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="grid gap-3 sm:grid-cols-2">
                                <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('ui.form_name') }} *" required class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500" />
                                <input type="text" name="phone" value="{{ old('phone') }}" placeholder="{{ __('ui.form_phone') }} *" required class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500" />
                            </div>

                            <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('ui.form_email') }}" class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500" />
                            <textarea name="message" rows="4" placeholder="{{ __('ui.form_message') }}" class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500">{{ old('message') }}</textarea>

                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-gray-900 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-600">
                                <i class="fa-solid fa-paper-plane text-xs"></i>{{ __('ui.form_submit') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            @if(isset($relatedProducts) && $relatedProducts->isNotEmpty())
                <div class="mt-10">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-bold text-gray-900">{{ app()->getLocale() === 'ka' ? 'მსგავსი პროდუქტები' : 'Related Products' }}</h2>
                        <a href="{{ route('products.index') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">{{ app()->getLocale() === 'ka' ? 'ყველას ნახვა' : 'View all' }}</a>
                    </div>

                    <div id="related-products-splide" class="splide" aria-label="Related products">
                        <div class="splide__track">
                            <ul class="splide__list">
                                @foreach($relatedProducts as $related)
                                    @php
                                        $relatedImage = $related->primaryImage;
                                        $relatedBase = $related->price;
                                        $relatedSale = $related->sale_price ?? null;
                                        $relatedDiscount = $relatedSale !== null && $relatedBase !== null && $relatedSale < $relatedBase;
                                        $relatedCurrency = $related->currency === 'GEL' ? '₾' : $related->currency;
                                    @endphp
                                    <li class="splide__slide">
                                        <a href="{{ route('products.show', $related) }}" class="group block overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:-translate-y-1 hover:shadow-md">
                                            <div class="aspect-square overflow-hidden bg-gray-100">
                                                <img src="{{ $relatedImage?->url ?: asset('storage/images/home/smart-watch3.jpg') }}" alt="{{ $related->name }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                                            </div>
                                            <div class="p-3">
                                                <h3 class="truncate text-sm font-semibold text-gray-900">{{ $related->name }}</h3>
                                                <div class="mt-1">
                                                    @if($relatedDiscount)
                                                        <p class="text-sm font-bold text-primary-600">{{ number_format($relatedSale, 2) }} {{ $relatedCurrency }}</p>
                                                    @elseif($relatedBase)
                                                        <p class="text-sm font-bold text-gray-900">{{ number_format($relatedBase, 2) }} {{ $relatedCurrency }}</p>
                                                    @else
                                                        <p class="text-sm font-semibold text-gray-600">{{ __('ui.price_on_request') }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const swatches = Array.from(document.querySelectorAll('.product-color-swatch'));
            if (!swatches.length) {
                return;
            }

            const selectedLabel = document.getElementById('selected-color-label');
            const selectedInput = document.getElementById('selected-color-input');
            const selectedVariantInput = document.getElementById('selected-variant-id');
            const quantityInput = document.getElementById('cart-quantity');

            const setActive = (targetSwatch) => {
                swatches.forEach((swatch) => {
                    swatch.classList.remove('ring-2', 'ring-primary-500', 'ring-offset-2');
                });
                targetSwatch.classList.add('ring-2', 'ring-primary-500', 'ring-offset-2');

                const colorName = targetSwatch.dataset.colorName || '';
                const selectedText = "{{ app()->getLocale() === 'ka' ? 'არჩეული' : 'Selected' }}";
                if (selectedLabel) {
                    selectedLabel.textContent = `${selectedText}: ${colorName}`;
                }
                if (selectedInput) {
                    selectedInput.value = colorName;
                }

                if (selectedVariantInput && targetSwatch.dataset.variantId) {
                    selectedVariantInput.value = targetSwatch.dataset.variantId;
                }

                if (quantityInput && targetSwatch.dataset.stock) {
                    const stock = Math.max(1, Math.min(10, parseInt(targetSwatch.dataset.stock, 10) || 1));
                    quantityInput.max = String(stock);
                    if (parseInt(quantityInput.value, 10) > stock) {
                        quantityInput.value = String(stock);
                    }
                }
            };

            swatches.forEach((swatch) => {
                swatch.addEventListener('click', function () {
                    setActive(this);
                });
            });
        });
    </script>
@endpush
