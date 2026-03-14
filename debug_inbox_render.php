<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Debugging Inbox Render ===\n\n";

try {
    $manager = new \App\Livewire\Inbox\InboxManager();
    $render = $manager->render();
    $html = $render->render();
    
    echo "Checking rendered HTML...\n";
    
    // Check if conversation-list is included
    if (strpos($html, 'livewire:inbox.conversation-list') !== false) {
        echo "✓ Found livewire:inbox.conversation-list\n";
    } else {
        echo "✗ livewire:inbox.conversation-list NOT found\n";
    }
    
    // Check if wire:model directives exist
    if (strpos($html, 'wire:model="search"') !== false) {
        echo "✓ Found wire:model=\"search\"\n";
    } else {
        echo "✗ wire:model=\"search\" NOT found\n";
    }
    
    if (strpos($html, 'wire:model="platformFilter"') !== false) {
        echo "✓ Found wire:model=\"platformFilter\"\n";
    } else {
        echo "✗ wire:model=\"platformFilter\" NOT found\n";
    }
    
    if (strpos($html, 'wire:model="statusFilter"') !== false) {
        echo "✓ Found wire:model=\"statusFilter\"\n";
    } else {
        echo "✗ wire:model=\"statusFilter\" NOT found\n";
    }
    
    // Save HTML to file for inspection
    file_put_contents('debug_inbox.html', $html);
    echo "\nHTML saved to debug_inbox.html for inspection\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Debug Complete ===\n";
