@extends('layouts.app')

@section('title', app()->getLocale() === 'ka' ? 'ბავშვის SIM სმარტ საათები — ყველა მოდელი | MyTechnic' : 'Kids SIM Smartwatches — All Models in Georgia | MyTechnic')
@section('meta_description', app()->getLocale() === 'ka' ? 'MyTechnic-ის ბავშვის SIM სმარტ საათების კატალოგი — 4G GPS, ბავშვთა უსაფრთხოება. ნახეთ ყველა მოდელი, ფასები, მახასიათებლები.' : 'Browse MyTechnic SIM smartwatch catalog — 4G GPS, child safety. All models, prices and specs.')
@section('canonical', url('/products'))
@section('og_title', app()->getLocale() === 'ka' ? 'ბავშვის SIM სმარტ საათები — MyTechnic' : 'Kids SIM Smartwatches — MyTechnic')
@section('og_url', url('/products'))
@section('og_image', asset('images/og-default.webp'))

@section('header')
    <!-- Header component -->
@endsection

@section('content')
    @php
        $generation = $generation ?? request('generation', 'all');
        $sort = $sort ?? request('sort', 'featured');
        $sortLabels = [
            'featured' => __('ui.sort_featured'),
            'price_low' => __('ui.sort_price_low'),
            'price_high' => __('ui.sort_price_high'),
            'discount' => __('ui.sort_discount'),
        ];
    @endphp

    <div class="bg-white border-b border-gray-100 py-8 sm:py-10 overflow-hidden">
        <div class="mx-auto max-w-screen-xl px-4 sm:px-6 lg:px-8 text-center w-full">
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900">
                @if(app()->getLocale() === 'ka')
                    {{ __('storefront.products.index_heading') }}
                @else
                    Kids SIM Smartwatches — All Models in Georgia
                @endif
            </h1>
            <p class="mt-2 text-sm text-gray-500">
                @if(app()->getLocale() === 'ka')
                    {{ __('storefront.products.index_subheading') }}
                @else
                    4G LTE · GPS Tracking · SOS Button · Calls Without a Phone
                @endif
            </p>
        </div>
    </div>

    <section class="border-b border-gray-100 bg-gradient-to-r from-primary-50 to-white overflow-hidden">
        <div class="mx-auto max-w-screen-xl px-4 py-6 sm:px-6 lg:px-8 w-full">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-100">
                        <i class="fa-solid fa-truck-fast text-xl text-primary-600"></i>
                    </div>
                    <div class="min-w-0 text-left">
                        <p class="text-sm font-semibold text-gray-900">{{ __('ui.trust_shipping') }}</p>
                        <p class="text-xs text-gray-600">{{ __('ui.trust_shipping_text') }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-green-100">
                        <i class="fa-solid fa-shield-halved text-xl text-green-600"></i>
                    </div>
                    <div class="min-w-0 text-left">
                        <p class="text-sm font-semibold text-gray-900">{{ __('ui.trust_warranty') }}</p>
                        <p class="text-xs text-gray-600">{{ __('ui.trust_warranty_text') }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-purple-100">
                        <i class="fa-solid fa-headset text-xl text-purple-600"></i>
                    </div>
                    <div class="min-w-0 text-left">
                        <p class="text-sm font-semibold text-gray-900">{{ __('ui.trust_support') }}</p>
                        <p class="text-xs text-gray-600">{{ __('ui.trust_support_text') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white overflow-hidden">
        <div class="mx-auto max-w-screen-xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8 w-full">
            <div class="mx-auto mt-8 max-w-2xl w-full">
                <form action="{{ route('products.index') }}" method="GET" class="flex gap-2">
                    <div class="relative flex-1">
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="{{ __('ui.search_placeholder') }}"
                            class="w-full rounded-lg border-gray-300 py-3 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        />
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fa-solid fa-magnifying-glass text-gray-400"></i>
                        </span>
                    </div>
                    <button
                        type="submit"
                        data-search-submit
                        class="inline-flex min-w-[118px] items-center justify-center gap-2 rounded-lg bg-primary-600 px-6 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        <span class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/45 border-t-slate-900" data-search-spinner></span>
                        <span data-search-label>{{ __('ui.search') }}</span>
                    </button>
                    @if ($search || $generation !== 'all')
                        <a
                            href="{{ route('products.index') }}"
                            data-products-reset
                            class="rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            title="{{ __('ui.filter_reset') }}"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    @endif
                    <input type="hidden" name="generation" value="{{ $generation }}">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                </form>
            </div>

            <div class="mt-6 flex flex-wrap justify-center gap-2 w-full" data-product-filters>
                <button
                    type="button"
                    data-product-filter="all"
                    class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-medium transition {{ $generation === 'all' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                    aria-pressed="{{ $generation === 'all' ? 'true' : 'false' }}"
                >
                    <span class="hidden h-4 w-4 animate-spin rounded-full border-2 border-slate-400/40 border-t-slate-900" data-filter-spinner></span>
                    <span data-filter-label>{{ app()->getLocale() === 'ka' ? 'ყველა' : 'All' }}</span>
                </button>
                <button
                    type="button"
                    data-product-filter="2g"
                    class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-medium transition {{ $generation === '2g' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                    aria-pressed="{{ $generation === '2g' ? 'true' : 'false' }}"
                >
                    <span class="hidden h-4 w-4 animate-spin rounded-full border-2 border-slate-400/40 border-t-slate-900" data-filter-spinner></span>
                    <span data-filter-label>2G</span>
                </button>
                <button
                    type="button"
                    data-product-filter="4g"
                    class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-medium transition {{ $generation === '4g' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                    aria-pressed="{{ $generation === '4g' ? 'true' : 'false' }}"
                >
                    <span class="hidden h-4 w-4 animate-spin rounded-full border-2 border-slate-400/40 border-t-slate-900" data-filter-spinner></span>
                    <span data-filter-label>4G</span>
                </button>
            </div>

            <div class="mt-8 sm:mt-12">
                <div class="mb-8 flex items-center justify-end" data-sort-filters>
                    <div class="relative min-w-[220px]" data-sort-dropdown>
                        <button
                            type="button"
                            data-sort-trigger
                            aria-haspopup="true"
                            aria-expanded="false"
                            class="inline-flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left text-sm text-slate-700 shadow-sm transition hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            <span class="hidden h-4 w-4 animate-spin rounded-full border-2 border-slate-400/40 border-t-slate-900" data-sort-spinner></span>
                            <span class="flex min-w-0 items-center gap-2" data-sort-text>
                                <span class="shrink-0 text-sm font-semibold text-slate-500">{{ __('ui.sort_by') }}:</span>
                                <span class="truncate font-medium text-slate-900" data-sort-current>{{ $sortLabels[$sort] ?? __('ui.sort_featured') }}</span>
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-slate-400 transition" data-sort-chevron></i>
                        </button>

                        <div
                            class="absolute right-0 z-20 mt-2 hidden w-full overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-[0_18px_42px_rgba(15,23,42,0.12)]"
                            data-sort-menu
                        >
                            @foreach ($sortLabels as $sortValue => $sortLabel)
                                <button
                                    type="button"
                                    data-sort-option="{{ $sortValue }}"
                                    data-sort-label="{{ $sortLabel }}"
                                    class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-sm transition {{ $sort === $sortValue ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
                                    aria-pressed="{{ $sort === $sortValue ? 'true' : 'false' }}"
                                >
                                    <span>{{ $sortLabel }}</span>
                                    <i class="fa-solid fa-check text-xs {{ $sort === $sortValue ? 'opacity-100' : 'opacity-0' }}" data-sort-check></i>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div id="products-grid" data-products-grid aria-live="polite">
                    @include('products._grid', ['products' => $products])
                </div>
            </div>
        </div>
    </section>

    @push('scripts')
    <script>
    (function () {
        const grid = document.querySelector('[data-products-grid]');
        const filterButtons = Array.from(document.querySelectorAll('[data-product-filter]'));
        const sortButtons = Array.from(document.querySelectorAll('[data-sort-option]'));
        const searchInput = document.querySelector('input[name="search"]');
        const searchForm = document.querySelector('form[action="{{ route('products.index') }}"]');
        const resetButton = document.querySelector('[data-products-reset]');
        const searchSubmitButton = document.querySelector('[data-search-submit]');
        const searchSpinner = document.querySelector('[data-search-spinner]');
        const searchLabel = document.querySelector('[data-search-label]');
        const sortDropdown = document.querySelector('[data-sort-dropdown]');
        const sortTrigger = document.querySelector('[data-sort-trigger]');
        const sortMenu = document.querySelector('[data-sort-menu]');
        const sortCurrent = document.querySelector('[data-sort-current]');
        const sortSpinner = document.querySelector('[data-sort-spinner]');
        const sortText = document.querySelector('[data-sort-text]');
        const sortChevron = document.querySelector('[data-sort-chevron]');
        const generationField = searchForm?.querySelector('input[name="generation"]');
        const sortField = searchForm?.querySelector('input[name="sort"]');
        let activeLoadingControl = null;

        if (!grid || filterButtons.length === 0) {
            return;
        }

        const initialGeneration = new URLSearchParams(window.location.search).get('generation') || '{{ $generation }}';
        const initialSort = new URLSearchParams(window.location.search).get('sort') || '{{ $sort }}';

        function setActiveFilter(generation) {
            filterButtons.forEach((button) => {
                const active = button.dataset.productFilter === generation;
                button.classList.toggle('bg-primary-600', active);
                button.classList.toggle('text-white', active);
                button.classList.toggle('shadow-md', active);
                button.classList.toggle('bg-gray-100', !active);
                button.classList.toggle('text-gray-700', !active);
                button.classList.toggle('hover:bg-gray-200', !active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        function setActiveSort(sort) {
            sortButtons.forEach((button) => {
                const active = button.dataset.sortOption === sort;
                button.classList.toggle('bg-slate-100', active);
                button.classList.toggle('text-slate-900', active);
                button.classList.toggle('bg-transparent', !active);
                button.classList.toggle('text-slate-600', !active);
                button.classList.toggle('hover:bg-slate-50', !active);
                button.classList.toggle('hover:text-slate-900', !active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');

                const checkIcon = button.querySelector('[data-sort-check]');
                if (checkIcon) {
                    checkIcon.classList.toggle('opacity-100', active);
                    checkIcon.classList.toggle('opacity-0', !active);
                }

                if (active && sortCurrent) {
                    sortCurrent.textContent = button.dataset.sortLabel || button.textContent.trim();
                }
            });
        }

        function setSortMenuState(isOpen) {
            if (!sortTrigger || !sortMenu) {
                return;
            }

            sortMenu.classList.toggle('hidden', !isOpen);
            sortTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            sortChevron?.classList.toggle('rotate-180', isOpen);
        }

        function setLoadingState(isLoading, triggerType = null, triggerElement = null) {
            grid.setAttribute('aria-busy', isLoading ? 'true' : 'false');
            activeLoadingControl = isLoading ? { type: triggerType, element: triggerElement } : null;

            if (searchInput) {
                searchInput.disabled = isLoading;
            }

            if (searchSubmitButton) {
                searchSubmitButton.disabled = isLoading;
            }

            if (sortTrigger) {
                sortTrigger.disabled = isLoading;
            }

            if (resetButton) {
                resetButton.classList.toggle('pointer-events-none', isLoading);
                resetButton.classList.toggle('opacity-60', isLoading);
            }

            filterButtons.forEach((button) => {
                button.disabled = isLoading;
                button.classList.toggle('pointer-events-none', isLoading);
                button.classList.toggle('opacity-70', isLoading);

                const spinner = button.querySelector('[data-filter-spinner]');
                const label = button.querySelector('[data-filter-label]');
                if (spinner) {
                    const shouldShow = isLoading && triggerType === 'filter' && triggerElement === button;
                    spinner.classList.toggle('hidden', !shouldShow);
                    if (label) {
                        label.classList.toggle('hidden', shouldShow);
                    }
                }
            });

            sortButtons.forEach((button) => {
                button.disabled = isLoading;
                button.classList.toggle('pointer-events-none', isLoading);
                button.classList.toggle('opacity-70', isLoading);
            });

            if (searchSpinner) {
                searchSpinner.classList.toggle('hidden', !(isLoading && triggerType === 'search'));
            }

            if (searchLabel) {
                searchLabel.classList.toggle('hidden', isLoading && triggerType === 'search');
            }

            if (sortSpinner) {
                sortSpinner.classList.toggle('hidden', !(isLoading && triggerType === 'sort'));
            }

            if (sortText) {
                sortText.classList.toggle('hidden', isLoading && triggerType === 'sort');
            }
        }

        function buildUrl(generation, sort) {
            const params = new URLSearchParams(window.location.search);
            params.set('generation', generation);
            params.set('ajax', '1');
            params.set('search', searchInput?.value || '');
            params.set('sort', sort || sortField?.value || '{{ $sort }}');
            return `${window.location.pathname}?${params.toString()}`;
        }

        async function loadProducts(generation, sort, pushState = true, triggerType = null, triggerElement = null) {
            const url = buildUrl(generation, sort);
            setLoadingState(true, triggerType, triggerElement);

            try {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const payload = await response.json();
                grid.innerHTML = payload.html || '';
                setActiveFilter(generation);
                setActiveSort(sort);
                if (generationField) {
                    generationField.value = generation;
                }
                if (sortField) {
                    sortField.value = sort;
                }

                if (pushState) {
                    const params = new URLSearchParams(url.split('?')[1] || '');
                    params.delete('ajax');
                    window.history.pushState({ generation }, '', `${window.location.pathname}?${params.toString()}`);
                }
            } catch (error) {
                window.location.href = url.replace('&ajax=1', '');
            } finally {
                setLoadingState(false);
            }
        }

        filterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const generation = button.dataset.productFilter || 'all';
                if (generation === (new URLSearchParams(window.location.search).get('generation') || initialGeneration)) {
                    return;
                }
                const currentSort = new URLSearchParams(window.location.search).get('sort') || sortField?.value || initialSort;
                loadProducts(generation, currentSort, true, 'filter', button);
            });
        });

        sortTrigger?.addEventListener('click', () => {
            if (sortTrigger.disabled) {
                return;
            }

            const isExpanded = sortTrigger.getAttribute('aria-expanded') === 'true';
            setSortMenuState(!isExpanded);
        });

        sortButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const sort = button.dataset.sortOption || 'featured';
                const currentSort = new URLSearchParams(window.location.search).get('sort') || initialSort;
                if (sort === currentSort) {
                    setSortMenuState(false);
                    return;
                }
                const currentGeneration = new URLSearchParams(window.location.search).get('generation') || generationField?.value || initialGeneration;
                setSortMenuState(false);
                loadProducts(currentGeneration, sort, true, 'sort', sortTrigger);
            });
        });

        searchForm?.addEventListener('submit', (event) => {
            event.preventDefault();

            const currentGeneration = new URLSearchParams(window.location.search).get('generation') || initialGeneration;
            const currentSort = new URLSearchParams(window.location.search).get('sort') || initialSort;
            loadProducts(currentGeneration, currentSort, true, 'search', searchSubmitButton);
        });

        resetButton?.addEventListener('click', (event) => {
            event.preventDefault();

            if (searchInput) {
                searchInput.value = '';
            }

            loadProducts('all', sortField?.value || initialSort, true, 'reset', resetButton);
        });

        document.addEventListener('click', (event) => {
            if (!sortDropdown?.contains(event.target)) {
                setSortMenuState(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setSortMenuState(false);
            }
        });

        window.addEventListener('popstate', () => {
            const generation = new URLSearchParams(window.location.search).get('generation') || 'all';
            const sort = new URLSearchParams(window.location.search).get('sort') || 'featured';
            setSortMenuState(false);
            loadProducts(generation, sort, false);
        });

        setActiveFilter(initialGeneration);
        setActiveSort(initialSort);
        setSortMenuState(false);
        if (generationField) {
            generationField.value = initialGeneration;
        }
        if (sortField) {
            sortField.value = initialSort;
        }
    })();
    </script>
    @endpush
@endsection

@section('footer')
    <!-- Footer component -->
@endsection
