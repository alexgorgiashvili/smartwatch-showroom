<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FacebookCompetitorPage;
use App\Models\FacebookCompetitorPost;

echo "Facebook Competitor Scraping Results\n";
echo "=====================================\n\n";

// Summary
$totalPosts = FacebookCompetitorPost::count();
$relevantPosts = FacebookCompetitorPost::where('is_relevant', true)->count();
$activeCompetitors = FacebookCompetitorPage::active()->count();

echo "📊 Summary:\n";
echo "  Active Competitors: {$activeCompetitors}\n";
echo "  Total Posts: {$totalPosts}\n";
echo "  Relevant Posts: {$relevantPosts} (" . round($relevantPosts / max($totalPosts, 1) * 100) . "%)\n\n";

// By competitor
echo "📈 Posts by Competitor:\n";
$pages = FacebookCompetitorPage::withCount('posts')->get();
foreach ($pages as $page) {
    $relevant = FacebookCompetitorPost::where('competitor_page_id', $page->id)
        ->where('is_relevant', true)
        ->count();
    
    echo "  • {$page->name}: {$page->posts_count} posts ({$relevant} relevant)\n";
}

echo "\n";

// Show relevant posts
echo "✅ Relevant Posts:\n";
echo "==================\n\n";

$relevantPosts = FacebookCompetitorPost::where('is_relevant', true)
    ->with('competitorPage')
    ->get();

if ($relevantPosts->isEmpty()) {
    echo "No relevant posts found.\n";
} else {
    foreach ($relevantPosts as $post) {
        echo "---\n";
        echo "Competitor: {$post->competitorPage->name}\n";
        echo "Score: {$post->relevance_score}/100\n";
        echo "Posted: {$post->posted_at}\n";
        echo "Engagement: {$post->likes_count} likes, {$post->comments_count} comments, {$post->shares_count} shares\n";
        echo "Text: " . substr($post->text, 0, 200) . "...\n";
        echo "Reason: {$post->relevance_reason}\n";
        if ($post->product_mentions_json) {
            echo "Products: " . json_encode($post->product_mentions_json, JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "\n";
    }
}

// Cost calculation
echo "💰 Cost Analysis:\n";
echo "=================\n\n";

$postsPerWeek = $totalPosts;
$postsPerMonth = $postsPerWeek * 4;
$costPerPost = 0.007;
$monthlyCost = $postsPerMonth * $costPerPost;

echo "Posts per scrape: ~" . round($totalPosts / $activeCompetitors) . "\n";
echo "Scraping frequency: Weekly\n";
echo "Posts per month: {$postsPerMonth}\n";
echo "Monthly cost: $" . number_format($monthlyCost, 2) . "\n";
echo "Free tier limit: $5.00\n";
echo "Status: " . ($monthlyCost < 5 ? "✅ Within free tier" : "❌ Exceeds free tier") . "\n";
