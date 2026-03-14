<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Article;

class SeoService
{
    /**
     * Generate optimized meta tags for product
     */
    public function generateProductMeta(Product $product): array
    {
        $locale = app()->getLocale();
        $isKa = $locale === 'ka';
        
        return [
            'title' => $this->buildProductTitle($product, $isKa),
            'description' => $this->buildProductDescription($product, $isKa),
            'keywords' => $this->generateProductKeywords($product, $isKa),
        ];
    }

    /**
     * Build SEO-optimized product title
     */
    private function buildProductTitle(Product $product, bool $isKa): string
    {
        // Use custom meta title if set
        if ($product->meta_title) {
            return $product->meta_title;
        }

        if ($isKa) {
            $parts = [$product->name];
            
            if ($product->sim_support) {
                $parts[] = 'SIM-იანი';
            }
            
            if ($product->gps_features) {
                $parts[] = 'GPS ტრეკინგით';
            }
            
            $parts[] = '— MyTechnic საქართველო';
            
            return implode(' ', $parts);
        }
        
        return "{$product->name} — MyTechnic Georgia";
    }

    /**
     * Build SEO-optimized product description
     */
    private function buildProductDescription(Product $product, bool $isKa): string
    {
        // Use custom meta description if set
        if ($product->meta_description) {
            return $product->meta_description;
        }

        if ($isKa) {
            $desc = $product->name . '. ';
            
            if ($product->sim_support) {
                $desc .= 'SIM ბარათით, ';
            }
            
            if ($product->gps_features) {
                $desc .= 'GPS მდებარეობის კონტროლით, ';
            }
            
            if ($product->water_resistant) {
                $desc .= 'წყალგამძლე, ';
            }
            
            if ($product->price) {
                $salePrice = $product->sale_price ?? $product->price;
                $desc .= 'ფასი ' . number_format($salePrice, 0) . '₾. ';
            }
            
            $desc .= 'უფასო მიტანა თბილისში და საქართველოს მასშტაბით. ოფიციალური იმპორტიორი.';
            
            return $desc;
        }
        
        return $product->short_description ?? "{$product->name}. Official importer in Georgia. Free delivery.";
    }

    /**
     * Generate product keywords
     */
    private function generateProductKeywords(Product $product, bool $isKa): array
    {
        $keywords = [];
        
        if ($isKa) {
            $keywords[] = 'ბავშვის სმარტ საათი';
            $keywords[] = 'სმარტ საათი საქართველოში';
            $keywords[] = 'სმარტ საათი თბილისში';
            
            if ($product->sim_support) {
                $keywords[] = 'SIM-იანი საათი';
                $keywords[] = 'GPS საათი ბავშვისთვის';
                $keywords[] = 'ბავშვის საათი SIM ბარათით';
            }
            
            if ($product->gps_features) {
                $keywords[] = 'GPS ტრეკინგი';
                $keywords[] = 'მდებარეობის კონტროლი';
            }
            
            $keywords[] = $product->name;
            
            if ($product->brand) {
                $keywords[] = $product->brand . ' საქართველოში';
            }
        } else {
            $keywords[] = 'kids smartwatch';
            $keywords[] = 'smartwatch Georgia';
            $keywords[] = $product->name;
        }
        
        return $keywords;
    }

    /**
     * Generate article meta tags
     */
    public function generateArticleMeta(Article $article): array
    {
        $locale = app()->getLocale();
        
        return [
            'title' => $article->meta_title ?? ($article->title . ' — MyTechnic'),
            'description' => $article->meta_description ?? $article->excerpt ?? '',
            'keywords' => $this->generateArticleKeywords($article, $locale === 'ka'),
        ];
    }

    /**
     * Generate article keywords
     */
    private function generateArticleKeywords(Article $article, bool $isKa): array
    {
        $keywords = [];
        
        if ($isKa) {
            $keywords[] = 'ბავშვის სმარტ საათი';
            $keywords[] = 'სმარტ საათის გზამკვლევი';
            $keywords[] = 'MyTechnic ბლოგი';
        } else {
            $keywords[] = 'kids smartwatch guide';
            $keywords[] = 'MyTechnic blog';
        }
        
        return $keywords;
    }

    /**
     * Generate breadcrumb items for product
     */
    public function generateProductBreadcrumbs(Product $product): array
    {
        $locale = app()->getLocale();
        $isKa = $locale === 'ka';
        
        return [
            [
                'name' => $isKa ? 'მთავარი' : 'Home',
                'url' => url('/'),
            ],
            [
                'name' => $isKa ? 'პროდუქტები' : 'Products',
                'url' => route('products.index'),
            ],
            [
                'name' => $product->name,
                'url' => route('products.show', $product),
            ],
        ];
    }

    /**
     * Generate breadcrumb items for article
     */
    public function generateArticleBreadcrumbs(Article $article): array
    {
        $locale = app()->getLocale();
        $isKa = $locale === 'ka';
        
        return [
            [
                'name' => $isKa ? 'მთავარი' : 'Home',
                'url' => url('/'),
            ],
            [
                'name' => $isKa ? 'ბლოგი' : 'Blog',
                'url' => route('blog.index'),
            ],
            [
                'name' => $article->title,
                'url' => route('blog.show', $article),
            ],
        ];
    }

    /**
     * Validate meta description length
     */
    public function validateMetaDescription(string $description): array
    {
        $length = mb_strlen($description);
        $optimal = $length >= 120 && $length <= 160;
        
        return [
            'valid' => $length <= 160,
            'optimal' => $optimal,
            'length' => $length,
            'message' => $optimal 
                ? 'Meta description length is optimal' 
                : ($length > 160 
                    ? 'Meta description is too long (max 160 characters)' 
                    : 'Meta description is too short (recommended 120-160 characters)'),
        ];
    }

    /**
     * Generate canonical URL
     */
    public function generateCanonicalUrl(string $path): string
    {
        return url($path);
    }
}
