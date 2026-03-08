<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find products with suspiciously low prices
$products = \App\Models\Product::where('price', '<', 1.00)->get(['id', 'name_en', 'slug', 'price', 'sale_price', 'stock_quantity']);

echo "=== Products with price < 1.00 ===\n";
foreach ($products as $p) {
    echo "ID:{$p->id} | {$p->name_en} | slug:{$p->slug} | price:{$p->price} | sale:{$p->sale_price} | stock:{$p->stock_quantity}\n";
}

echo "\n=== Products matching Q19 ===\n";
$q19 = \App\Models\Product::where('name_en', 'LIKE', '%Q19%')
    ->orWhere('slug', 'LIKE', '%q19%')
    ->get(['id', 'name_en', 'slug', 'price', 'sale_price', 'stock_quantity']);
foreach ($q19 as $p) {
    echo "ID:{$p->id} | {$p->name_en} | slug:{$p->slug} | price:{$p->price} | sale:{$p->sale_price} | stock:{$p->stock_quantity}\n";
}
if ($q19->isEmpty()) {
    echo "(none found)\n";
}

echo "\n=== ALL products (name + price) ===\n";
$all = \App\Models\Product::orderBy('price', 'asc')->get(['id', 'name_en', 'slug', 'price', 'sale_price']);
foreach ($all as $p) {
    echo "  {$p->id}. {$p->name_en} | price:{$p->price} | sale:{$p->sale_price}\n";
}
