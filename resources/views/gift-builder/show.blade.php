@extends('layouts.app')

@section('title', __('storefront.gift_builder.meta_title'))
@section('meta_description', __('storefront.gift_builder.meta_description'))
@section('robots', config('gift_builder.public_enabled') === true ? 'index, follow' : 'noindex, nofollow, noarchive')

@section('content')
    <div class="gift-experience gift-builder-page" data-gift-builder-experience>
        <section class="gift-builder-intro">
            <div class="gift-shell">
                <a href="{{ route('gift-builder.boxes') }}" class="gift-back-link">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    {{ __('storefront.gift_builder.back_to_boxes') }}
                </a>
                <div class="gift-builder-intro-row">
                    <div>
                        <div class="gift-kicker"><span aria-hidden="true">✦</span>MyTechnic Gift Box</div>
                        <h1>{{ __('storefront.gift_builder.title') }}</h1>
                        <p>{{ __('storefront.gift_builder.subtitle_v2') }}</p>
                    </div>
                    <div class="gift-builder-promise">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        <span><strong>{{ __('storefront.gift_builder.stock_checked') }}</strong>{{ __('storefront.gift_builder.at_cart_time') }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="gift-builder-workspace">
            <div class="gift-shell">
                <div id="gift-preset-overview" class="gift-preset-overview" hidden></div>
                <div id="gift-draft-restore" class="gift-draft-restore" hidden></div>

                @if(config('gift_builder.recommender_enabled'))
                    <button type="button" class="gift-help-link gift-builder-help-link" data-gift-help-open>{{ __('storefront.gift_builder.help_choose') }}</button>
                @endif

                <div id="gift-builder-app" class="gift-builder-grid">
                    <div class="gift-builder-main">
                        <nav id="gift-progress" class="gift-progress" aria-label="{{ __('storefront.gift_builder.progress_label') }}"></nav>
                        <section id="gift-live-preview" class="gift-live-preview" aria-labelledby="gift-live-preview-title">
                            <header class="gift-live-preview-header">
                                <div>
                                    <p>{{ __('storefront.gift_builder.your_box') }}</p>
                                    <h2 id="gift-live-preview-title">{{ __('storefront.gift_builder.my_box') }}</h2>
                                </div>
                                <span class="gift-live-preview-count" data-gift-live-count>0/4</span>
                            </header>
                            <p class="sr-only" data-gift-live-status role="status" aria-live="polite"></p>
                            <div class="gift-live-box" data-gift-live-box data-packaging="standard" aria-hidden="true">
                                <div class="gift-live-box-lid"></div>
                                <div class="gift-live-box-ribbon"></div>
                                <div class="gift-live-box-base">
                                    <div class="gift-live-box-tissue"></div>
                                    <div class="gift-live-slot gift-live-slot-main" data-gift-slot="main" data-flip-id="gift-slot-main">
                                        <span>{{ __('storefront.gift_builder.main_gift') }}</span>
                                        <div class="gift-live-slot-media"><i class="fa-solid fa-clock" aria-hidden="true"></i></div>
                                    </div>
                                    <div class="gift-live-slot gift-live-slot-addon gift-live-slot-addon-1" data-gift-slot="addon-1" data-flip-id="gift-slot-addon-1">
                                        <span>{{ __('storefront.gift_builder.addon') }} 1</span>
                                        <div class="gift-live-slot-media"><i class="fa-solid fa-plus" aria-hidden="true"></i></div>
                                    </div>
                                    <div class="gift-live-slot gift-live-slot-addon gift-live-slot-addon-2" data-gift-slot="addon-2" data-flip-id="gift-slot-addon-2">
                                        <span>{{ __('storefront.gift_builder.addon') }} 2</span>
                                        <div class="gift-live-slot-media"><i class="fa-solid fa-plus" aria-hidden="true"></i></div>
                                    </div>
                                    <div class="gift-live-slot gift-live-slot-addon gift-live-slot-addon-3" data-gift-slot="addon-3" data-flip-id="gift-slot-addon-3">
                                        <span>{{ __('storefront.gift_builder.addon') }} 3</span>
                                        <div class="gift-live-slot-media"><i class="fa-solid fa-plus" aria-hidden="true"></i></div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <div id="gift-content" class="gift-builder-content"></div>
                    </div>
                    <aside id="gift-summary" class="gift-builder-summary" aria-label="{{ __('storefront.gift_builder.summary') }}"></aside>
                </div>
            </div>
        </section>

        <div id="gift-mobile-bar" class="gift-builder-mobile-bar"></div>

        <div class="gift-builder-sheet" id="gift-builder-sheet" aria-hidden="true">
            <button type="button" class="gift-sheet-backdrop" data-builder-sheet-close aria-label="{{ __('storefront.common.close') }}"></button>
            <section class="gift-builder-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="gift-builder-sheet-title" tabindex="-1">
                <header>
                    <h2 id="gift-builder-sheet-title" data-builder-sheet-title>{{ __('storefront.gift_builder.my_box') }}</h2>
                    <button type="button" data-builder-sheet-close aria-label="{{ __('storefront.common.close') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </header>
                <div class="gift-builder-sheet-body" data-builder-sheet-body></div>
                <p class="gift-builder-sheet-status" data-builder-sheet-status role="status" aria-live="polite"></p>
            </section>
        </div>

        <div class="gift-completion" data-gift-completion role="status" aria-live="polite" hidden>
            <div class="gift-completion-box"><i class="fa-solid fa-gift" aria-hidden="true"></i></div>
            <strong>{{ __('storefront.gift_builder.box_ready') }}</strong>
        </div>

        @if(config('gift_builder.recommender_enabled'))
            @include('gift-builder._recommendation-sheet', ['recommendationRoute' => route('gift-builder.recommendations')])
        @endif
    </div>
@endsection

@push('scripts')
    @php
        $giftBuilderPayload = [
            'builder' => $builderConfig,
            'i18n' => trans('storefront.gift_builder'),
            'common' => trans('storefront.common'),
        ];
    @endphp
    <script type="application/json" id="gift-builder-config">{!! json_encode($giftBuilderPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush
