<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "PHP Version: " . phpversion() . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] ?? 'Not set' . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] ?? 'Not set' . "\n";

try {
    require __DIR__.'/../vendor/autoload.php';
    $app = require_once __DIR__.'/../bootstrap/app.php';
    
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    echo "Laravel loaded successfully\n";
    
    // Test Filament
    if (class_exists('Filament\Facades\Filament')) {
        echo "Filament class exists\n";
        $panel = \Filament\Facades\Filament::getPanel('admin');
        echo "Panel ID: " . $panel->getId() . "\n";
    } else {
        echo "Filament class not found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
