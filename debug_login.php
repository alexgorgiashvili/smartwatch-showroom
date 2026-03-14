<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test the login page
$request = Illuminate\Http\Request::create('/admin/login', 'GET');

$response = $app->handle($request);

$content = $response->getContent();

// Look for scrollTo usage
if (strpos($content, 'scrollTo') !== false) {
    echo "Found scrollTo in content\n";
    
    // Extract lines around scrollTo
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        if (strpos($line, 'scrollTo') !== false) {
            echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
            // Show context
            for ($j = max(0, $i - 2); $j < min(count($lines), $i + 3); $j++) {
                echo "  " . ($j + 1) . ": " . trim($lines[$j]) . "\n";
            }
            echo "\n";
        }
    }
} else {
    echo "No scrollTo found in content\n";
}

// Check for any error elements
if (strpos($content, 'data-validation-error') !== false) {
    echo "Found validation error elements\n";
}
?>
