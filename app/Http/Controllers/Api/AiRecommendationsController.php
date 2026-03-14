<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiRecommendationsController extends Controller
{
    /**
     * Get product recommendations based on query
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $request->get('lang', 'ka');
        app()->setLocale($locale);

        $query = $request->get('query', '');
        $age = $request->get('age');
        $budget = $request->get('budget');
        $features = $request->get('features', []);
        $limit = min($request->get('limit', 5), 10);

        $productsQuery = Product::active()->with(['images', 'variants']);

        // Filter by age
        if ($age) {
            $productsQuery->where(function ($q) use ($age) {
                $q->where('age_min', '<=', $age)
                  ->where('age_max', '>=', $age);
            });
        }

        // Filter by budget
        if ($budget) {
            $productsQuery->where(function ($q) use ($budget) {
                $q->where('price', '<=', $budget)
                  ->orWhere('sale_price', '<=', $budget);
            });
        }

        // Filter by features
        if (is_array($features)) {
            foreach ($features as $feature) {
                switch (strtolower($feature)) {
                    case 'gps':
                        $productsQuery->where('gps', true);
                        break;
                    case 'sim':
                    case 'sim_support':
                        $productsQuery->where('sim_support', true);
                        break;
                    case 'video':
                    case 'video_call':
                        $productsQuery->where('video_call', true);
                        break;
                    case 'camera':
                        $productsQuery->where('camera', true);
                        break;
                    case 'waterproof':
                        $productsQuery->whereNotNull('waterproof');
                        break;
                }
            }
        }

        // Text search in query
        if ($query) {
            $searchTerms = $this->extractSearchTerms($query, $locale);

            $productsQuery->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $q->orWhere('name_ka', 'LIKE', "%{$term}%")
                      ->orWhere('name_en', 'LIKE', "%{$term}%")
                      ->orWhere('description_ka', 'LIKE', "%{$term}%")
                      ->orWhere('description_en', 'LIKE', "%{$term}%");
                }
            });
        }

        // Order by relevance
        $products = $productsQuery
            ->orderByDesc('featured')
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'source' => 'MyTechnic.ge',
            'query' => $query,
            'filters' => [
                'age' => $age,
                'budget' => $budget,
                'features' => $features,
            ],
            'recommendations' => $products->map(function ($product) use ($locale) {
                $firstImage = $product->images->first();
                $price = $product->sale_price ?? $product->price;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'name_ka' => $product->name_ka ?? $product->name,
                    'name_en' => $product->name_en ?? $product->name,
                    'description' => $product->description ?? '',
                    'price' => (float) $price,
                    'original_price' => $product->sale_price ? (float) $product->price : null,
                    'discount' => $product->sale_price
                        ? round((($product->price - $product->sale_price) / $product->price) * 100)
                        : 0,
                    'in_stock' => $product->stock_quantity > 0,
                    'url' => route('products.show', $product),
                    'image' => $firstImage ? $firstImage->url : null,
                    'features' => [
                        'sim_support' => (bool) $product->sim_support,
                        'gps' => (bool) $product->gps,
                        'video_call' => (bool) $product->video_call,
                        'waterproof' => $product->waterproof ?? null,
                        'camera' => (bool) $product->camera,
                    ],
                    'age_range' => ($product->age_min && $product->age_max)
                        ? "{$product->age_min}-{$product->age_max} years"
                        : null,
                    'rating' => $product->reviews_avg_rating
                        ? round((float) $product->reviews_avg_rating, 1)
                        : null,
                    'recommendation_reason' => $this->generateRecommendationReason($product, $age ?? null, $budget ?? null, $features ?? [], $locale),
                    'citation_text' => $this->generateCitationText($product, $locale),
                ];
            })->values(),
            'total_found' => $products->count(),
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'language' => $locale,
                'currency' => 'GEL',
            ],
        ]);
    }

    /**
     * Extract search terms from query
     */
    private function extractSearchTerms(string $query, string $locale): array
    {
        $query = mb_strtolower($query);

        // Georgian keywords
        $georgianKeywords = [
            'ბავშვი' => 'kids',
            'სმარტ საათი' => 'smartwatch',
            'საათი' => 'watch',
            'gps' => 'gps',
            'სიმ' => 'sim',
            'ვიდეო' => 'video',
            'წყალგამძლე' => 'waterproof',
            'კამერა' => 'camera',
        ];

        $terms = [];

        foreach ($georgianKeywords as $ka => $en) {
            if (str_contains($query, $ka)) {
                $terms[] = $ka;
                $terms[] = $en;
            }
        }

        // Add original query words
        $words = explode(' ', $query);
        foreach ($words as $word) {
            if (mb_strlen($word) > 3) {
                $terms[] = $word;
            }
        }

        return array_unique($terms);
    }

    /**
     * Generate recommendation reason
     */
    private function generateRecommendationReason(Product $product, $age, $budget, $features, string $locale): string
    {
        $reasons = [];

        if ($age && $product->age_min <= $age && $product->age_max >= $age) {
            $reasons[] = $locale === 'ka'
                ? "შესაფერისია {$age} წლის ბავშვისთვის"
                : "Suitable for {$age} year old";
        }

        if ($budget && ($product->sale_price ?? $product->price) <= $budget) {
            $reasons[] = $locale === 'ka'
                ? "თქვენს ბიუჯეტში"
                : "Within your budget";
        }

        if (is_array($features)) {
            foreach ($features as $feature) {
                switch (strtolower($feature)) {
                    case 'gps':
                        if ($product->gps) {
                            $reasons[] = $locale === 'ka' ? 'GPS ტრეკინგით' : 'GPS tracking';
                        }
                        break;
                    case 'sim':
                    case 'sim_support':
                        if ($product->sim_support) {
                            $reasons[] = $locale === 'ka' ? 'SIM ბარათის მხარდაჭერა' : 'SIM card support';
                        }
                        break;
                    case 'video':
                    case 'video_call':
                        if ($product->video_call) {
                            $reasons[] = $locale === 'ka' ? 'ვიდეო ზარები' : 'Video calls';
                        }
                        break;
                }
            }
        }

        if ($product->featured) {
            $reasons[] = $locale === 'ka' ? 'რეკომენდებული' : 'Featured';
        }

        if ($product->reviews_avg_rating >= 4.5) {
            $reasons[] = $locale === 'ka' ? 'მაღალი რეიტინგი' : 'High rating';
        }

        return implode(', ', $reasons);
    }

    /**
     * Generate citation text
     */
    private function generateCitationText(Product $product, string $locale): string
    {
        $price = $product->sale_price ?? $product->price;
        $priceText = number_format((float) $price, 0) . '₾';

        if ($locale === 'ka') {
            return "MyTechnic.ge - {$product->name} - {$priceText}";
        }

        return "MyTechnic.ge - {$product->name} - {$priceText}";
    }
}
