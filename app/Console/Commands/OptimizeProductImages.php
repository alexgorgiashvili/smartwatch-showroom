<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\Product\ProductImageProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OptimizeProductImages extends Command
{
    protected $signature = 'products:optimize-images {--apply : Write optimized files and update product image records} {--product= : Limit to a product slug}';

    protected $description = 'Prepare or apply WebP replacements for locally stored product images.';

    public function handle(ProductImageProcessor $processor): int
    {
        $manifest = null;
        if ($this->option('apply')) {
            $directory = storage_path('app/product-image-backups');
            if (! is_dir($directory) && ! mkdir($directory, 0750, true)) {
                $this->error('Could not create the image rollback directory.');
                return self::FAILURE;
            }
            $manifest = fopen($directory.'/'.now()->format('Ymd-His').'-'.uniqid().'.jsonl', 'wb');
            if ($manifest === false) {
                $this->error('Could not create the image rollback manifest.');
                return self::FAILURE;
            }
        }

        $query = ProductImage::query();
        if ($slug = $this->option('product')) {
            $query->whereHas('product', fn ($product) => $product->where('slug', $slug));
        }

        $eligible = 0;
        $done = 0;
        $errors = 0;

        foreach ($query->lazyById(100) as $image) {
            $path = ltrim((string) $image->path, '/');
            if (str_starts_with($path, 'storage/')) {
                $path = substr($path, 8);
            }
            if ($path === '' || str_contains($path, '://') || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            $absolutePath = Storage::disk('public')->path($path);
            $size = @getimagesize($absolutePath);
            if ($size === false) {
                $errors++;
                $this->warn("Unreadable image record {$image->id}");
                continue;
            }

            $optimized = str_ends_with(strtolower($path), '.webp')
                && max($size[0], $size[1]) <= 1200
                && Storage::disk('public')->size($path) <= 300 * 1024
                && (bool) $image->thumbnail_path;
            if ($optimized) {
                continue;
            }

            $eligible++;
            if (! $this->option('apply')) {
                continue;
            }

            try {
                [$main, $thumbnail] = $processor->storeFile($absolutePath);
                $entry = json_encode([
                    'id' => $image->id,
                    'old_path' => $image->path,
                    'old_thumbnail_path' => $image->thumbnail_path,
                    'new_path' => 'storage/'.$main,
                    'new_thumbnail_path' => 'storage/'.$thumbnail,
                ], JSON_UNESCAPED_SLASHES).PHP_EOL;
                if (fwrite($manifest, $entry) === false || ! fflush($manifest)) {
                    Storage::disk('public')->delete([$main, $thumbnail]);
                    throw new \RuntimeException('Could not write the rollback manifest.');
                }
                $image->update([
                    'path' => 'storage/'.$main,
                    'thumbnail_path' => 'storage/'.$thumbnail,
                ]);
                $done++;
            } catch (\Throwable $exception) {
                $errors++;
                $this->warn("Could not optimize image record {$image->id}: {$exception->getMessage()}");
            }
        }

        if ($manifest !== null) {
            fclose($manifest);
        }

        $this->info($this->option('apply')
            ? "Optimized {$done} image(s); {$errors} error(s). Existing source files remain available for rollback."
            : "{$eligible} image(s) eligible; {$errors} error(s). Use --apply to update them.");

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
