<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

        $productsQuery = Product::active()
            ->with(['images', 'variants'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

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
                        $productsQuery->where('gps_features', true);
                        break;
                    case 'sim':
                    case 'sim_support':
                        $productsQuery->where('sim_support', true);
                        break;
                    case 'video':
                    case 'video_call':
                        $productsQuery->where(function ($query): void {
                            $query
                                ->where('description_ka', 'like', '%ვიდეო%')
                                ->orWhere('short_description_ka', 'like', '%ვიდეო%')
                                ->orWhere('description_en', 'like', '%video%')
                                ->orWhere('short_description_en', 'like', '%video%');
                        });
                        break;
                    case 'camera':
                        $productsQuery->where('camera', true);
                        break;
                    case 'waterproof':
                        $productsQuery->whereNotNull('water_resistant');
                        break;
                    case 'waterproof':
                        if ($product->water_resistant) {
                            $reasons[] = $locale === 'ka' ? 'áƒ¬áƒ§áƒáƒšáƒ’áƒáƒ›áƒ«áƒšáƒ”áƒáƒ‘áƒ' : 'Water resistance';
                        }
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
                    'features' => $this->featureFlags($product, $locale),
                    'age_range' => ($product->age_min && $product->age_max)
                        ? "{$product->age_min}-{$product->age_max} years"
                        : null,
                    'rating' => $this->productRating($product),
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
                        if ($product->gps_features) {
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
                        if ($this->hasVideoCallFeature($product)) {
                            $reasons[] = $locale === 'ka' ? 'ვიდეო ზარები' : 'Video calls';
                        }
                        break;
                }
            }
        }

        if ($product->featured) {
            $reasons[] = $locale === 'ka' ? 'რეკომენდებული' : 'Featured';
        }

        if (($rating = $this->productRating($product)) !== null && $rating >= 4.5) {
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
            'camera' => (bool) $product->camera,
            'battery_life' => $product->batteryLifeLabel($locale),
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
}
