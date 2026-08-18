<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ReadyGiftBox;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AuditReadyGiftBoxes extends Command
{
    protected $signature = 'gift-boxes:audit';

    protected $description = 'Read-only readiness audit for products used in ready gift boxes';

    public function handle(): int
    {
        try {
            $rows = $this->catalogRows();
        } catch (\Throwable $exception) {
            $this->error('Catalog audit could not connect to the database: '.$exception->getMessage());

            return self::FAILURE;
        }
        if ($rows->isEmpty()) {
            $this->warn('No ready gift boxes were found in the database or legacy configuration.');

            return self::FAILURE;
        }

        $issues = [];
        foreach ($rows as $row) {
            $boxSlug = $row['box_slug'];
            $productSlug = $row['product_slug'];
            $role = $row['role'];
            $product = Product::query()->with(['primaryImage', 'variants'])->where('slug', $productSlug)->first();

            if (! $product) {
                $issues[] = [$boxSlug, $productSlug, 'missing_product', 'Product does not exist.'];

                continue;
            }
            $product->variants->each(fn ($variant) => $variant->setRelation('product', $product));

            $names = trim((string) $product->name_ka).' '.trim((string) $product->name_en);
            if (str_contains(mb_strtoupper($names), '[TEST]')) {
                $issues[] = [$boxSlug, $productSlug, 'test_name', 'Product name still contains [TEST].'];
            }

            $imagePath = trim((string) $product->primaryImage?->path);
            if ($imagePath === '') {
                $issues[] = [$boxSlug, $productSlug, 'missing_image', 'Primary image is missing.'];
            } elseif (preg_match('/placeholder|default|smart-watch3/i', $imagePath)) {
                $issues[] = [$boxSlug, $productSlug, 'placeholder_image', 'Primary image appears to be a placeholder.'];
            } elseif (preg_match('/^https?:\/\//i', $imagePath)) {
                $issues[] = [$boxSlug, $productSlug, 'remote_image_manual_check', 'Remote primary image requires a manual availability check.'];
            } else {
                $normalizedImagePath = ltrim($imagePath, '/');
                if (str_starts_with($normalizedImagePath, 'storage/')) {
                    $normalizedImagePath = substr($normalizedImagePath, 8);
                }

                if ($normalizedImagePath === '' || ! Storage::disk('public')->exists($normalizedImagePath)) {
                    $issues[] = [$boxSlug, $productSlug, 'missing_image_file', 'Primary image file is missing from public storage.'];
                }
            }

            if ((float) ($product->sale_price ?? $product->price ?? 0) <= 0) {
                $issues[] = [$boxSlug, $productSlug, 'invalid_price', 'Price must be positive.'];
            }

            if (! $product->variants->contains(fn ($variant): bool => $variant->canFulfillQuantity(1))) {
                $issues[] = [$boxSlug, $productSlug, 'out_of_stock', 'No variant is in stock.'];
            }

            $allowedRoles = $role === 'main' ? ['main', 'both'] : ['addon', 'both'];
            if (! $product->is_active || ! $product->gift_builder_enabled || $product->fulfillment_mode !== 'local_stock' || ! in_array($product->gift_builder_role, $allowedRoles, true)) {
                $issues[] = [$boxSlug, $productSlug, 'ineligible', "Product is not eligible for role {$role}."];
            }
        }

        if ($issues === []) {
            $this->info('Ready gift box catalog audit passed.');

            return self::SUCCESS;
        }

        $this->table(['Box', 'Product', 'Code', 'Issue'], $issues);
        $this->error(count($issues).' readiness issue(s) found. No data was changed.');

        return self::FAILURE;
    }

    /** @return Collection<int, array{box_slug: string, product_slug: string, role: string}> */
    private function catalogRows(): Collection
    {
        if (Schema::hasTable('ready_gift_boxes') && Schema::hasTable('ready_gift_box_items')) {
            $boxes = ReadyGiftBox::query()->with('items.product')->get();
            if ($boxes->isNotEmpty()) {
                return $boxes->flatMap(fn (ReadyGiftBox $box) => $box->items->map(fn ($item): array => [
                    'box_slug' => $box->slug,
                    'product_slug' => (string) $item->product?->slug,
                    'role' => $item->role,
                ]))->values();
            }
        }

        return collect((array) config('ready_gift_boxes_legacy', []))
            ->flatMap(function (array $box, string $slug): array {
                $rows = [[
                    'box_slug' => $slug,
                    'product_slug' => (string) ($box['main_product'] ?? ''),
                    'role' => 'main',
                ]];

                foreach ((array) ($box['addon_products'] ?? []) as $productSlug) {
                    $rows[] = [
                        'box_slug' => $slug,
                        'product_slug' => (string) $productSlug,
                        'role' => 'addon',
                    ];
                }

                return $rows;
            })
            ->values();
    }
}
