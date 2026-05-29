<?php

/**
 * Test mbasic.facebook.com scraping
 * Mobile version is simpler HTML, easier to parse
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing mbasic.facebook.com (Mobile Version)\n";
echo "============================================\n\n";

$apifyToken = config('services.apify.token');

if (empty($apifyToken)) {
    echo "❌ No token\n";
    exit(1);
}

// Page function for mbasic.facebook.com
// Much simpler HTML structure!
$pageFunction = <<<'JS'
async function pageFunction(context) {
    const $ = context.jQuery;
    
    // mbasic has simple, clean HTML
    const title = $('title').text();
    
    // Extract posts from mbasic
    const posts = [];
    
    // mbasic uses simple div structure
    $('div[data-ft]').each((index, element) => {
        if (index >= 10) return false; // limit to 10 posts
        
        const $post = $(element);
        
        // Text content
        const text = $post.find('p, div > span').first().text().trim();
        
        // Post link
        const postLink = $post.find('a[href*="/story.php"]').attr('href');
        
        // Images
        const images = [];
        $post.find('img').each((i, img) => {
            const src = $(img).attr('src');
            if (src && !src.includes('emoji')) {
                images.push(src);
            }
        });
        
        // Reactions/likes (mbasic shows as text)
        const reactionsText = $post.find('a[href*="reaction"]').text();
        
        if (text.length > 10) {
            posts.push({
                text: text.substring(0, 300),
                postLink: postLink ? 'https://mbasic.facebook.com' + postLink : null,
                images: images.slice(0, 3),
                reactionsText: reactionsText,
            });
        }
    });
    
    return {
        url: context.request.url,
        title: title,
        postsFound: posts.length,
        posts: posts,
        htmlLength: $('html').html().length,
    };
}
JS;

$input = [
    'startUrls' => [['url' => 'https://mbasic.facebook.com/i.Mobile.ge']],
    'pageFunction' => $pageFunction,
    'proxyConfiguration' => ['useApifyProxy' => true],
    'maxRequestsPerCrawl' => 1,
];

echo "Testing i.Mobile.ge on mbasic.facebook.com...\n";
echo "Using: apify/web-scraper (cheaper than Puppeteer)\n\n";

try {
    $startTime = microtime(true);
    
    $response = Http::withToken($apifyToken)
        ->timeout(120)
        ->post('https://api.apify.com/v2/acts/apify~web-scraper/run-sync-get-dataset-items', $input);
    
    $duration = round(microtime(true) - $startTime, 2);
    
    if (!$response->successful()) {
        echo "❌ Failed: {$response->status()}\n";
        echo substr($response->body(), 0, 800) . "\n";
        exit(1);
    }
    
    $items = $response->json();
    
    echo "✅ Scraping completed in {$duration}s\n";
    echo "Items: " . count($items) . "\n\n";
    
    if (!empty($items)) {
        foreach ($items as $i => $item) {
            if (isset($item['#error'])) {
                echo "⚠️  Item #{$i} has error\n";
                if (!empty($item['#debug']['errorMessages'])) {
                    print_r($item['#debug']['errorMessages']);
                }
                echo "\n";
                continue;
            }
            
            echo "📄 Result:\n";
            echo "   URL: " . ($item['url'] ?? 'N/A') . "\n";
            echo "   Title: " . ($item['title'] ?? 'N/A') . "\n";
            echo "   HTML Length: " . ($item['htmlLength'] ?? 0) . " bytes\n";
            echo "   Posts Found: " . ($item['postsFound'] ?? 0) . "\n\n";
            
            if (!empty($item['posts'])) {
                echo "   📝 Posts:\n";
                foreach ($item['posts'] as $pi => $post) {
                    echo "   ---\n";
                    echo "   Post #{$pi}:\n";
                    echo "   Text: " . substr($post['text'] ?? '', 0, 150) . "...\n";
                    echo "   Images: " . count($post['images'] ?? []) . "\n";
                    echo "   Reactions: " . ($post['reactionsText'] ?? 'N/A') . "\n";
                    if (!empty($post['postLink'])) {
                        echo "   Link: " . $post['postLink'] . "\n";
                    }
                }
                echo "\n";
            }
        }
        
        echo "============================================\n";
        echo "ANALYSIS\n";
        echo "============================================\n\n";
        
        $postsFound = $items[0]['postsFound'] ?? 0;
        $hasError = isset($items[0]['#error']);
        
        if ($postsFound > 0 && !$hasError) {
            echo "✅ SUCCESS!\n";
            echo "   - mbasic.facebook.com works perfectly!\n";
            echo "   - Extracted {$postsFound} posts\n";
            echo "   - Simple HTML, easy parsing\n";
            echo "   - Works with Web Scraper (cheap!)\n\n";
            
            echo "💰 Cost Estimate:\n";
            echo "   - Web Scraper on mbasic: ~0.001 CU per page\n";
            echo "   - 3 competitors × 2/week = 6 scrapes/week\n";
            echo "   - Monthly: ~0.024 CU = $0.001/month\n";
            echo "   - ✅ WELL within free tier ($5 = 125 CU)\n\n";
            
            echo "💡 Next Steps:\n";
            echo "   1. Update plan to use mbasic.facebook.com\n";
            echo "   2. Keep apify/web-scraper (no need for Puppeteer)\n";
            echo "   3. Implement in Laravel\n";
            echo "   4. Add mcpc CLI for better integration\n\n";
            
            echo "🎯 Recommendation:\n";
            echo "   USE mbasic.facebook.com - it's perfect!\n";
            echo "   - Cheaper than Puppeteer\n";
            echo "   - Simpler than regular Facebook\n";
            echo "   - Less likely to be blocked\n";
            echo "   - Free tier friendly\n\n";
            
        } elseif ($hasError) {
            echo "❌ FAILED\n";
            echo "   Check error messages above\n\n";
        } else {
            echo "⚠️  NO POSTS FOUND\n";
            echo "   - Page loaded but no posts extracted\n";
            echo "   - May need to adjust selectors\n";
            echo "   - Check saved JSON for debugging\n\n";
        }
        
        // Save for debugging
        $debugFile = __DIR__ . '/storage/logs/mbasic_test.json';
        file_put_contents($debugFile, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "📁 Full response: {$debugFile}\n\n";
        
    } else {
        echo "⚠️  Empty response\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
    exit(1);
}
