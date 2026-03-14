<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SeoHealthCheck extends Command
{
    protected $signature = 'seo:health-check {--detailed : Show detailed information}';
    protected $description = 'Run SEO health check on the website';

    private array $issues = [];
    private array $warnings = [];
    private array $passed = [];

    public function handle()
    {
        $this->info('🔍 Starting SEO Health Check...');
        $this->newLine();

        $this->checkRobotsTxt();
        $this->checkSitemap();
        $this->checkProductsMeta();
        $this->checkArticlesMeta();
        $this->checkImages();
        $this->checkSchemaMarkup();
        $this->checkPerformance();

        $this->displayResults();

        return count($this->issues) === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function checkRobotsTxt(): void
    {
        $this->info('📄 Checking robots.txt...');

        $robotsPath = public_path('robots.txt');

        if (!File::exists($robotsPath)) {
            $this->issues[] = 'robots.txt file not found';
            return;
        }

        $content = File::get($robotsPath);

        if (str_contains($content, 'Sitemap:')) {
            $this->passed[] = 'robots.txt contains sitemap reference';
        } else {
            $this->warnings[] = 'robots.txt missing sitemap reference';
        }

        if (str_contains($content, 'Disallow: /admin')) {
            $this->passed[] = 'Admin routes are blocked in robots.txt';
        } else {
            $this->issues[] = 'Admin routes not blocked in robots.txt';
        }
    }

    private function checkSitemap(): void
    {
        $this->info('🗺️  Checking sitemap...');

        try {
            $response = Http::timeout(5)->get(url('/sitemap.xml'));

            if ($response->successful()) {
                $this->passed[] = 'Sitemap is accessible';

                $xml = simplexml_load_string($response->body());
                if ($xml) {
                    $urlCount = count($xml->url ?? []);
                    $this->passed[] = "Sitemap contains {$urlCount} URLs";

                    if ($urlCount < 5) {
                        $this->warnings[] = 'Sitemap has very few URLs';
                    }
                } else {
                    $this->issues[] = 'Sitemap XML is malformed';
                }
            } else {
                $this->issues[] = 'Sitemap is not accessible (HTTP ' . $response->status() . ')';
            }
        } catch (\Exception $e) {
            $this->warnings[] = 'Could not check sitemap: ' . $e->getMessage();
        }
    }

    private function checkProductsMeta(): void
    {
        $this->info('🛍️  Checking products meta data...');

        $products = Product::active()->get();
        $totalProducts = $products->count();

        if ($totalProducts === 0) {
            $this->warnings[] = 'No active products found';
            return;
        }

        $missingMeta = 0;
        $shortDescriptions = 0;
        $longDescriptions = 0;
        $missingImages = 0;

        foreach ($products as $product) {
            // Check meta description
            $metaDesc = $product->meta_description ?? $product->short_description ?? '';
            $metaLength = mb_strlen($metaDesc);

            if (empty($metaDesc)) {
                $missingMeta++;
            } elseif ($metaLength < 120) {
                $shortDescriptions++;
            } elseif ($metaLength > 160) {
                $longDescriptions++;
            }

            // Check images
            if (!$product->primaryImage) {
                $missingImages++;
            }
        }

        $this->passed[] = "Checked {$totalProducts} active products";

        if ($missingMeta > 0) {
            $this->warnings[] = "{$missingMeta} products missing meta descriptions";
        }
        if ($shortDescriptions > 0) {
            $this->warnings[] = "{$shortDescriptions} products have short meta descriptions (<120 chars)";
        }
        if ($longDescriptions > 0) {
            $this->warnings[] = "{$longDescriptions} products have long meta descriptions (>160 chars)";
        }
        if ($missingImages > 0) {
            $this->issues[] = "{$missingImages} products missing primary images";
        }
    }

    private function checkArticlesMeta(): void
    {
        $this->info('📝 Checking articles meta data...');

        $articles = Article::published()->get();
        $totalArticles = $articles->count();

        if ($totalArticles === 0) {
            $this->warnings[] = 'No published articles found';
            return;
        }

        $missingMeta = 0;

        foreach ($articles as $article) {
            if (empty($article->meta_description) && empty($article->excerpt)) {
                $missingMeta++;
            }
        }

        $this->passed[] = "Checked {$totalArticles} published articles";

        if ($missingMeta > 0) {
            $this->warnings[] = "{$missingMeta} articles missing meta descriptions";
        }
    }

    private function checkImages(): void
    {
        $this->info('🖼️  Checking images optimization...');

        $products = Product::with('primaryImage')->active()->get();
        $totalImages = 0;
        $largeImages = 0;

        foreach ($products as $product) {
            if ($product->primaryImage) {
                $totalImages++;
                $imagePath = storage_path('app/public/' . $product->primaryImage->path);

                if (File::exists($imagePath)) {
                    $size = File::size($imagePath);
                    // Check if image is larger than 500KB
                    if ($size > 500 * 1024) {
                        $largeImages++;
                    }
                }
            }
        }

        $this->passed[] = "Checked {$totalImages} product images";

        if ($largeImages > 0) {
            $this->warnings[] = "{$largeImages} images are larger than 500KB (consider optimization)";
        }
    }

    private function checkSchemaMarkup(): void
    {
        $this->info('📊 Checking schema markup...');

        // Check if home page has LocalBusiness schema
        $homePath = resource_path('views/home.blade.php');
        if (File::exists($homePath)) {
            $content = File::get($homePath);

            if (str_contains($content, 'LocalBusiness')) {
                $this->passed[] = 'Home page has LocalBusiness schema';
            } else {
                $this->warnings[] = 'Home page missing LocalBusiness schema';
            }

            if (str_contains($content, 'Organization')) {
                $this->passed[] = 'Home page has Organization schema';
            }
        }

        // Check product schema
        $productPath = resource_path('views/products/show.blade.php');
        if (File::exists($productPath)) {
            $content = File::get($productPath);

            if (str_contains($content, '@type') && str_contains($content, 'Product')) {
                $this->passed[] = 'Product pages have Product schema';
            } else {
                $this->issues[] = 'Product pages missing Product schema';
            }

            if (str_contains($content, 'AggregateRating') || str_contains($content, 'aggregateRating')) {
                $this->passed[] = 'Product schema includes rating support';
            }
        }
    }

    private function checkPerformance(): void
    {
        $this->info('⚡ Checking performance optimizations...');

        // Check if lazy loading is implemented
        $appJsPath = resource_path('js/app.js');
        if (File::exists($appJsPath)) {
            $content = File::get($appJsPath);

            if (str_contains($content, 'lazy-load')) {
                $this->passed[] = 'Lazy loading JavaScript is implemented';
            } else {
                $this->warnings[] = 'Lazy loading not found in app.js';
            }
        }

        // Check if resource hints are in layout
        $layoutPath = resource_path('views/layouts/app.blade.php');
        if (File::exists($layoutPath)) {
            $content = File::get($layoutPath);

            if (str_contains($content, 'preconnect') || str_contains($content, 'dns-prefetch')) {
                $this->passed[] = 'Resource hints (preconnect/dns-prefetch) are implemented';
            } else {
                $this->warnings[] = 'Missing resource hints for performance';
            }
        }
    }

    private function displayResults(): void
    {
        $this->newLine(2);
        $this->line('═══════════════════════════════════════════════════════');
        $this->info('                    SEO HEALTH CHECK RESULTS');
        $this->line('═══════════════════════════════════════════════════════');
        $this->newLine();

        // Passed checks
        if (count($this->passed) > 0) {
            $this->info('✅ PASSED (' . count($this->passed) . ')');
            foreach ($this->passed as $item) {
                $this->line('   • ' . $item);
            }
            $this->newLine();
        }

        // Warnings
        if (count($this->warnings) > 0) {
            $this->warn('⚠️  WARNINGS (' . count($this->warnings) . ')');
            foreach ($this->warnings as $item) {
                $this->line('   • ' . $item);
            }
            $this->newLine();
        }

        // Issues
        if (count($this->issues) > 0) {
            $this->error('❌ ISSUES (' . count($this->issues) . ')');
            foreach ($this->issues as $item) {
                $this->line('   • ' . $item);
            }
            $this->newLine();
        }

        // Summary
        $total = count($this->passed) + count($this->warnings) + count($this->issues);
        $score = $total > 0 ? round((count($this->passed) / $total) * 100) : 0;

        $this->line('═══════════════════════════════════════════════════════');
        $this->info("SEO Health Score: {$score}%");
        $this->line('═══════════════════════════════════════════════════════');

        if ($score >= 80) {
            $this->info('🎉 Great! Your SEO health is excellent!');
        } elseif ($score >= 60) {
            $this->warn('👍 Good, but there\'s room for improvement.');
        } else {
            $this->error('⚠️  Needs attention. Please fix the issues above.');
        }
    }
}
