<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class ExportSafeCatalogSync extends Command
{
    protected $signature = 'catalog:export-safe
        {--path= : Output JSON file path}
        {--ids=* : Optional product IDs to export}
    ';

    protected $description = 'Export products, images, and variants for a safe upsert-only catalog sync package.';

    public function handle(): int
    {
        $ids = collect((array) $this->option('ids'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $productsQuery = Product::query()
            ->with([
                'images' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'variants' => fn ($query) => $query->orderBy('id'),
            ])
            ->orderBy('id');

        if ($ids !== []) {
            $productsQuery->whereIn('id', $ids);
        }

        $products = $productsQuery->get();

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'source' => config('app.url') ?: 'local',
            'products' => $products->map(function (Product $product): array {
                return [
                    'product' => $product->getAttributes(),
                    'images' => $product->images->map(fn (ProductImage $image) => $this->normalizeRow(
                        $image->getAttributes(),
                        ['id']
                    ))->values()->all(),
                    'variants' => $product->variants->map(fn (ProductVariant $variant) => $variant->getAttributes())->values()->all(),
                ];
            })->values()->all(),
        ];

        $path = $this->option('path') ?: storage_path('app/catalog-sync/catalog-sync-'.now()->format('Ymd_His').'.json');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            $this->error("Unable to create export directory: {$directory}");

            return self::FAILURE;
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->error('Failed to encode catalog payload as JSON.');

            return self::FAILURE;
        }

        file_put_contents($path, $json);

        $this->info(sprintf(
            'Exported %d product(s), %d image(s), %d variant(s) to %s',
            $products->count(),
            $products->sum(fn (Product $product) => $product->images->count()),
            $products->sum(fn (Product $product) => $product->variants->count()),
            $path
        ));

        return self::SUCCESS;
    }

    private function normalizeRow(array $row, array $removeKeys = []): array
    {
        foreach (array_merge(['id'], $removeKeys) as $key) {
            unset($row[$key]);
        }

        return $row;
    }
}
