<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Article;

class InternalLinkingService
{
    /**
     * Get suggested internal links for a product
     */
    public function getSuggestedLinks(Product $product): array
    {
        $links = [];
        
        // Related products by brand or features
        $links['related'] = Product::active()
            ->where('id', '!=', $product->id)
            ->where(function($q) use ($product) {
                $q->where('brand', $product->brand)
                  ->orWhere('sim_support', $product->sim_support);
            })
            ->limit(3)
            ->get();
        
        // Related articles mentioning this product or category
        $links['articles'] = Article::published()
            ->where(function($q) use ($product) {
                $q->where('body_ka', 'LIKE', "%{$product->name}%")
                  ->orWhere('body_ka', 'LIKE', '%სმარტ საათი%')
                  ->orWhere('body_en', 'LIKE', "%{$product->name}%")
                  ->orWhere('body_en', 'LIKE', '%smart watch%');
            })
            ->limit(2)
            ->get();
        
        // Relevant guides
        $links['guides'] = [];
        
        if ($product->sim_support) {
            $links['guides'][] = [
                'url' => route('landing.sim-guide'),
                'title' => app()->getLocale() === 'ka' ? 'SIM ბარათის სახელმძღვანელო' : 'SIM Card Guide',
            ];
        }
        
        // Gift guide
        $links['guides'][] = [
            'url' => route('landing.gift-guide'),
            'title' => app()->getLocale() === 'ka' ? 'საჩუქრის სახელმძღვანელო' : 'Gift Guide',
        ];
        
        return $links;
    }

    /**
     * Get breadcrumb trail for a product
     */
    public function getProductBreadcrumbs(Product $product): array
    {
        return [
            [
                'name' => app()->getLocale() === 'ka' ? 'მთავარი' : 'Home',
                'url' => route('home'),
            ],
            [
                'name' => app()->getLocale() === 'ka' ? 'პროდუქტები' : 'Products',
                'url' => route('products.index'),
            ],
            [
                'name' => $product->name,
                'url' => route('products.show', $product),
            ],
        ];
    }

    /**
     * Get contextual links for article
     */
    public function getArticleLinks(Article $article): array
    {
        $links = [];
        
        // Related articles
        $links['related'] = Article::published()
            ->where('id', '!=', $article->id)
            ->where(function($q) use ($article) {
                // Find articles with similar keywords
                $keywords = explode(' ', substr($article->title, 0, 50));
                foreach ($keywords as $keyword) {
                    if (mb_strlen($keyword) > 4) {
                        $q->orWhere('title_ka', 'LIKE', "%{$keyword}%")
                          ->orWhere('title_en', 'LIKE', "%{$keyword}%");
                    }
                }
            })
            ->limit(3)
            ->get();
        
        // Related products mentioned in article
        $links['products'] = Product::active()
            ->where(function($q) use ($article) {
                $q->whereRaw('LOWER(name_ka) IN (SELECT LOWER(word) FROM (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(?, " ", numbers.n), " ", -1) word FROM (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) numbers) words)', [$article->body_ka ?? '']);
            })
            ->limit(3)
            ->get();
        
        return $links;
    }

    /**
     * Generate anchor text variations
     */
    public function generateAnchorText(Product $product, string $type = 'default'): string
    {
        $locale = app()->getLocale();
        
        $variations = [
            'default' => $product->name,
            'brand' => ($product->brand ?? '') . ' ' . ($locale === 'ka' ? 'სმარტ საათი' : 'smart watch'),
            'feature' => $locale === 'ka' ? 'GPS სმარტ საათი ბავშვებისთვის' : 'GPS smart watch for kids',
            'price' => $locale === 'ka' ? 'სმარტ საათი ' . number_format($product->price) . '₾-დან' : 'smart watch from ₾' . number_format($product->price),
        ];
        
        return $variations[$type] ?? $variations['default'];
    }
}
