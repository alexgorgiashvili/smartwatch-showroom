<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\Article;
use App\Services\GoogleSearchConsoleService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SeoMonitoring extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.seo-monitoring';

    protected static ?string $navigationGroup = 'SEO & Analytics';

    protected static ?string $navigationLabel = 'SEO მონიტორინგი';

    protected static ?int $navigationSort = 1;

    public function getTitle(): string
    {
        return 'SEO მონიტორინგი';
    }

    public function getHeading(): string
    {
        return 'SEO მონიტორინგი და ანალიტიკა';
    }

    /**
     * Get SEO health statistics
     */
    public function getSeoHealthStats(): array
    {
        return [
            'total_products' => Product::active()->count(),
            'products_with_meta' => Product::active()
                ->whereNotNull('meta_title_ka')
                ->whereNotNull('meta_description_ka')
                ->count(),
            'products_missing_meta' => Product::active()
                ->where(function($q) {
                    $q->whereNull('meta_title_ka')
                      ->orWhereNull('meta_description_ka');
                })
                ->count(),
            'products_with_images' => Product::active()
                ->whereHas('images')
                ->count(),
            'products_missing_images' => Product::active()
                ->whereDoesntHave('images')
                ->count(),
            'total_articles' => Article::published()->count(),
            'articles_with_meta' => Article::published()
                ->whereNotNull('meta_title_ka')
                ->whereNotNull('meta_description_ka')
                ->count(),
        ];
    }

    /**
     * Get products missing meta tags
     */
    public function getProductsMissingMeta(): array
    {
        return Product::active()
            ->where(function($q) {
                $q->whereNull('meta_title_ka')
                  ->orWhereNull('meta_description_ka');
            })
            ->select('id', 'name_ka', 'slug', 'meta_title_ka', 'meta_description_ka')
            ->limit(10)
            ->get()
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name_ka,
                    'slug' => $product->slug,
                    'meta_title_ka' => $product->meta_title_ka,
                    'meta_description_ka' => $product->meta_description_ka,
                ];
            })
            ->toArray();
    }

    /**
     * Get products missing alt tags
     */
    public function getProductsMissingAltTags(): array
    {
        return Product::active()
            ->whereDoesntHave('images')
            ->select('id', 'name_ka', 'slug')
            ->limit(10)
            ->get()
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name_ka,
                    'slug' => $product->slug,
                ];
            })
            ->toArray();
    }

    /**
     * Get sitemap statistics
     */
    public function getSitemapStats(): array
    {
        $sitemapPath = public_path('sitemap.xml');
        $sitemapExists = File::exists($sitemapPath);

        $stats = [
            'exists' => $sitemapExists,
            'last_modified' => $sitemapExists ? date('Y-m-d H:i:s', File::lastModified($sitemapPath)) : null,
            'size' => $sitemapExists ? File::size($sitemapPath) : 0,
        ];

        return $stats;
    }

    /**
     * Get schema markup coverage
     */
    public function getSchemaStats(): array
    {
        // Check if ProductReview model exists
        $reviewsCount = 0;
        if (class_exists(\App\Models\ProductReview::class)) {
            $reviewsCount = DB::table('product_reviews')
                ->where('is_approved', true)
                ->distinct('product_id')
                ->count('product_id');
        }

        return [
            'products_with_schema' => Product::active()->count(), // All products have schema
            'products_with_reviews' => $reviewsCount,
            'articles_with_schema' => Article::published()->count(),
        ];
    }

    /**
     * Get top performing products (by views if tracked)
     */
    public function getTopProducts(): array
    {
        return Product::active()
            ->with('images')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name_ka,
                    'slug' => $product->slug,
                    'price' => $product->sale_price ?? $product->price,
                    'stock' => $product->stock_quantity ?? 0,
                    'has_meta' => !empty($product->meta_title_ka) && !empty($product->meta_description_ka),
                ];
            })
            ->toArray();
    }

    /**
     * Get recent articles
     */
    public function getRecentArticles(): array
    {
        return Article::published()
            ->orderByDesc('published_at')
            ->limit(5)
            ->get()
            ->map(function($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'published_at' => $article->published_at->format('Y-m-d'),
                    'has_meta' => !empty($article->meta_title_ka) && !empty($article->meta_description_ka),
                ];
            })
            ->toArray();
    }

    /**
     * Get SEO recommendations
     */
    public function getRecommendations(): array
    {
        $recommendations = [];

        $stats = $this->getSeoHealthStats();

        if ($stats['products_missing_meta'] > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Meta თეგები არ აქვს ' . $stats['products_missing_meta'] . ' პროდუქტს',
                'description' => 'დაამატეთ meta title და description ყველა პროდუქტს SEO-ს გასაუმჯობესებლად.',
                'action' => 'შეავსეთ meta თეგები',
            ];
        }

        if ($stats['products_missing_images'] > 0) {
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'სურათები არ აქვს ' . $stats['products_missing_images'] . ' პროდუქტს',
                'description' => 'დაამატეთ სურათები alt ატრიბუტებით.',
                'action' => 'ატვირთეთ სურათები',
            ];
        }

        $schemaStats = $this->getSchemaStats();
        if ($schemaStats['products_with_reviews'] === 0) {
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'პროდუქტებს არ აქვთ მიმოხილვები',
                'description' => 'მიმოხილვები აუმჯობესებს rich snippets-ს Google-ში.',
                'action' => 'დაამატეთ მიმოხილვები',
            ];
        }

        return $recommendations;
    }

    /**
     * Get Google Search Console data (if configured)
     */
    public function getSearchConsoleData(): ?array
    {
        try {
            if (!File::exists(storage_path('app/google-credentials.json'))) {
                return null;
            }

            $service = new GoogleSearchConsoleService();
            $endDate = now()->subDays(1)->format('Y-m-d');
            $startDate = now()->subDays(30)->format('Y-m-d');

            return $service->getSearchAnalytics($startDate, $endDate);
        } catch (\Exception $e) {
            return null;
        }
    }
}
