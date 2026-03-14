<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Article;
use Illuminate\Http\Response;

class AiSitemapController extends Controller
{
    /**
     * Generate AI-optimized sitemap
     */
    public function index(): Response
    {
        $products = Product::active()
            ->with('images')
            ->orderByDesc('updated_at')
            ->get();

        $articles = Article::published()
            ->orderByDesc('updated_at')
            ->get();

        $xml = $this->generateXml($products, $articles);

        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Generate AI-optimized sitemap XML
     */
    private function generateXml($products, $articles): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:ai="https://mytechnic.ge/schemas/ai">' . "\n";

        // Add API endpoints
        $xml .= $this->addUrl(
            url('/api/ai/products'),
            now()->toIso8601String(),
            'daily',
            '1.0',
            [
                'type' => 'api',
                'format' => 'json',
                'purpose' => 'product_catalog',
                'language' => 'ka,en',
            ]
        );

        $xml .= $this->addUrl(
            url('/api/ai/recommendations'),
            now()->toIso8601String(),
            'hourly',
            '1.0',
            [
                'type' => 'api',
                'format' => 'json',
                'purpose' => 'recommendations',
                'language' => 'ka,en',
            ]
        );

        $xml .= $this->addUrl(
            url('/api/ai/knowledge'),
            now()->toIso8601String(),
            'weekly',
            '0.9',
            [
                'type' => 'api',
                'format' => 'json',
                'purpose' => 'knowledge_base',
                'language' => 'ka,en',
            ]
        );

        // Add products
        foreach ($products as $product) {
            $xml .= $this->addUrl(
                route('products.show', $product),
                $product->updated_at->toIso8601String(),
                'daily',
                '0.9',
                [
                    'type' => 'product',
                    'name' => $product->name,
                    'price' => $product->sale_price ?? $product->price,
                    'currency' => 'GEL',
                    'in_stock' => $product->stock_quantity > 0,
                ]
            );

            // Add markdown version
            $xml .= $this->addUrl(
                url("/api/ai/products/{$product->id}/markdown"),
                $product->updated_at->toIso8601String(),
                'daily',
                '0.8',
                [
                    'type' => 'content',
                    'format' => 'markdown',
                    'purpose' => 'ai_readable',
                ]
            );
        }

        // Add articles
        foreach ($articles as $article) {
            $xml .= $this->addUrl(
                route('blog.show', $article),
                $article->updated_at->toIso8601String(),
                'weekly',
                '0.7',
                [
                    'type' => 'article',
                    'title' => $article->title,
                ]
            );
        }

        // Add static pages
        $xml .= $this->addUrl(url('/'), now()->toIso8601String(), 'daily', '1.0', ['type' => 'homepage']);
        $xml .= $this->addUrl(url('/products'), now()->toIso8601String(), 'daily', '0.9', ['type' => 'catalog']);
        $xml .= $this->addUrl(url('/faq'), now()->toIso8601String(), 'weekly', '0.8', ['type' => 'faq']);
        $xml .= $this->addUrl(url('/contact'), now()->toIso8601String(), 'monthly', '0.7', ['type' => 'contact']);

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Add URL to sitemap with AI metadata
     */
    private function addUrl(string $loc, string $lastmod, string $changefreq, string $priority, array $aiMetadata = []): string
    {
        $xml = "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
        $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
        $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
        $xml .= "    <priority>{$priority}</priority>\n";

        // Add AI-specific metadata
        if (!empty($aiMetadata)) {
            $xml .= "    <ai:metadata>\n";
            foreach ($aiMetadata as $key => $value) {
                $value = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                $xml .= "      <ai:{$key}>" . htmlspecialchars($value) . "</ai:{$key}>\n";
            }
            $xml .= "    </ai:metadata>\n";
        }

        $xml .= "  </url>\n";

        return $xml;
    }
}
