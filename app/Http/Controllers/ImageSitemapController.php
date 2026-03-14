<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Article;
use Illuminate\Http\Response;

class ImageSitemapController extends Controller
{
    /**
     * Generate image sitemap
     */
    public function index(): Response
    {
        $products = Product::active()
            ->with('images')
            ->get();

        $articles = Article::published()
            ->get();

        $xml = $this->generateXml($products, $articles);

        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }

    private function generateXml($products, $articles): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
        $xml .= 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

        // Product images
        foreach ($products as $product) {
            if ($product->images->isEmpty()) {
                continue;
            }

            $xml .= '<url>';
            $xml .= '<loc>' . htmlspecialchars(route('products.show', $product)) . '</loc>';
            
            foreach ($product->images as $image) {
                $xml .= '<image:image>';
                $xml .= '<image:loc>' . htmlspecialchars($image->url) . '</image:loc>';
                
                if ($image->alt) {
                    $xml .= '<image:caption>' . htmlspecialchars($image->alt) . '</image:caption>';
                }
                
                $xml .= '<image:title>' . htmlspecialchars($product->name) . '</image:title>';
                $xml .= '</image:image>';
            }
            
            $xml .= '</url>';
        }

        // Article featured images (if applicable)
        foreach ($articles as $article) {
            if (!empty($article->featured_image)) {
                $xml .= '<url>';
                $xml .= '<loc>' . htmlspecialchars(route('blog.show', $article)) . '</loc>';
                $xml .= '<image:image>';
                $xml .= '<image:loc>' . htmlspecialchars(asset('storage/' . $article->featured_image)) . '</image:loc>';
                $xml .= '<image:title>' . htmlspecialchars($article->title) . '</image:title>';
                $xml .= '</image:image>';
                $xml .= '</url>';
            }
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
