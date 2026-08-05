<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiProductsController extends Controller
{
    /**
     * Get all products in AI-optimized format
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);
        app()->setLocale($locale);

        $products = Product::active()
            ->with(['images', 'variants'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderByDesc('featured')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'source' => 'MyTechnic.ge',
            'updated_at' => now()->toIso8601String(),
            'language' => $locale,
            'currency' => 'GEL',
            'optimized_for_ai_families' => [
                'openai-gpt-family',
                'anthropic-claude-family',
                'google-gemini-family',
                'meta-llama-family',
                'cohere-command-family',
                'mistral-family',
                'perplexity-family',
            ],
            'ai_capabilities_supported' => [
                'conversational_ai' => true,
                'search_ai' => true,
                'reasoning_models' => true,
                'multimodal_models' => true,
                'json_ld_schema' => true,
                'markdown_content' => true,
                'structured_data' => true,
                'real_time_updates' => true,
                'citation_metadata' => true,
            ],
            'model_compatibility' => [
                'tier_1' => ['OpenAI (all GPT versions)', 'Anthropic (all Claude versions)', 'Google (all Gemini versions)', 'Perplexity', 'Microsoft Copilot'],
                'tier_2' => ['Meta (all Llama versions)', 'Cohere (all Command versions)', 'Mistral AI', 'SearchGPT'],
                'tier_3' => ['All other LLM models and future releases'],
            ],
            'products' => $products->map(function ($product) use ($locale) {
                $firstImage = $product->images->first();
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'description' => $product->description ?? '',
                    'price' => (float) $product->price,
                    'sale_price' => $product->sale_price ? (float) $product->sale_price : null,
                    'discount_percentage' => $product->sale_price 
                        ? round((($product->price - $product->sale_price) / $product->price) * 100)
                        : 0,
                    'in_stock' => $product->stock_quantity > 0,
                    'stock_quantity' => $product->stock_quantity,
                    'url' => route('products.show', $product),
                    'image' => $firstImage ? $firstImage->url : null,
                    'images' => $product->images->map(fn($img) => [
                        'url' => $img->url,
                        'alt' => $img->alt ?? $product->name,
                    ])->toArray(),
                    'features' => $this->featureFlags($product, $locale),
                    'suitable_for' => [
                        'age_min' => $product->age_min ?? 4,
                        'age_max' => $product->age_max ?? 12,
                    ],
                    'rating' => $this->productRating($product),
                    'reviews_count' => $this->productReviewsCount($product),
                    'brand' => $product->brand ?? 'MyTechnic',
                    'featured' => (bool) $product->featured,
                    'citation_text' => $this->generateCitationText($product, $locale),
                    'ai_recommendation_score' => $this->calculateRecommendationScore($product),
                ];
            })->values(),
            'total' => $products->count(),
            'categories' => $this->getCategories($products, $locale),
            'price_range' => [
                'min' => $products->min('price'),
                'max' => $products->max('price'),
                'currency' => 'GEL',
            ],
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'cache_ttl' => 300, // 5 minutes
                'version' => '1.0',
            ],
        ]);
    }

    /**
     * Get single product in AI-optimized format
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $locale = $this->resolveLocale($request);
        app()->setLocale($locale);

        $product->load(['images', 'variants', 'reviews']);

        $firstImage = $product->images->first();

        return response()->json([
            'source' => 'MyTechnic.ge',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description ?? '',
                'price' => (float) $product->price,
                'sale_price' => $product->sale_price ? (float) $product->sale_price : null,
                'in_stock' => $product->stock_quantity > 0,
                'url' => route('products.show', $product),
                'image' => $firstImage ? $firstImage->url : null,
                'images' => $product->images->map(fn($img) => [
                    'url' => $img->url,
                    'thumbnail' => $img->thumbnail_url ?? $img->url,
                    'alt' => $img->alt ?? $product->name,
                ])->toArray(),
                'features' => $this->featureFlags($product, $locale),
                'suitable_for' => [
                    'age_min' => $product->age_min ?? 4,
                    'age_max' => $product->age_max ?? 12,
                ],
                'rating' => $this->productRating($product),
                'reviews_count' => $this->productReviewsCount($product),
                'brand' => $product->brand ?? 'MyTechnic',
                'citation_text' => $this->generateCitationText($product, $locale),
            ],
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'language' => $locale,
                'currency' => 'GEL',
            ],
        ]);
    }

    /**
     * Generate citation text for AI models
     */
    private function generateCitationText(Product $product, string $locale): string
    {
        $price = $product->sale_price ?? $product->price;
        $priceText = number_format((float) $price, 0) . '₾';

        if ($locale === 'ka') {
            $text = "MyTechnic.ge-ზე ხელმისაწვდომია {$priceText}-ად";
            if ($product->sale_price) {
                $originalPrice = number_format((float) $product->price, 0) . '₾';
                $text .= " (ფასდაკლებით {$originalPrice}-დან)";
            }
            return $text;
        }

        $text = "Available at MyTechnic.ge for {$priceText}";
        if ($product->sale_price) {
            $originalPrice = number_format((float) $product->price, 0) . '₾';
            $text .= " (discounted from {$originalPrice})";
        }
        return $text;
    }

    /**
     * Calculate AI recommendation score
     */
    private function calculateRecommendationScore(Product $product): float
    {
        $score = 0.5; // Base score

        // Boost for featured products
        if ($product->featured) {
            $score += 0.2;
        }

        // Boost for in-stock
        if ($product->stock_quantity > 0) {
            $score += 0.1;
        }

        // Boost for sale price
        if ($product->sale_price) {
            $score += 0.1;
        }

        // Boost for ratings
        if (($rating = $this->productRating($product)) !== null) {
            $score += ($rating / 5) * 0.1;
        }

        return round(min($score, 1.0), 2);
    }

    /**
     * Get product categories
     */
    private function getCategories($products, string $locale): array
    {
        $categories = [];

        if ($products->where('gps_features', true)->count() > 0) {
            $categories[] = $locale === 'ka' ? 'GPS საათები' : 'GPS watches';
        }
        if ($products->where('sim_support', true)->count() > 0) {
            $categories[] = $locale === 'ka' ? 'SIM საათები' : 'SIM watches';
        }
        if ($products->contains(fn (Product $product): bool => $this->hasVideoCallFeature($product))) {
            $categories[] = $locale === 'ka' ? 'ვიდეო ზარის საათები' : 'Video call watches';
        }
        if ($products->whereNotNull('water_resistant')->count() > 0) {
            $categories[] = $locale === 'ka' ? 'წყალგამძლე საათები' : 'Water-resistant watches';
        }

        return $categories;
    }

    /**
     * @return array<string, mixed>
     */
    private function featureFlags(Product $product, string $locale): array
    {
        return [
            'sim_support' => (bool) $product->sim_support,
            'gps' => (bool) $product->gps_features,
            'video_call' => $this->hasVideoCallFeature($product),
            'waterproof' => $product->water_resistant,
            'battery_life' => $product->batteryLifeLabel($locale),
            'screen_size' => $product->screen_size ?? null,
            'camera' => (bool) $product->camera,
        ];
    }

    private function hasVideoCallFeature(Product $product): bool
    {
        return Str::contains($this->productFeatureText($product), [
            'video call',
            'video calls',
            'video calling',
            'two-way calling',
            'ვიდეო ზარ',
            'ვიდეოზარ',
            'ორმხრივ ზარ',
        ]);
    }

    private function productFeatureText(Product $product): string
    {
        $parts = array_filter([
            (string) $product->name_en,
            (string) $product->name_ka,
            (string) $product->short_description_en,
            (string) $product->short_description_ka,
            (string) $product->description_en,
            (string) $product->description_ka,
            is_array($product->functions) ? implode(' ', array_map(static fn ($item): string => (string) $item, $product->functions)) : '',
        ], static fn (string $part): bool => trim($part) !== '');

        return mb_strtolower(implode(' ', $parts));
    }

    private function productRating(Product $product): ?float
    {
        $rating = $product->getAttribute('reviews_avg_rating');

        if (is_numeric($rating)) {
            return round((float) $rating, 1);
        }

        if ($product->relationLoaded('reviews')) {
            $avg = $product->reviews->avg('rating');

            return $avg !== null ? round((float) $avg, 1) : null;
        }

        return null;
    }

    private function productReviewsCount(Product $product): int
    {
        $count = $product->getAttribute('reviews_count');

        if (is_numeric($count)) {
            return (int) $count;
        }

        if ($product->relationLoaded('reviews')) {
            return $product->reviews->count();
        }

        return 0;
    }

    private function resolveLocale(Request $request): string
    {
        $fallback = in_array(app()->getLocale(), ['ka', 'en'], true)
            ? app()->getLocale()
            : 'ka';
        $locale = strtolower((string) $request->query('lang', $fallback));

        return in_array($locale, ['ka', 'en'], true) ? $locale : $fallback;
    }
}
