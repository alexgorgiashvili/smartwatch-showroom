<?php

/**
 * Test AI Relevance Analysis
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\FacebookCompetitorAiService;
use App\Models\FacebookCompetitorPost;

echo "Testing AI Relevance Analysis\n";
echo "==============================\n\n";

$aiService = app(FacebookCompetitorAiService::class);

$posts = FacebookCompetitorPost::where('competitor_page_id', 2)->get();

echo "Posts to analyze: " . $posts->count() . "\n\n";

foreach ($posts as $post) {
    echo "Post #{$post->id}:\n";
    echo substr($post->text, 0, 150) . "...\n\n";
}

echo "Running AI analysis...\n\n";

try {
    $filtered = $aiService->filterRelevantPosts($posts);
    
    echo "✅ Analysis complete!\n";
    echo "Filtered posts: {$filtered}\n\n";
    
    // Show results
    $posts->fresh();
    foreach ($posts as $post) {
        echo "---\n";
        echo "Post #{$post->id}:\n";
        echo "Text: " . substr($post->text, 0, 100) . "...\n";
        echo "Relevant: " . ($post->is_relevant ? '✅ YES' : '❌ NO') . "\n";
        echo "Score: " . $post->relevance_score . "/100\n";
        echo "Reason: " . ($post->relevance_reason ?? 'N/A') . "\n";
        if ($post->product_mentions_json) {
            echo "Products: " . json_encode($post->product_mentions_json, JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "\n";
    }
    
    $relevantCount = FacebookCompetitorPost::where('competitor_page_id', 2)
        ->where('is_relevant', true)
        ->count();
    
    echo "==============================\n";
    echo "RESULT: {$relevantCount} relevant posts found\n";
    echo "==============================\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}
