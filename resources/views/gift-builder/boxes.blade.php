@extends('layouts.app')

@section('title', __('storefront.gift_boxes.meta_title'))
@section('meta_description', __('storefront.gift_boxes.meta_description'))
@section('og_image', asset('images/gift-box/hero-poster.webp'))
@section('robots', config('gift_builder.public_enabled') === true ? 'index, follow' : 'noindex, nofollow, noarchive')

@push('head_meta')
    <link rel="preload" as="image" href="{{ asset('images/gift-box/hero-mobile-closed-v2.webp') }}" type="image/webp" media="(max-width: 767px)" fetchpriority="high">
    <link rel="preload" as="image" href="{{ asset('images/gift-box/hero-poster.webp') }}" type="image/webp" media="(min-width: 768px)" fetchpriority="high">
@endpush

@section('content')
    <div class="gift-experience gift-landing" data-gift-box-experience>
        <section class="gift-hero" data-gift-hero>
            <div class="gift-hero-glow gift-hero-glow-one" aria-hidden="true"></div>
            <div class="gift-hero-glow gift-hero-glow-two" aria-hidden="true"></div>

            <div class="gift-shell gift-hero-grid">
                <div class="gift-hero-copy">
                    <div class="gift-kicker">
                        <span aria-hidden="true">✦</span>
                        {{ __('storefront.gift_boxes.hero_kicker') }}
                    </div>
                    <h1>{{ __('storefront.gift_boxes.hero_title') }}</h1>
                    <p>{{ __('storefront.gift_boxes.hero_text') }}</p>

                    <div class="gift-hero-actions" aria-label="{{ __('storefront.gift_boxes.choose_path') }}">
                        <a href="#ready-gift-boxes" class="gift-primary-button" data-gift-path="ready_boxes">
                            <span>{{ __('storefront.gift_boxes.choose_ready') }}</span>
                            <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                        </a>
                        <a href="{{ route('gift-builder.show') }}" class="gift-secondary-button" data-gift-path="custom_builder">
                            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                            <span>{{ __('storefront.gift_boxes.build_myself') }}</span>
                        </a>
                    </div>

                    <div class="gift-hero-proof" aria-label="{{ __('storefront.gift_boxes.social_proof') }}">
                        <div class="gift-proof-faces" aria-hidden="true"><span>♥</span><span>★</span><span>☺</span></div>
                        <p><strong>{{ __('storefront.gift_boxes.ready_in_minutes') }}</strong><br>{{ __('storefront.gift_boxes.no_guessing') }}</p>
                    </div>
                </div>

                <div class="gift-hero-visual" data-gift-stage data-gift-state="static_closed" data-renderer="static">
                    <picture class="gift-hero-poster">
                        <source media="(max-width: 767px)" srcset="{{ asset('images/gift-box/hero-mobile-closed-v2.webp') }}" type="image/webp">
                        <source srcset="{{ asset('images/gift-box/hero-poster.webp') }}" type="image/webp">
                        <img src="{{ asset('images/gift-builder/hero-gift-box-v2.jpg') }}" width="1600" height="900" alt="" fetchpriority="high" decoding="async">
                    </picture>
                    <img class="gift-hero-open-poster" src="{{ asset('images/gift-box/hero-mobile-open-v2.webp') }}" width="1370" height="1712" alt="" decoding="async" aria-hidden="true">
                    <div class="gift-canvas" data-gift-canvas></div>
                    <button type="button" class="gift-open-trigger" data-gift-open aria-describedby="gift-open-status">
                        <i class="fa-solid fa-hand-pointer" aria-hidden="true"></i>
                        <span data-gift-open-label>{{ __('storefront.gift_boxes.touch_to_open') }}</span>
                    </button>
                    <button type="button" class="gift-effects-toggle" data-gift-effects aria-pressed="false">
                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        <span>{{ __('storefront.gift_boxes.disable_effects') }}</span>
                    </button>
                    <p id="gift-open-status" class="sr-only" data-gift-open-status role="status" aria-live="polite">{{ __('storefront.gift_boxes.box_closed_status') }}</p>
                    <span class="gift-float-chip gift-float-chip-one" aria-hidden="true"><i class="fa-solid fa-truck-fast"></i>{{ __('storefront.gift_boxes.free_delivery_short') }}</span>
                    <span class="gift-float-chip gift-float-chip-two" aria-hidden="true"><i class="fa-solid fa-gift"></i>{{ __('storefront.gift_boxes.gift_ready') }}</span>
                </div>
            </div>
        </section>

        <section class="gift-trust-strip" aria-label="{{ __('storefront.gift_boxes.why_trust') }}">
            <div class="gift-shell gift-trust-grid">
                <div>
                    <span class="gift-trust-icon is-mint"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i></span>
                    <p><strong>{{ __('storefront.gift_boxes.free_delivery') }}</strong><small>{{ __('storefront.gift_boxes.across_georgia') }}</small></p>
                </div>
                <div>
                    <span class="gift-trust-icon is-coral"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i></span>
                    <p><strong>{{ __('storefront.gift_boxes.local_stock') }}</strong><small>{{ __('storefront.gift_boxes.stock_checked') }}</small></p>
                </div>
                <div>
                    <span class="gift-trust-icon is-grape"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
                    <p><strong>{{ __('storefront.gift_boxes.warranty') }}</strong><small>{{ __('storefront.gift_boxes.warranty_terms') }}</small></p>
                </div>
            </div>
        </section>

        <section id="ready-gift-boxes" class="gift-ready-section">
            <div class="gift-shell">
                <div class="gift-section-heading">
                    <div>
                        <div class="gift-kicker"><span aria-hidden="true">✦</span>{{ __('storefront.gift_boxes.curated_kicker') }}</div>
                        <h2>{{ __('storefront.gift_boxes.ready_title') }}</h2>
                        <p>{{ __('storefront.gift_boxes.ready_text') }}</p>
                    </div>
                    <a href="{{ route('gift-builder.show') }}" data-gift-path="custom_builder_section">
                        {{ __('storefront.gift_boxes.or_build_yours') }}
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                    @if(config('gift_builder.recommender_enabled'))
                        <button type="button" class="gift-help-link" data-gift-help-open>{{ __('storefront.gift_builder.help_choose') }}</button>
                    @endif
                </div>

                <div class="gift-box-grid">
                    @forelse($readyBoxes as $box)
                        @php
                            $items = collect(data_get($box, 'items', []));
                            $slug = (string) data_get($box, 'slug', '');
                            $title = data_get($box, 'title') ?: data_get($box, 'label', $slug);
                            $description = data_get($box, 'description', '');
                            $badge = data_get($box, 'badge');
                            $cover = data_get($box, 'cover_image');
                            $theme = in_array(data_get($box, 'theme_key'), ['grape', 'coral', 'mint'], true) ? data_get($box, 'theme_key') : 'grape';
                            $total = (float) data_get($box, 'total', 0);
                            $discountAmount = (float) (data_get($box, 'discount.amount') ?? data_get($box, 'discount_amount', 0));
                            $originalTotal = (float) (data_get($box, 'original_total') ?: ($total + $discountAmount));
                            $discountLabel = data_get($box, 'discount.label');
                            $builderUrl = data_get($box, 'builder_url') ?: url('/gift-box-builder?box=' . urlencode($slug));
                            $optionsUrl = data_get($box, 'options_url') ?: url('/gift-boxes/' . urlencode($slug) . '/options');
                            $addUrl = data_get($box, 'add_to_cart_url') ?: url('/gift-boxes/' . urlencode($slug) . '/add-to-cart');
                        @endphp
                        <article class="gift-box-card gift-theme-{{ $theme }}" data-gift-box-card data-box-slug="{{ $slug }}">
                            <div class="gift-box-media">
                                @if($cover)
                                    <img src="{{ $cover }}" alt="{{ $title }}" loading="lazy" decoding="async">
                                @else
                                    <div class="gift-box-mosaic">
                                        @foreach($items->take(4) as $index => $item)
                                            @php
                                                $product = data_get($item, 'product', $item);
                                                $image = data_get($product, 'image', data_get($item, 'image'));
                                                $name = data_get($product, 'name', data_get($item, 'name', ''));
                                            @endphp
                                            @if($image)
                                                <img src="{{ $image }}" alt="{{ $name }}" loading="lazy" decoding="async" class="gift-mosaic-item-{{ $index + 1 }}">
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                @if($badge)
                                    <span class="gift-box-badge"><i class="fa-solid fa-star" aria-hidden="true"></i>{{ $badge }}</span>
                                @endif
                                <span class="gift-box-count">{{ trans_choice('storefront.gift_boxes.item_count', $items->count(), ['count' => $items->count()]) }}</span>
                            </div>

                            <div class="gift-box-body">
                                <div class="gift-box-title-row">
                                    <div>
                                        <p class="gift-box-eyebrow">{{ __('storefront.gift_boxes.complete_set') }}</p>
                                        <h3>{{ $title }}</h3>
                                    </div>
                                    <div class="gift-box-price">
                                        @if($discountAmount > 0 && $originalTotal > $total)
                                            <del>{{ number_format($originalTotal, 2) }} ₾</del>
                                        @endif
                                        <strong>{{ number_format($total, 2) }} ₾</strong>
                                    </div>
                                </div>

                                @if($description)
                                    <p class="gift-box-description">{{ $description }}</p>
                                @endif

                                <ul class="gift-box-items">
                                    @foreach($items as $item)
                                        @php
                                            $product = data_get($item, 'product', $item);
                                        @endphp
                                        <li>
                                            <span><i class="fa-solid {{ data_get($item, 'role') === 'main' ? 'fa-clock' : 'fa-check' }}" aria-hidden="true"></i></span>
                                            <p><strong>{{ data_get($product, 'name', data_get($item, 'name')) }}</strong>@if(data_get($item, 'role') === 'main')<small>{{ __('storefront.gift_boxes.main_gift') }}</small>@endif</p>
                                        </li>
                                    @endforeach
                                    <li>
                                        <span><i class="fa-solid fa-gift" aria-hidden="true"></i></span>
                                        <p><strong>{{ data_get($box, 'packaging_label') }}</strong><small>{{ __('storefront.gift_boxes.packaging_included') }}</small></p>
                                    </li>
                                </ul>

                                @if($discountAmount > 0)
                                    <p class="gift-discount-note"><i class="fa-solid fa-tag" aria-hidden="true"></i>{{ $discountLabel ?: __('storefront.gift_boxes.you_save', ['amount' => number_format($discountAmount, 2) . ' ₾']) }}</p>
                                @endif

                                <div class="gift-box-actions">
                                    <button type="button"
                                            class="gift-primary-button"
                                            data-gift-quick-open
                                            data-gift-path="quick_buy"
                                            data-box-slug="{{ $slug }}"
                                            data-box-title="{{ $title }}"
                                            data-options-url="{{ $optionsUrl }}"
                                            data-add-url="{{ $addUrl }}">
                                        <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                                        <span>{{ __('storefront.gift_boxes.quick_buy') }}</span>
                                    </button>
                                    <a href="{{ $builderUrl }}" class="gift-tertiary-button" data-gift-path="customize_preset" data-gift-customize data-box-slug="{{ $slug }}">
                                        <span>{{ __('storefront.gift_boxes.customize') }}</span>
                                        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="gift-empty-state">
                            <span><i class="fa-solid fa-box-open" aria-hidden="true"></i></span>
                            <h3>{{ __('storefront.gift_boxes.empty_title') }}</h3>
                            <p>{{ __('storefront.gift_boxes.empty_text') }}</p>
                            <a href="{{ route('gift-builder.show') }}" class="gift-primary-button" data-gift-path="custom_builder_empty">{{ __('storefront.gift_boxes.build_myself') }}</a>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="gift-how-section">
            <div class="gift-shell">
                <div class="gift-how-card">
                    <div class="gift-how-copy">
                        <div class="gift-kicker"><span aria-hidden="true">✦</span>{{ __('storefront.gift_boxes.custom_kicker') }}</div>
                        <h2>{{ __('storefront.gift_boxes.custom_title') }}</h2>
                        <p>{{ __('storefront.gift_boxes.custom_text') }}</p>
                        <a href="{{ route('gift-builder.show') }}" class="gift-primary-button" data-gift-path="custom_builder_bottom">
                            {{ __('storefront.gift_boxes.start_building') }}
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                    <ol class="gift-how-steps">
                        <li><span>1</span><p><strong>{{ __('storefront.gift_boxes.step_watch') }}</strong><small>{{ __('storefront.gift_boxes.step_watch_text') }}</small></p></li>
                        <li><span>2</span><p><strong>{{ __('storefront.gift_boxes.step_addons') }}</strong><small>{{ __('storefront.gift_boxes.step_addons_text') }}</small></p></li>
                        <li><span>3</span><p><strong>{{ __('storefront.gift_boxes.step_finish') }}</strong><small>{{ __('storefront.gift_boxes.step_finish_text') }}</small></p></li>
                    </ol>
                </div>
            </div>
        </section>

        <div class="gift-mobile-landing-cta" data-gift-mobile-cta aria-label="{{ __('storefront.gift_boxes.choose_path') }}" hidden>
            <a href="#ready-gift-boxes" class="gift-primary-button" data-gift-path="ready_boxes_sticky">{{ __('storefront.gift_boxes.choose_ready_short') }}</a>
            <a href="{{ route('gift-builder.show') }}" class="gift-mobile-builder-link" data-gift-path="custom_builder_sticky"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span>{{ __('storefront.gift_boxes.build_myself') }}</span></a>
        </div>

        @if(config('gift_builder.recommender_enabled'))
            @include('gift-builder._recommendation-sheet', ['recommendationRoute' => route('gift-builder.recommendations')])
        @endif

        <div class="gift-quick-modal" data-gift-quick-modal aria-hidden="true">
            <section class="gift-quick-panel" data-gift-quick-panel role="dialog" aria-modal="true" aria-labelledby="gift-quick-title" tabindex="-1">
                <header>
                    <div>
                        <p>{{ __('storefront.gift_boxes.quick_buy_kicker') }}</p>
                        <h2 id="gift-quick-title" data-gift-quick-title>{{ __('storefront.gift_boxes.quick_buy_title') }}</h2>
                    </div>
                    <button type="button" data-gift-quick-close aria-label="{{ __('storefront.common.close') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </header>
                <div class="gift-quick-scroll">
                    <div data-gift-quick-body></div>
                    <p class="gift-quick-status" data-gift-quick-status role="status" aria-live="polite" hidden></p>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    @php
        $giftBoxCopyPayload = [
            'quick' => trans('storefront.gift_boxes.quick'),
            'experience' => trans('storefront.gift_boxes.experience'),
        ];
    @endphp
    <script type="application/json" id="gift-box-copy">{!! json_encode($giftBoxCopyPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush
