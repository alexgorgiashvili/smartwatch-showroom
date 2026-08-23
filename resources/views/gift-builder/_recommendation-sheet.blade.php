@php
    $locale = app()->getLocale();
    $budgetOptions = collect(config('gift_builder.budget_bands', []))->map(fn ($option, $slug) => [
        'slug' => $slug,
        'label' => $locale === 'ka' ? ($option['label_ka'] ?? $option['label_en'] ?? $slug) : ($option['label_en'] ?? $slug),
    ]);
    $priorityOptions = collect(config('gift_builder.recommendation_priorities', []))->map(fn ($option, $slug) => [
        'slug' => $slug,
        'label' => $locale === 'ka' ? ($option['label_ka'] ?? $option['label_en'] ?? $slug) : ($option['label_en'] ?? $slug),
    ]);
@endphp

<div class="gift-recommender" data-gift-recommender data-endpoint="{{ $recommendationRoute }}" aria-hidden="true">
    <button type="button" class="gift-recommender-backdrop" data-gift-help-close aria-label="{{ __('storefront.common.close') }}"></button>
    <section class="gift-recommender-panel" role="dialog" aria-modal="true" aria-labelledby="gift-recommender-title" tabindex="-1">
        <header>
            <div>
                <p>MyTechnic Gift Match</p>
                <h2 id="gift-recommender-title">{{ __('storefront.gift_builder.recommendation_title') }}</h2>
            </div>
            <button type="button" data-gift-help-close aria-label="{{ __('storefront.common.close') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>
        <div class="gift-recommender-scroll">
            <p>{{ __('storefront.gift_builder.recommendation_text') }}</p>
            <form data-gift-recommendation-form>
                <fieldset>
                    <legend>{{ __('storefront.gift_builder.recommendation_budget') }}</legend>
                    <div class="gift-recommender-options">
                        @foreach($budgetOptions as $index => $option)
                            <label><input type="radio" name="budget_band" value="{{ $option['slug'] }}" @checked($index === 1 || ($budgetOptions->count() === 1 && $index === 0))><span>{{ $option['label'] }}</span></label>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend>{{ __('storefront.gift_builder.recommendation_priority') }}</legend>
                    <div class="gift-recommender-options is-priority">
                        @foreach($priorityOptions as $index => $option)
                            <label><input type="radio" name="priority" value="{{ $option['slug'] }}" @checked($index === 0)><span>{{ $option['label'] }}</span></label>
                        @endforeach
                    </div>
                </fieldset>
                <button type="submit" class="gift-primary-button"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>{{ __('storefront.gift_builder.recommendation_submit') }}</button>
            </form>
            <div class="gift-recommender-result" data-gift-recommendation-result></div>
            <p class="gift-recommender-status" data-gift-recommendation-status role="status" aria-live="polite"></p>
        </div>
    </section>
    @php
        $giftRecommendationCopyPayload = [
            'loading' => __('storefront.gift_builder.recommendation_loading'),
            'error' => __('storefront.gift_builder.recommendation_error'),
            'empty' => __('storefront.gift_builder.recommendation_empty'),
            'next_budget' => __('storefront.gift_builder.recommendation_next_budget'),
            'ready' => __('storefront.gift_builder.recommendation_ready'),
            'custom' => __('storefront.gift_builder.recommendation_custom'),
            'retry' => __('storefront.gift_builder.recommendation_retry'),
            'apply' => __('storefront.gift_builder.recommendation_apply'),
            'close' => __('storefront.common.close'),
        ];
    @endphp
    <script type="application/json" data-gift-recommendation-copy>{!! json_encode($giftRecommendationCopyPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</div>
