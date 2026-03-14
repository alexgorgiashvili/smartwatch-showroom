<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiProductsController extends Controller
{
    /**
     * Get all products in AI-optimized format
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $request->get('lang', 'ka');
        app()->setLocale($locale);

        $products = Product::active()
            ->with(['images', 'variants'])
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
                    'name_ka' => $product->name_ka ?? $product->name,
                    'name_en' => $product->name_en ?? $product->name,
                    'slug' => $product->slug,
                    'description' => $product->description ?? '',
                    'description_ka' => $product->description_ka ?? $product->description,
                    'description_en' => $product->description_en ?? $product->description,
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
                    'features' => [
                        'sim_support' => (bool) $product->sim_support,
                        'gps' => (bool) $product->gps,
                        'video_call' => (bool) $product->video_call,
                        'waterproof' => $product->waterproof ?? null,
                        'battery_life' => $product->battery_life ?? null,
                        'screen_size' => $product->screen_size ?? null,
                        'camera' => (bool) $product->camera,
                    ],
                    'suitable_for' => [
                        'age_min' => $product->age_min ?? 4,
                        'age_max' => $product->age_max ?? 12,
                    ],
                    'rating' => $product->reviews_avg_rating 
                        ? round((float) $product->reviews_avg_rating, 1)
                        : null,
                    'reviews_count' => $product->reviews_count ?? 0,
                    'brand' => $product->brand ?? 'MyTechnic',
                    'featured' => (bool) $product->featured,
                    'citation_text' => $this->generateCitationText($product, $locale),
                    'ai_recommendation_score' => $this->calculateRecommendationScore($product),
                ];
            })->values(),
            'total' => $products->count(),
            'categories' => $this->getCategories($products),
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
        $locale = $request->get('lang', 'ka');
        app()->setLocale($locale);

        $product->load(['images', 'variants', 'reviews']);

        $firstImage = $product->images->first();

        return response()->json([
            'source' => 'MyTechnic.ge',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'name_ka' => $product->name_ka ?? $product->name,
                'name_en' => $product->name_en ?? $product->name,
                'slug' => $product->slug,
                'description' => $product->description ?? '',
                'description_ka' => $product->description_ka ?? $product->description,
                'description_en' => $product->description_en ?? $product->description,
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
                'features' => [
                    'sim_support' => (bool) $product->sim_support,
                    'gps' => (bool) $product->gps,
                    'video_call' => (bool) $product->video_call,
                    'waterproof' => $product->waterproof ?? null,
                    'battery_life' => $product->battery_life ?? null,
                    'screen_size' => $product->screen_size ?? null,
                    'camera' => (bool) $product->camera,
                ],
                'suitable_for' => [
                    'age_min' => $product->age_min ?? 4,
                    'age_max' => $product->age_max ?? 12,
                ],
                'rating' => $product->reviews_avg_rating 
                    ? round((float) $product->reviews_avg_rating, 1)
                    : null,
                'reviews_count' => $product->reviews_count ?? 0,
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
        if ($product->reviews_avg_rating) {
            $score += ($product->reviews_avg_rating / 5) * 0.1;
        }

        return round(min($score, 1.0), 2);
    }

    /**
     * Get product categories
     */
    private function getCategories($products): array
    {
        $categories = [];

        if ($products->where('gps', true)->count() > 0) {
            $categories[] = 'GPS watches';
        }
        if ($products->where('sim_support', true)->count() > 0) {
            $categories[] = 'SIM watches';
        }
        if ($products->where('video_call', true)->count() > 0) {
            $categories[] = 'Video call watches';
        }
        if ($products->where('waterproof', '!=', null)->count() > 0) {
            $categories[] = 'Waterproof watches';
        }

        return $categories;
    }
}
