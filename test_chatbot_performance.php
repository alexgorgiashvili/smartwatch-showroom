<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\Chatbot\IntentAnalyzerService;
use App\Services\Chatbot\Agents\SupervisorAgent;
use App\Services\Chatbot\MultiLayerCacheService;
use App\Services\Chatbot\BifurcatedMemoryService;
use App\Services\Chatbot\SmartSearchOrchestrator;

echo "🔍 Chatbot Performance Diagnostics\n";
echo "==================================\n\n";

$message = "რა ფასად მაქვს Q19?";
$conversationId = 1;
$customerId = 1;

// Test 1: Intent Analysis
echo "1️⃣ Testing Intent Analysis...\n";
$start = microtime(true);
$intentAnalyzer = app(IntentAnalyzerService::class);
$intentResult = $intentAnalyzer->analyze($message, [], [], ['trace_id' => 'perf_test']);
$duration = round((microtime(true) - $start) * 1000);
echo "   ✓ Intent: {$intentResult->intent()}\n";
echo "   ✓ Duration: {$duration}ms\n\n";

// Test 2: Cache Check
echo "2️⃣ Testing Cache...\n";
$start = microtime(true);
$cache = app(MultiLayerCacheService::class);
$cached = $cache->getCachedResponse($message, $intentResult);
$duration = round((microtime(true) - $start) * 1000);
echo "   ✓ Cache Hit: " . ($cached ? 'YES' : 'NO') . "\n";
echo "   ✓ Duration: {$duration}ms\n\n";

// Test 3: Memory Operations
echo "3️⃣ Testing Memory Operations...\n";
$start = microtime(true);
$memory = app(BifurcatedMemoryService::class);
$memory->appendMessage($conversationId, 'user', $message);
$preferences = $memory->getUserPreferences($customerId);
$duration = round((microtime(true) - $start) * 1000);
echo "   ✓ Duration: {$duration}ms\n\n";

// Test 4: Search (if needed)
if ($intentResult->requiresSearch()) {
    echo "4️⃣ Testing Search...\n";
    $start = microtime(true);
    $search = app(SmartSearchOrchestrator::class);
    $searchResult = $search->search($intentResult);
    $duration = round((microtime(true) - $start) * 1000);
    echo "   ✓ Products Found: " . ($searchResult ? $searchResult->products()->count() : 0) . "\n";
    echo "   ✓ Duration: {$duration}ms\n\n";
}

// Test 5: Full Supervisor Orchestration
echo "5️⃣ Testing Full Supervisor...\n";
$start = microtime(true);
$supervisor = app(SupervisorAgent::class);
$result = $supervisor->orchestrate(
    $message,
    $conversationId,
    $customerId,
    $intentResult,
    $preferences,
    ['trace_id' => 'perf_test']
);
$duration = round((microtime(true) - $start) * 1000);
echo "   ✓ Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
echo "   ✓ Duration: {$duration}ms\n\n";

// Summary
echo "📊 Performance Summary:\n";
echo "==================================\n";
echo "Check storage/logs/chatbot_widget_trace_*.log for detailed breakdown\n";
echo "\nRecommendations:\n";
echo "- If Intent Analysis > 2000ms: Check OpenAI API latency\n";
echo "- If Search > 3000ms: Check Pinecone connection\n";
echo "- If Supervisor > 8000ms: Enable parallel execution\n";
echo "- If Cache always MISS: Check Redis connection\n";
