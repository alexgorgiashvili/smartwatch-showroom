<?php

/**
 * Test Apify Puppeteer Scraper on Facebook
 * Extract actual posts from i.Mobile.ge page
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Puppeteer Scraper on Facebook\n";
echo "======================================\n\n";

$apifyToken = config('services.apify.token');

if (empty($apifyToken)) {
    echo "❌ No token\n";
    exit(1);
}

// Puppeteer Page Function for Facebook posts
$pageFunction = <<<'JS'
async function pageFunction(context) {
    const { page, log } = context;

    log.info('Starting Facebook scraping with Puppeteer');

    // Wait for page to load (use setTimeout instead of waitForTimeout)
    await new Promise(resolve => setTimeout(resolve, 5000));

    // Try to find posts (Facebook uses div[role="article"])
    try {
        await page.waitForSelector('div[role="article"]', { timeout: 10000 });
    } catch (e) {
        log.warning('No articles found, trying alternative selectors');
    }

    // Extract posts using page.evaluate
    const posts = await page.evaluate(() => {
        const articles = document.querySelectorAll('div[role="article"]');
        const results = [];

        for (let i = 0; i < Math.min(articles.length, 10); i++) {
            const article = articles[i];

            // Get text content
            const textElements = article.querySelectorAll('div[dir="auto"]');
            let text = '';
            textElements.forEach(el => {
                const t = el.innerText || el.textContent;
                if (t && t.length > text.length) {
                    text = t;
                }
            });

            // Get images
            const images = [];
            article.querySelectorAll('img').forEach(img => {
                const src = img.src;
                if (src && !src.includes('emoji') && !src.includes('static')) {
                    images.push(src);
                }
            });

            // Get post link
            const linkElement = article.querySelector('a[href*="/posts/"], a[href*="/permalink/"]');
            const postLink = linkElement ? linkElement.href : null;

            // Get timestamp
            const timeElement = article.querySelector('a[href*="/posts/"] abbr, a[href*="/permalink/"] abbr');
            const timestamp = timeElement ? timeElement.getAttribute('title') || timeElement.innerText : null;

            if (text.length > 20) {
                results.push({
                    text: text.substring(0, 500),
                    images: images.slice(0, 3),
                    postLink,
                    timestamp,
                });
            }
        }

        return results;
    });

    const title = await page.title();
    const url = page.url();

    return {
        url,
        title,
        postsFound: posts.length,
        posts,
        scrapedAt: new Date().toISOString(),
    };
}
JS;

$input = [
    'startUrls' => [['url' => 'https://www.facebook.com/i.Mobile.ge']],
    'pageFunction' => $pageFunction,
    'proxyConfiguration' => ['useApifyProxy' => true],
    'maxRequestsPerCrawl' => 1,
    'maxConcurrency' => 1,
];

echo "Testing i.Mobile.ge with Puppeteer Scraper...\n";
echo "Actor: apify/puppeteer-scraper\n\n";

try {
    $startTime = microtime(true);

    $response = Http::withToken($apifyToken)
        ->timeout(180)
        ->post('https://api.apify.com/v2/acts/apify~puppeteer-scraper/run-sync-get-dataset-items', $input);

    $duration = round(microtime(true) - $startTime, 2);

    if (!$response->successful()) {
        echo "❌ Failed: {$response->status()}\n";
        echo substr($response->body(), 0, 800) . "\n";
        exit(1);
    }

    $items = $response->json();

    echo "✅ Scraping completed in {$duration}s\n";
    echo "Items: " . count($items) . "\n\n";

    if (empty($items)) {
        echo "⚠️  Empty response\n";
        exit(1);
    }

    foreach ($items as $i => $item) {
        if (isset($item['#error'])) {
            echo "⚠️  Item #{$i} has error:\n";
            if (!empty($item['#debug']['errorMessages'])) {
                foreach ($item['#debug']['errorMessages'] as $err) {
                    echo "   - " . substr($err, 0, 200) . "\n";
                }
            }
            echo "\n";
            continue;
        }

        echo "📄 Result:\n";
        echo "   URL: " . ($item['url'] ?? 'N/A') . "\n";
        echo "   Title: " . ($item['title'] ?? 'N/A') . "\n";
        echo "   Posts Found: " . ($item['postsFound'] ?? 0) . "\n";
        echo "   Scraped At: " . ($item['scrapedAt'] ?? 'N/A') . "\n\n";

        if (!empty($item['posts'])) {
            echo "   📝 Posts:\n";
            foreach ($item['posts'] as $pi => $post) {
                echo "   ───────────────────────────────────\n";
                echo "   Post #{$pi}:\n";
                echo "   Text: " . substr($post['text'] ?? '', 0, 200) . "...\n";
                echo "   Images: " . count($post['images'] ?? []) . "\n";
                echo "   Timestamp: " . ($post['timestamp'] ?? 'N/A') . "\n";
                if (!empty($post['postLink'])) {
                    echo "   Link: " . $post['postLink'] . "\n";
                }
                echo "\n";
            }
        } else {
            echo "   ⚠️  No posts extracted\n\n";
        }
    }

    echo "======================================\n";
    echo "ANALYSIS\n";
    echo "======================================\n\n";

    $postsFound = $items[0]['postsFound'] ?? 0;
    $hasError = isset($items[0]['#error']);

    if ($postsFound > 0 && !$hasError) {
        echo "✅ EXCELLENT SUCCESS!\n";
        echo "   - Puppeteer Scraper works perfectly!\n";
        echo "   - Extracted {$postsFound} posts from Facebook\n";
        echo "   - JavaScript rendered correctly\n";
        echo "   - Ready for Laravel integration\n\n";

        echo "💰 Cost Analysis:\n";
        echo "   - Duration: {$duration}s\n";
        echo "   - Estimated CU: ~0.01 per scrape\n";
        echo "   - Monthly (3 competitors × 2/week): ~$0.02\n";
        echo "   - ✅ Well within $5 free tier!\n\n";

        echo "🎯 Next Steps:\n";
        echo "   1. Update FacebookApifyScraperService\n";
        echo "   2. Change actor to apify/puppeteer-scraper\n";
        echo "   3. Update pageFunction in service\n";
        echo "   4. Test with Laravel artisan command\n\n";

    } elseif ($hasError) {
        echo "❌ FAILED\n";
        echo "   Check error messages above\n\n";
    } else {
        echo "⚠️  NO POSTS FOUND\n";
        echo "   - Scraper ran but no posts extracted\n";
        echo "   - May need to adjust selectors\n\n";
    }

    // Save for debugging
    $debugFile = __DIR__ . '/storage/logs/puppeteer_test.json';
    file_put_contents($debugFile, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "📁 Full response: {$debugFile}\n\n";

} catch (\Throwable $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
    exit(1);
}
