<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== Cleaning Duplicate Agents ===\n\n";

// Find duplicate agents
$duplicates = DB::table('agents')
    ->select('user_id', DB::raw('count(*) as count'))
    ->groupBy('user_id')
    ->having('count', '>', 1)
    ->get();

echo "Found duplicate agents for:\n";
foreach ($duplicates as $duplicate) {
    $user = User::find($duplicate->user_id);
    echo "  - User: {$user->name} ({$duplicate->count} agents)\n";
    
    // Keep the first agent, delete the rest
    $agentsToDelete = Agent::where('user_id', $duplicate->user_id)
        ->orderBy('id')
        ->skip(1)
        ->get();
    
    foreach ($agentsToDelete as $agent) {
        echo "    - Deleting agent ID: {$agent->id}\n";
        $agent->delete();
    }
}

echo "\n=== Cleanup Complete ===\n";

// Verify
$agents = Agent::with('user')->get();
echo "\nTotal agents after cleanup: " . $agents->count() . "\n";
