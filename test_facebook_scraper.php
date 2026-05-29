<?php

/**
 * Test Apify Web Scraper on Facebook Page
 * Simplified version - just test if we can access Facebook
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=================================================\n";
echo "Testing Apify Web Scraper on Facebook\n";
echo "=================================================\n\n";

$apifyToken = config('services.apify.token');
$baseUrl = config('services.apify.base_url', 'https://api.apify.com/v2');
$actorId = 'apify~web-scraper';

if (empty($apifyToken)) {
    echo "❌ ERROR: APIFY_API_TOKEN not configured\n";
    exit(1);
}

echo "✓ Token: " . substr($apifyToken, 0, 10) . "...\n";
echo "✓ Actor: {$actorId}\n\n";

// Simplified Page Function
$pageFunction = <<<'JS'
async function pageFunction(context) {
    const { request, log } = context;
    log.info('Testing Facebook access');

    await context.page.waitForTimeout(3000);

    const title = await context.page.title();
    const url = context.page.url();
    const html = await context.page.content();

    const isBlocked = html.includes('blocked') ||
                     html.includes('captcha') ||
                     html.includes('checkpoint');

    const bodyText = await context.page.evaluate(() => {
        return document.body ? document.body.innerText.substring(0, 500) : '';
    });

    return {
        url,
        title,
        htmlLength: html.length,
        bodyTextPreview: bodyText,
        isBlocked,
        success: !isBlocked && html.length > 1000,
    };
}
JS;

$input = [
    'startUrls' => [['url' => 'https://www.facebook.com/i.Mobile.ge']],
    'pageFunction' => $pageFunction,
    'proxyConfiguration' => ['useApifyProxy' => true],
    'maxRequestsPerCrawl' => 1,
    'maxConcurrency' => 1,
    'navigationTimeoutSecs' => 30,
    'waitUntil' => ['domcontentloaded'],
];

echo "🚀 Starting scraper (may take 30-60s)...\n\n";

try {
    $response = Http::withToken($apifyToken)
        ->timeout(180)
        ->post("{$baseUrl}/acts/{$actorId}/run-sync-get-dataset-items", $input);

    if (!$response->successful()) {
        echo "❌ ERROR: {$response->status()}\n";
        echo substr($response->body(), 0, 500) . "\n";
        exit(1);
    }

    $items = $response->json();

    if (empty($items)) {
        echo "⚠️  No data returned\n";
        exit(1);
    }

    echo "=================================================\n";
    echo "RESULTS\n";
    echo "=================================================\n\n";

    foreach ($items as $item) {
        echo "URL: " . ($item['url'] ?? 'N/A') . "\n";
        echo "Title: " . ($item['title'] ?? 'N/A') . "\n";
        echo "HTML Length: " . ($item['htmlLength'] ?? 0) . " bytes\n";
        echo "Blocked: " . ($item['isBlocked'] ? '❌ YES' : '✅ NO') . "\n";
        echo "Success: " . ($item['success'] ? '✅ YES' : '❌ NO') . "\n\n";

        if (!empty($item['bodyTextPreview'])) {
            echo "Body Preview:\n";
            echo substr($item['bodyTextPreview'], 0, 300) . "...\n\n";
        }
    }

    echo "=================================================\n";
    echo "ANALYSIS\n";
    echo "=================================================\n\n";

    $firstItem = $items[0] ?? [];
    $success = $firstItem['success'] ?? false;
    $blocked = $firstItem['isBlocked'] ?? false;

    if ($success && !$blocked) {
        echo "✅ SUCCESS! Web Scraper can access Facebook\n";
        echo "   Next: Add DOM selectors for post extraction\n\n";
    } elseif ($blocked) {
        echo "❌ BLOCKED by Facebook\n";
        echo "   Solutions:\n";
        echo "   1. Try residential proxies (paid)\n";
        echo "   2. Use Facebook Graph API\n";
        echo "   3. Consider Instagram instead\n\n";
    } else {
        echo "⚠️  INCONCLUSIVE\n";
        echo "   Check Apify Console for logs\n\n";
    }

    $debugFile = __DIR__ . '/storage/logs/fb_test_' . date('His') . '.json';
    file_put_contents($debugFile, json_encode($items, JSON_PRETTY_PRINT));
    echo "📁 Saved to: {$debugFile}\n\n";

} catch (\Throwable $e) {
    echo "❌ EXCEPTION: {$e->getMessage()}\n";
    exit(1);
}

echo "Test completed!\n";
