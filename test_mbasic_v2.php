<?php

/**
 * Test mbasic.facebook.com v2
 * With increased timeout and simpler approach
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing mbasic.facebook.com v2\n";
echo "==============================\n\n";

$apifyToken = config('services.apify.token');

// Simpler page function - just check if mbasic works
$pageFunction = <<<'JS'
async function pageFunction(context) {
    const $ = context.jQuery;
    
    const title = $('title').text();
    const bodyText = $('body').text().substring(0, 1000);
    
    // Check if we got actual content or redirect
    const hasContent = bodyText.length > 100;
    const isLoginPage = bodyText.includes('Log in') || bodyText.includes('Sign up');
    
    // Try to find posts (mbasic uses simple structure)
    const postCount = $('article').length || 
                     $('div[data-ft]').length ||
                     $('div.story_body_container').length;
    
    return {
        url: context.request.url,
        title: title,
        bodyTextPreview: bodyText,
        hasContent: hasContent,
        isLoginPage: isLoginPage,
        postCount: postCount,
        htmlLength: $('html').html().length,
    };
}
JS;

$input = [
    'startUrls' => [['url' => 'https://mbasic.facebook.com/i.Mobile.ge']],
    'pageFunction' => $pageFunction,
    'proxyConfiguration' => ['useApifyProxy' => true],
    'maxRequestsPerCrawl' => 1,
    'maxConcurrency' => 1,
    'navigationTimeoutSecs' => 90, // Increased timeout
    'waitUntil' => ['domcontentloaded'], // Don't wait for full load
];

echo "Testing with 90s timeout...\n\n";

try {
    $response = Http::withToken($apifyToken)
        ->timeout(150)
        ->post('https://api.apify.com/v2/acts/apify~web-scraper/run-sync-get-dataset-items', $input);
    
    if (!$response->successful()) {
        echo "❌ Failed: {$response->status()}\n";
        echo substr($response->body(), 0, 500) . "\n";
        exit(1);
    }
    
    $items = $response->json();
    
    if (empty($items)) {
        echo "⚠️  Empty response\n";
        exit(1);
    }
    
    $item = $items[0];
    
    if (isset($item['#error'])) {
        echo "❌ Error occurred:\n";
        print_r($item['#debug']['errorMessages'] ?? ['Unknown']);
        echo "\n";
        exit(1);
    }
    
    echo "✅ Success!\n\n";
    echo "URL: " . ($item['url'] ?? 'N/A') . "\n";
    echo "Title: " . ($item['title'] ?? 'N/A') . "\n";
    echo "HTML Length: " . ($item['htmlLength'] ?? 0) . " bytes\n";
    echo "Has Content: " . ($item['hasContent'] ? '✅ YES' : '❌ NO') . "\n";
    echo "Is Login Page: " . ($item['isLoginPage'] ? '⚠️  YES' : '✅ NO') . "\n";
    echo "Post Count: " . ($item['postCount'] ?? 0) . "\n\n";
    
    if (!empty($item['bodyTextPreview'])) {
        echo "Body Preview:\n";
        echo substr($item['bodyTextPreview'], 0, 400) . "...\n\n";
    }
    
    echo "==============================\n";
    echo "VERDICT\n";
    echo "==============================\n\n";
    
    $hasContent = $item['hasContent'] ?? false;
    $isLogin = $item['isLoginPage'] ?? false;
    $postCount = $item['postCount'] ?? 0;
    
    if ($hasContent && !$isLogin && $postCount > 0) {
        echo "✅ EXCELLENT! mbasic.facebook.com works!\n";
        echo "   - Found {$postCount} posts\n";
        echo "   - No login required\n";
        echo "   - Ready for implementation\n\n";
    } elseif ($hasContent && !$isLogin) {
        echo "⚠️  PARTIAL SUCCESS\n";
        echo "   - Page loaded but no posts found\n";
        echo "   - May need better selectors\n";
        echo "   - Check body preview above\n\n";
    } elseif ($isLogin) {
        echo "❌ LOGIN REQUIRED\n";
        echo "   - mbasic redirects to login\n";
        echo "   - Public pages may not work on mbasic\n";
        echo "   - Alternative: Use www.facebook.com with Puppeteer\n\n";
    } else {
        echo "⚠️  UNCLEAR\n";
        echo "   - Check body preview for details\n\n";
    }
    
    file_put_contents(
        __DIR__ . '/storage/logs/mbasic_v2.json',
        json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    echo "📁 Saved: storage/logs/mbasic_v2.json\n\n";
    
} catch (\Throwable $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
    exit(1);
}

echo "==============================\n";
echo "RECOMMENDATION\n";
echo "==============================\n\n";

echo "Based on all tests:\n\n";

echo "1. ✅ www.facebook.com works (tested)\n";
echo "   - Loads successfully\n";
echo "   - But needs JavaScript rendering\n";
echo "   - Use: apify/puppeteer-scraper\n";
echo "   - Cost: ~0.01 CU per page\n\n";

echo "2. ⚠️  mbasic.facebook.com (this test)\n";
echo "   - Simpler HTML\n";
echo "   - May require login for some pages\n";
echo "   - If works: cheaper option\n\n";

echo "3. 🎯 BEST OPTION:\n";
echo "   Use apify/puppeteer-scraper on www.facebook.com\n";
echo "   - Proven to work\n";
echo "   - Handles JavaScript\n";
echo "   - Still within free tier for 2-3 competitors\n";
echo "   - Cost: ~$0.02/month (vs $5 free tier)\n\n";

echo "Next: Implement with Puppeteer Scraper?\n";
