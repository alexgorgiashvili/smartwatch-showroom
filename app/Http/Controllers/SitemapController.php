<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Article;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    public function index()
    {
        $sitemap = Sitemap::create();

        // — Static pages —
        $sitemap->add(
            Url::create('/')
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                ->setPriority(1.0)
        );

        $sitemap->add(
            Url::create('/products')
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(0.9)
        );

        $sitemap->add(
            Url::create('/faq')
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setPriority(0.7)
        );

        $sitemap->add(
            Url::create('/contact')
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setPriority(0.6)
        );

        $sitemap->add(
            Url::create('/about')
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setPriority(0.5)
        );

        // — Dynamic product pages —
        Product::where('is_active', true)
            ->select(['slug', 'updated_at'])
            ->orderBy('updated_at', 'desc')
            ->each(function (Product $product) use ($sitemap) {
                $sitemap->add(
                    Url::create('/products/' . $product->slug)
                        ->setLastModificationDate($product->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.8)
                );
            });

        // — Landing pages —
        foreach (['4-6', '7-10', '11-14'] as $range) {
            $sitemap->add(
                Url::create('/smartwatches/bavshvis-saati-' . $range)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.85)
            );
        }
        $sitemap->add(Url::create('/sim-card-guide')->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)->setPriority(0.75));
        $sitemap->add(Url::create('/gift-guide')->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)->setPriority(0.75));

        // — Blog pages —
        $sitemap->add(
            Url::create('/blog')
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(0.8)
        );

        Article::where('is_published', true)
            ->select(['slug', 'updated_at', 'published_at'])
            ->orderBy('published_at', 'desc')
            ->each(function (Article $article) use ($sitemap) {
                $sitemap->add(
                    Url::create('/blog/' . $article->slug)
                        ->setLastModificationDate($article->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                        ->setPriority(0.7)
                );
            });

        return $sitemap->toResponse(request());
    }
}
