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

    public function search(IntentResult $intent): SearchContext
    {
        $standaloneQuery = trim($intent->standaloneQuery());
        $query = $standaloneQuery !== ''
            ? $standaloneQuery
            : $this->policy->normalizeIncomingMessage($standaloneQuery);

        $products = $this->lookupProducts($intent);
        $requestedProduct = $products->first();
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

    private function lookupProducts(IntentResult $intent): Collection
    {
        $slugHint = $intent->productSlugHint();
        $limit = 6;

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
