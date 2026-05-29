<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeoMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $stats = $this->getSeoHealthStats();
        $productsMissingMeta = $this->getProductsMissingMeta();
        $productsMissingImages = $this->getProductsMissingImages();
        $recommendations = $this->getRecommendations($stats);

        $view = view('admin.seo-monitoring.index', [
            'stats' => $stats,
            'productsMissingMeta' => $productsMissingMeta,
            'productsMissingImages' => $productsMissingImages,
            'recommendations' => $recommendations,
        ]);

        return $this->renderPjaxView($request, $view);
    }

    private function getSeoHealthStats(): array
    {
        $totalProducts = Product::active()->count();
        $productsWithMeta = Product::active()
            ->whereNotNull('meta_title_ka')
            ->whereNotNull('meta_description_ka')
            ->count();

        $productsWithImages = Product::active()
            ->whereHas('images')
            ->count();

        $totalArticles = Article::published()->count();
        $articlesWithMeta = Article::published()
            ->whereNotNull('meta_title_ka')
            ->whereNotNull('meta_description_ka')
            ->count();

        return [
            'total_products' => $totalProducts,
            'products_with_meta' => $productsWithMeta,
            'products_missing_meta' => $totalProducts - $productsWithMeta,
            'products_with_images' => $productsWithImages,
            'products_missing_images' => $totalProducts - $productsWithImages,
            'total_articles' => $totalArticles,
            'articles_with_meta' => $articlesWithMeta,
            'meta_percentage' => $totalProducts > 0 ? round(($productsWithMeta / $totalProducts) * 100) : 0,
            'images_percentage' => $totalProducts > 0 ? round(($productsWithImages / $totalProducts) * 100) : 0,
        ];
    }

    private function getProductsMissingMeta(): array
    {
        return Product::active()
            ->where(function ($q) {
                $q->whereNull('meta_title_ka')
                    ->orWhereNull('meta_description_ka');
            })
            ->select('id', 'name_ka', 'slug', 'meta_title_ka', 'meta_description_ka')
            ->limit(10)
            ->get()
            ->map(fn($product) => [
                'id' => $product->id,
                'name' => $product->name_ka,
                'slug' => $product->slug,
                'missing_title' => empty($product->meta_title_ka),
                'missing_description' => empty($product->meta_description_ka),
            ])
            ->toArray();
    }

    private function getProductsMissingImages(): array
    {
        return Product::active()
            ->whereDoesntHave('images')
            ->select('id', 'name_ka', 'slug')
            ->limit(10)
            ->get()
            ->map(fn($product) => [
                'id' => $product->id,
                'name' => $product->name_ka,
                'slug' => $product->slug,
            ])
            ->toArray();
    }

    private function getRecommendations(array $stats): array
    {
        $recommendations = [];

        if ($stats['meta_percentage'] < 80) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Meta Tags დაკარგულია',
                'description' => $stats['products_missing_meta'] . ' პროდუქტს არ აქვს სრული meta tags',
                'action' => 'დაამატეთ Meta Tags',
            ];
        }

        if ($stats['images_percentage'] < 90) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'სურათები დაკარგულია',
                'description' => $stats['products_missing_images'] . ' პროდუქტს არ აქვს სურათები',
                'action' => 'ატვირთეთ სურათები',
            ];
        }

        if ($stats['total_articles'] > 0 && $stats['articles_with_meta'] < $stats['total_articles']) {
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'სტატიების SEO',
                'description' => ($stats['total_articles'] - $stats['articles_with_meta']) . ' სტატიას არ აქვს meta tags',
                'action' => 'დაამატეთ Meta Tags',
            ];
        }

        return $recommendations;
    }
}
