<?php

/**
 * Basic Apify Web Scraper Test
 * Test on simple site first (example.com)
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Apify Web Scraper (Basic)\n";
echo "=================================\n\n";

$apifyToken = config('services.apify.token');

if (empty($apifyToken)) {
    echo "❌ No APIFY_API_TOKEN\n";
    exit(1);
}

echo "✓ Token configured\n\n";

// Very simple page function
$pageFunction = <<<'JS'
async function pageFunction(context) {
    return {
        url: context.request.url,
        title: await context.page.title(),
        test: 'success'
    };
}
JS;

$input = [
    'startUrls' => [['url' => 'https://example.com']],
    'pageFunction' => $pageFunction,
    'maxRequestsPerCrawl' => 1,
];

echo "Testing on example.com...\n\n";

try {
    $response = Http::withToken($apifyToken)
        ->timeout(120)
        ->post('https://api.apify.com/v2/acts/apify~web-scraper/run-sync-get-dataset-items', $input);
    
    if (!$response->successful()) {
        echo "❌ Failed: {$response->status()}\n";
        echo $response->body() . "\n";
        exit(1);
    }
    
    $items = $response->json();
    
    echo "✅ Response received\n";
    echo "Items count: " . count($items) . "\n\n";
    
    if (!empty($items)) {
        echo "First item:\n";
        print_r($items[0]);
        echo "\n";
        
        echo "=================================\n";
        echo "✅ Apify Web Scraper WORKS!\n";
        echo "=================================\n\n";
        
        echo "Now test Facebook:\n";
        echo "php test_facebook_scraper.php\n\n";
    } else {
        echo "⚠️  No items returned\n";
        echo "Full response:\n";
        print_r($items);
    }
    
} catch (\Throwable $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
    exit(1);
}
