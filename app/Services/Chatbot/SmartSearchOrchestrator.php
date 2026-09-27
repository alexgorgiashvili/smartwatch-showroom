<?php

namespace App\Services\Chatbot;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SmartSearchOrchestrator
{
    public function __construct(
        private RagContextBuilder $ragBuilder,
        private UnifiedAiPolicyService $policy
    ) {
    }

    public function search(IntentResult $intent, bool $widget = false, ?string $originalMessage = null): SearchContext
    {
        $standaloneQuery = trim($intent->standaloneQuery());
        $query = $standaloneQuery !== ''
            ? $standaloneQuery
            : $this->policy->normalizeIncomingMessage($standaloneQuery);

        $products = $this->lookupProducts($intent, $widget, $originalMessage);
        $requestedProduct = $widget
            ? $this->requestedWidgetProduct($products, $originalMessage)
            : $products->first();
        $notFoundMessage = null;

        if ($intent->hasSpecificProduct() && $products->isEmpty()) {
            $brand = $intent->brand();
            $model = $intent->model();
            $label = trim(implode(' ', array_filter([$brand, $model])));
            $notFoundMessage = 'მოთხოვნილი პროდუქტი (' . $label . ') ამჟამად ჩვენს კატალოგში არ არის.';
        }

        $ragContext = $this->shouldBuildRagContext($intent, $products, $notFoundMessage)
            ? ($this->ragBuilder->build($query !== '' ? $query : $intent->intent(), 5, [], $intent) ?? '')
            : '';

        return new SearchContext(
            $ragContext,
            $products,
            $requestedProduct,
            $notFoundMessage
        );
    }

    private function shouldBuildRagContext(IntentResult $intent, Collection $products, ?string $notFoundMessage): bool
    {
        if ($notFoundMessage !== null) {
            return true;
        }

        if ($products->isEmpty()) {
            return true;
        }

        return match ($intent->intent()) {
            'general', 'comparison' => true,
            default => false,
        };
    }

    private function requestedWidgetProduct(Collection $products, ?string $message): ?Product
    {
        if ($message === null || trim($message) === '') {
            return null;
        }

        $named = $products->filter(static function (Product $product) use ($message): bool {
            $model = trim((string) ($product->model ?? ''));
            $slug = trim((string) $product->slug);

            return ($model !== '' && preg_match('/(?<![\p{L}\p{N}-])' . preg_quote($model, '/') . '(?![\p{L}\p{N}-])/iu', $message) === 1)
                || ($slug !== '' && preg_match('/(?<![\p{L}\p{N}-])' . preg_quote($slug, '/') . '(?![\p{L}\p{N}-])/iu', $message) === 1);
        })->values();

        return $named->count() === 1 ? $named->first() : null;
    }

    private function lookupProducts(IntentResult $intent, bool $widget, ?string $originalMessage): Collection
    {
        if ($widget && !$intent->hasSpecificProduct() && !$intent->hasCatalogFacet()
            && $originalMessage !== null && $this->isContextDependentWidgetQuestion($originalMessage)) {
            return collect();
        }

        $slugHint = $intent->productSlugHint();
        $limit = 6;

        if ($widget && $originalMessage !== null
            && preg_match('/^\s*(\d{2,4})\s+(?:გაქვთ|არის)\s+(?:საათ|სათ)/iu', $originalMessage, $priceQuestion) === 1) {
            $quotedPrice = (float) $priceQuestion[1];
            $pricedProducts = $this->baseProductQuery()->limit(50)->get()
                ->filter(fn (Product $product): bool => abs((float) ($product->sale_price ?: $product->price) - $quotedPrice) < 0.01)
                ->take($limit)->values();
            if ($pricedProducts->isNotEmpty()) {
                return $pricedProducts;
            }
        }

        // Customers often spell the published Wonlex brand in Georgian and
        // compare a budget with another watch identified only by its price.
        if ($widget && $originalMessage !== null
            && preg_match('/ვონლექს/iu', $originalMessage) === 1
            && preg_match('/(?<!\d)(\d{2,4})\s*(?:₾|ლარ)/iu', $originalMessage, $budgetMatch) === 1
            && preg_match('/\b(?:KT|CT)\s*\d+\b/iu', $originalMessage) !== 1) {
            $budget = (int) $budgetMatch[1];
            $withinBudget = $this->baseProductQuery()
                ->where('brand', 'like', '%Wonlex%')
                ->limit(50)->get()
                ->filter(fn (Product $product): bool => (float) ($product->sale_price ?: $product->price) <= $budget)
                ->sortBy(fn (Product $product): float => (float) ($product->sale_price ?: $product->price))
                ->take($limit)->values();
            if ($withinBudget->isNotEmpty()) {
                if (preg_match('/ჯობს|იგივე|შეადარ|თუ/iu', $originalMessage) === 1
                    && preg_match_all('/(?<!\d)(\d{2,4})\s*(?:₾|ლარ)/iu', $originalMessage, $priceMatches) > 1) {
                    foreach (array_unique(array_map('intval', $priceMatches[1])) as $quotedPrice) {
                        if ($quotedPrice === $budget || $quotedPrice < 20) {
                            continue;
                        }
                        $pricedProducts = $this->baseProductQuery()->limit(50)->get()
                            ->filter(fn (Product $product): bool => (float) ($product->sale_price ?: $product->price) === (float) $quotedPrice);
                        $withinBudget = $withinBudget->concat($pricedProducts)->unique('id')->take($limit)->values();
                    }
                }
                return $withinBudget;
            }
        }

        if ($slugHint !== null) {
            $exact = $this->baseProductQuery()
                ->where('slug', $slugHint)
                ->get();

            if ($exact->isNotEmpty()) {
                return $exact;
            }

            $fuzzy = $this->baseProductQuery()
                ->where('slug', 'like', '%' . $slugHint . '%')
                ->limit($limit * 2)
                ->get();

            if ($fuzzy->isNotEmpty()) {
                return $this->rankProducts($fuzzy, $intent)->take($limit)->values();
            }
        }

        $brand = $intent->brand();
        $model = $intent->model();

        if ($brand !== null || $model !== null) {
            $brandModelQuery = $this->baseProductQuery();

            if ($brand !== null) {
                $brandModelQuery->where(function (Builder $query) use ($brand): void {
                    $query->where('brand', 'like', '%' . $brand . '%')
                        ->orWhere('name_en', 'like', '%' . $brand . '%')
                        ->orWhere('name_ka', 'like', '%' . $brand . '%')
                        ->orWhere('slug', 'like', '%' . $brand . '%');
                });
            }

            if ($model !== null) {
                $brandModelQuery->where(function (Builder $query) use ($model): void {
                    $query->where('model', 'like', '%' . $model . '%')
                        ->orWhere('name_en', 'like', '%' . $model . '%')
                        ->orWhere('name_ka', 'like', '%' . $model . '%')
                        ->orWhere('slug', 'like', '%' . $model . '%');
                });
            }

            $brandModel = $brandModelQuery->limit($limit * 2)->get();
            $augmentedProducts = $brandModel;

            if ($intent->hasSpecificProduct() && $brand !== null && $brandModel->count() < 2) {
                $brandOnlyQuery = $this->baseProductQuery();
                $brandOnlyQuery->where(function (Builder $query) use ($brand): void {
                    $query->where('brand', 'like', '%' . $brand . '%')
                        ->orWhere('name_en', 'like', '%' . $brand . '%')
                        ->orWhere('name_ka', 'like', '%' . $brand . '%')
                        ->orWhere('slug', 'like', '%' . $brand . '%');
                });

                $brandOnly = $brandOnlyQuery->limit($limit * 2)->get();

                if ($brandOnly->isNotEmpty()) {
                    $augmentedProducts = $brandModel
                        ->merge($brandOnly)
                        ->unique(fn (Product $product): int => (int) $product->id)
                        ->values();
                }
            }

            if ($augmentedProducts->isNotEmpty()) {
                if ($intent->intent() === 'comparison') {
                    return $this->augmentComparisonProducts($augmentedProducts, $intent, $limit);
                }

                return $this->rankProducts($augmentedProducts, $intent)->take($limit)->values();
            }
        }

        $facetProducts = $this->lookupCatalogFacetProducts($intent);
        if ($facetProducts->isNotEmpty()) {
            return $this->rankProducts($facetProducts, $intent)
                ->take($this->catalogFacetLimit($facetProducts, $limit))
                ->values();
        }

        if ($widget && !$intent->hasSpecificProduct()) {
            $widgetMatches = $this->lookupWidgetQuestionProducts($originalMessage ?: $intent->standaloneQuery());
            if ($widgetMatches->isNotEmpty()) {
                return $widgetMatches;
            }
        }

        $keywords = $intent->searchKeywords();

        if ($keywords !== []) {
            $keywordQuery = $this->baseProductQuery();

            $keywordQuery->where(function (Builder $query) use ($keywords): void {
                foreach ($keywords as $keyword) {
                    $query->orWhere('name_en', 'like', '%' . $keyword . '%')
                        ->orWhere('name_ka', 'like', '%' . $keyword . '%')
                        ->orWhere('slug', 'like', '%' . $keyword . '%')
                        ->orWhere('brand', 'like', '%' . $keyword . '%')
                        ->orWhere('model', 'like', '%' . $keyword . '%');
                }
            });

            $keywordMatches = $keywordQuery->limit($limit * 2)->get();

            if ($keywordMatches->isNotEmpty()) {
                return $this->rankProducts($keywordMatches, $intent)->take($limit)->values();
            }
        }

        return collect();
    }

    private function isContextDependentWidgetQuestion(string $message): bool
    {
        $question = mb_strtolower(trim($message));
        if (preg_match('/\d+\s*(?:₾|ლარ)/iu', $question) === 1) {
            return false;
        }

        if (preg_match('/ფოტო|ვიდეო/iu', $question) === 1
            && preg_match('/გამომიგზავ|გამოგზავ|მომაწოდ|აჩვენ/iu', $question) === 1) {
            return true;
        }

        return preg_match('/^(?:და\s+|ეს\s+|ამას\s+|იმას\s+)/iu', $question) === 1
            && preg_match('/რომელ\p{L}*\s+მოდელ|რომელ\p{L}*\s+საათ|მოდელებ|რა\s+გაქვთ/iu', $question) !== 1;
    }

    /** Use public catalog fields for broad widget questions that have no model name. */
    private function lookupWidgetQuestionProducts(string $message): Collection
    {
        $question = mb_strtolower(trim($message));
        if ($question === '') {
            return collect();
        }

        // A follow-up about "this" device needs conversation context; do not
        // silently pick the first catalog model as if the customer named it.
        if (preg_match('/^(?:და\s+)?(?:ეს|ამას|ამის|იმას|ლოკაციაც)(?!\p{L})/iu', $question) === 1
            && preg_match('/რომელ\p{L}*\s+მოდელ|რომელ\p{L}*\s+საათ/iu', $question) !== 1) {
            return collect();
        }

        $feature = match (true) {
            preg_match('/ვიდეო\s*ზარ|video\s*call/iu', $question) === 1 => 'video',
            preg_match('/კამერ|camera/iu', $question) === 1 => 'camera',
            preg_match('/წყალ|waterproof|water resistant/iu', $question) === 1 => 'water',
            preg_match('/gps|გეოლოკაცი|ლოკაცი|მდებარეობ/iu', $question) === 1 => 'gps',
            preg_match('/(?:სიმ|sim)\s*(?:ბარათ|კარტ|card)/iu', $question) === 1 => 'sim',
            default => null,
        };

        preg_match('/შავი|ვარდისფერი|ლურჯი|მწვანე|იასამნისფერი/iu', $question, $color);
        $isBroadQuestion = preg_match('/გაქვთ საათები|რა არჩევანი|სხვა.{0,12}მოდელ|ყველაზე იაფ|იაფიანი მოდელ|ბიუჯეტურ|საუკეთესო მოდელ|ყველაზე კარგი|შეკვეთას\?|შეკვეთას გამიფორმ|მირჩევ|მირჩიე|შემირჩი|შევარჩიო|რა\s*ღირს.{0,20}საათ|საათებ\p{L}*.{0,15}ფას|ფასებ\p{L}*.{0,15}საათ|საბავშვო.{0,25}ფას|ფას.{0,25}საბავშვო|მოდელ.{0,20}ფას/iu', $question) === 1
            || (preg_match('/\d+\s*(?:₾|ლარ)/iu', $question) === 1
                && preg_match('/ვიყიდ|მომივა|შეიძ|ვარიანტ|საათ/iu', $question) === 1);
        if ($feature === null && $color === [] && !$isBroadQuestion) {
            return collect();
        }

        $catalogQuery = $this->baseProductQuery();
        if ($feature === null && $color === []) {
            // Apply the limit after price ordering so "cheapest" can see the
            // store's lowest-priced active products even as the catalog grows.
            $catalogQuery->orderByRaw('COALESCE(NULLIF(sale_price, 0), price) ASC');
        }
        $candidates = $catalogQuery->limit(50)->get();

        if ($feature !== null) {
            return $candidates->filter(function (Product $product) use ($feature): bool {
                $functions = mb_strtolower(implode(' ', array_filter((array) ($product->functions ?? []), 'is_string')));
                $name = mb_strtolower((string) $product->name);
                return match ($feature) {
                    'video' => preg_match('/ვიდეო\s*ზარ|video\s*call/iu', $functions . ' ' . $name) === 1,
                    'camera' => trim((string) $product->camera) !== '' || preg_match('/კამერ|camera/iu', $functions . ' ' . $name) === 1,
                    'water' => trim((string) $product->water_resistant) !== '',
                    'gps' => preg_match('/gps|გეოლოკაცი/iu', $functions . ' ' . $name) === 1,
                    'sim' => (bool) $product->sim_support,
                };
            })->take(6)->values();
        }

        if ($color !== []) {
            return $candidates->filter(fn (Product $product): bool => $product->variants->contains(
                fn ($variant): bool => str_contains(mb_strtolower((string) $variant->color_name), $color[0])
                    && $variant->available_quantity > 0
            ))->take(6)->values();
        }

        return $candidates->sortBy(fn (Product $product): float => (float) ($product->sale_price ?: $product->price))
            ->take(6)->values();
    }

    private function lookupCatalogFacetProducts(IntentResult $intent): Collection
    {
        if (!$intent->hasCatalogFacet()) {
            return collect();
        }

        $candidates = $this->baseProductQuery()
            ->limit(50)
            ->get();

        $generations = [];

        if ($intent->mentionsTwoGCatalog()) {
            $generations[] = '2g';
        }

        if ($intent->mentionsFourGCatalog()) {
            $generations[] = '4g';
        }

        return $candidates
            ->filter(function (Product $product) use ($generations, $intent): bool {
                if ($intent->mentionsDiscountCatalog() && !$this->productIsDiscounted($product)) {
                    return false;
                }

                if ($generations === []) {
                    return true;
                }

                foreach ($generations as $generation) {
                    if ($this->productHasGeneration($product, $generation)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    private function catalogFacetLimit(Collection $products, int $defaultLimit): int
    {
        return min(max($defaultLimit, $products->count()), 8);
    }

    private function augmentComparisonProducts(Collection $seedProducts, IntentResult $intent, int $limit): Collection
    {
        $keywordMatches = collect();

        if ($intent->searchKeywords() !== []) {
            $keywordQuery = $this->baseProductQuery();

            $keywordQuery->where(function (Builder $query) use ($intent): void {
                foreach ($intent->searchKeywords() as $keyword) {
                    $query->orWhere('name_en', 'like', '%' . $keyword . '%')
                        ->orWhere('name_ka', 'like', '%' . $keyword . '%')
                        ->orWhere('slug', 'like', '%' . $keyword . '%')
                        ->orWhere('brand', 'like', '%' . $keyword . '%')
                        ->orWhere('model', 'like', '%' . $keyword . '%');
                }
            });

            $keywordMatches = $keywordQuery->limit($limit * 2)->get();
        }

        return $this->rankProducts($seedProducts->merge($keywordMatches)->unique(fn (Product $product): int => (int) $product->id), $intent)
            ->take($limit)
            ->values();
    }

    private function rankProducts(Collection $products, IntentResult $intent): Collection
    {
        return $products
            ->sortByDesc(fn (Product $product): int => $this->productMatchScore($product, $intent))
            ->values();
    }

    private function productMatchScore(Product $product, IntentResult $intent): int
    {
        $score = 0;
        $slug = $this->normalizeSearchText((string) $product->slug);
        $brand = $this->normalizeSearchText((string) ($product->brand ?? ''));
        $model = $this->normalizeSearchText((string) ($product->model ?? ''));
        $name = $this->normalizeSearchText((string) ($product->name ?? ''));
        $combined = trim(implode(' ', array_filter([$brand, $model, $name, $slug])));

        $slugHint = $this->normalizeSearchText((string) ($intent->productSlugHint() ?? ''));
        $intentBrand = $this->normalizeSearchText((string) ($intent->brand() ?? ''));
        $intentModel = $this->normalizeSearchText((string) ($intent->model() ?? ''));
        $intentPhrase = trim(implode(' ', array_filter([$intentBrand, $intentModel])));

        if ($slugHint !== '') {
            if ($slug === $slugHint) {
                $score += 30;
            } elseif ($this->containsWholePhrase($slug, $slugHint) || $this->containsWholePhrase($name, $slugHint)) {
                $score += 12;
            }
        }

        if ($intentPhrase !== '') {
            if ($name === $intentPhrase || trim(implode(' ', array_filter([$brand, $model]))) === $intentPhrase) {
                $score += 20;
            } elseif ($this->containsWholePhrase($combined, $intentPhrase)) {
                $score += 8;
            }
        }

        if ($intentBrand !== '') {
            if ($brand === $intentBrand) {
                $score += 8;
            } elseif ($this->containsWholePhrase($combined, $intentBrand)) {
                $score += 3;
            }
        }

        if ($intentModel !== '') {
            if ($model === $intentModel) {
                $score += 12;
            } elseif ($name === $intentModel || $slug === $intentModel) {
                $score += 10;
            } elseif ($this->containsWholePhrase($combined, $intentModel)) {
                $score += 4;
            }
        }

        if ($intent->mentionsDiscountCatalog() && $this->productIsDiscounted($product)) {
            $score += 20;
        }

        if ($intent->mentionsTwoGCatalog() && $this->productHasGeneration($product, '2g')) {
            $score += 12;
        }

        if ($intent->mentionsFourGCatalog() && $this->productHasGeneration($product, '4g')) {
            $score += 12;
        }

        foreach ($intent->searchKeywords() as $keyword) {
            $normalizedKeyword = $this->normalizeSearchText((string) $keyword);

            if ($normalizedKeyword === '' || mb_strlen($normalizedKeyword) < 3) {
                continue;
            }

            if ($this->containsWholePhrase($combined, $normalizedKeyword)) {
                $score += 2;
            }
        }

        return $score;
    }

    private function normalizeSearchText(string $value): string
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value));

        return trim((string) $normalized);
    }

    private function containsWholePhrase(string $haystack, string $needle): bool
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        return str_contains(' ' . $haystack . ' ', ' ' . $needle . ' ');
    }

    private function productHasGeneration(Product $product, string $generation): bool
    {
        $haystack = $this->normalizeSearchText(implode(' ', array_filter([
            (string) $product->name,
            (string) $product->name_en,
            (string) $product->name_ka,
            (string) $product->slug,
            (string) $product->brand,
            (string) $product->model,
        ])));

        $patterns = $generation === '2g'
            ? [
                '/(?:^|\s)2\s*g(?:\s|$)/u',
                '/(?:^|\s)2\s*გ(?:\s|$)/u',
                '/(?:^|\s)2გ(?:\s|$)/u',
            ]
            : [
                '/(?:^|\s)4\s*g(?:\s|$)/u',
                '/(?:^|\s)4\s*გ(?:\s|$)/u',
                '/(?:^|\s)4გ(?:\s|$)/u',
            ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    private function productIsDiscounted(Product $product): bool
    {
        return is_numeric($product->sale_price) && (float) $product->sale_price > 0;
    }

    private function baseProductQuery(): Builder
    {
        return Product::query()
            ->active()
            ->with(['primaryImage', 'variants'])
            ->withSum('variants as total_stock', 'quantity');
    }
}
