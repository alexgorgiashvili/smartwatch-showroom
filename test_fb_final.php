<?php

/**
 * Final Facebook Scraper Test
 * Using correct Apify Web Scraper syntax
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Facebook with Apify Web Scraper\n";
echo "========================================\n\n";

$apifyToken = config('services.apify.token');

if (empty($apifyToken)) {
    echo "❌ No token\n";
    exit(1);
}

// Correct syntax for Web Scraper pageFunction
$pageFunction = <<<'JS'
async function pageFunction(context) {
    // context.request has the URL
    // context.jQuery is the $ function
    // No context.page in Web Scraper!
    
    const $ = context.jQuery;
    const title = $('title').text();
    const bodyText = $('body').text().substring(0, 500);
    
    return {
        url: context.request.url,
        title: title,
        bodyPreview: bodyText,
        htmlLength: $('html').html().length,
    };
}
JS;

$input = [
    'startUrls' => [['url' => 'https://www.facebook.com/i.Mobile.ge']],
    'pageFunction' => $pageFunction,
    'proxyConfiguration' => ['useApifyProxy' => true],
    'maxRequestsPerCrawl' => 1,
];

echo "Testing i.Mobile.ge Facebook page...\n\n";

try {
    $response = Http::withToken($apifyToken)
        ->timeout(120)
        ->post('https://api.apify.com/v2/acts/apify~web-scraper/run-sync-get-dataset-items', $input);
    
    if (!$response->successful()) {
        echo "❌ Failed: {$response->status()}\n";
        echo substr($response->body(), 0, 800) . "\n";
        exit(1);
    }
    
    $items = $response->json();
    
    echo "✅ Scraping completed\n";
    echo "Items: " . count($items) . "\n\n";
    
    if (!empty($items)) {
        foreach ($items as $i => $item) {
            if (isset($item['#error'])) {
                echo "⚠️  Item #{$i} has error:\n";
                print_r($item['#debug']['errorMessages'] ?? ['Unknown error']);
                echo "\n";
                continue;
            }
            
            echo "📄 Item #{$i}:\n";
            echo "   URL: " . ($item['url'] ?? 'N/A') . "\n";
            echo "   Title: " . ($item['title'] ?? 'N/A') . "\n";
            echo "   HTML Length: " . ($item['htmlLength'] ?? 0) . " bytes\n";
            echo "   Body Preview: " . substr($item['bodyPreview'] ?? '', 0, 200) . "...\n\n";
        }
        
        $hasError = isset($items[0]['#error']);
        $hasData = isset($items[0]['title']) && !empty($items[0]['title']);
        
        echo "========================================\n";
        echo "CONCLUSION\n";
        echo "========================================\n\n";
        
        if ($hasData) {
            echo "✅ SUCCESS!\n";
            echo "   - Apify Web Scraper CAN access Facebook\n";
            echo "   - Data extracted successfully\n";
            echo "   - Title: " . ($items[0]['title'] ?? '') . "\n\n";
            
            $isBlocked = stripos($items[0]['title'] ?? '', 'error') !== false ||
                        stripos($items[0]['bodyPreview'] ?? '', 'blocked') !== false;
            
            if ($isBlocked) {
                echo "⚠️  But page might be blocked/restricted\n";
                echo "   Check body preview above\n\n";
            } else {
                echo "💡 Next Steps:\n";
                echo "   1. Add DOM selectors for posts\n";
                echo "   2. Implement in Laravel with mcpc\n";
                echo "   3. Test with 2-3 competitors\n\n";
            }
        } elseif ($hasError) {
            echo "❌ FAILED with errors\n";
            echo "   Check error messages above\n\n";
        } else {
            echo "⚠️  INCONCLUSIVE\n";
            echo "   No clear success or error\n\n";
        }
        
        // Save for debugging
        file_put_contents(
            __DIR__ . '/storage/logs/fb_test.json',
            json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        echo "📁 Full response saved to storage/logs/fb_test.json\n\n";
    } else {
        echo "⚠️  Empty response\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
    exit(1);
}
