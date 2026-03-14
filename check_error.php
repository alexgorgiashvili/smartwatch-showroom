<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check if CustomerInfoPanel can be instantiated
try {
    $component = new \App\Livewire\Inbox\CustomerInfoPanel();
    echo "CustomerInfoPanel component instantiated successfully\n";
    
    // Try to render with no conversation
    $html = $component->render();
    echo "Render successful with no conversation\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Check if conversation property works
try {
    $component = new \App\Livewire\Inbox\CustomerInfoPanel();
    $component->conversationId = 1; // Assuming conversation 1 exists
    $conv = $component->conversation;
    echo "Conversation property works: " . ($conv ? "Found" : "Not found") . "\n";
} catch (Exception $e) {
    echo "Error accessing conversation: " . $e->getMessage() . "\n";
}
