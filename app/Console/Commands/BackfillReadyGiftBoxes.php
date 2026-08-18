<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ReadyGiftBox;
use App\Services\GiftBuilder\ReadyGiftBoxManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BackfillReadyGiftBoxes extends Command
{
    protected $signature = 'gift-boxes:backfill
                            {--strict : Roll back if any mapped product is missing or ineligible (default behavior)}
                            {--force : Replace existing boxes with the same slugs}';

    protected $description = 'Idempotently import the legacy configured ready gift boxes into the database';

    public function handle(ReadyGiftBoxManager $manager): int
    {
        if (! Schema::hasTable('ready_gift_boxes') || ! Schema::hasTable('ready_gift_box_items')) {
            $this->error('Run the ready gift box migration first.');

            return self::FAILURE;
        }

        $legacyBoxes = (array) config('ready_gift_boxes_legacy', []);
        if ($legacyBoxes === []) {
            $this->warn('No legacy ready boxes are configured.');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        try {
            DB::transaction(function () use ($legacyBoxes, $manager, &$created, &$updated, &$skipped): void {
                foreach ($legacyBoxes as $slugKey => $boxConfig) {
                    $slug = (string) $slugKey;
                    $existing = ReadyGiftBox::query()->where('slug', $slug)->first();
                    if ($existing && ! $this->option('force')) {
                        $this->line("SKIP {$slug}: already exists.");
                        $skipped++;

                        continue;
                    }

                    $main = $this->strictProduct((string) ($boxConfig['main_product'] ?? ''), 'main');
                    $addons = collect((array) ($boxConfig['addon_products'] ?? []))
                        ->map(fn ($productSlug) => $this->strictProduct((string) $productSlug, 'addon'))
                        ->values();

                    if ($addons->count() > max(0, (int) config('gift_builder.max_items', 4) - 1)) {
                        throw new RuntimeException("{$slug}: too many add-on products.");
                    }

                    $data = [
                        'slug' => $slug,
                        'title_ka' => $boxConfig['title_ka'] ?? $boxConfig['title_en'] ?? $slug,
                        'title_en' => $boxConfig['title_en'] ?? null,
                        'short_description_ka' => $boxConfig['description_ka'] ?? null,
                        'short_description_en' => $boxConfig['description_en'] ?? null,
                        'badge_ka' => null,
                        'badge_en' => null,
                        'cover_image_path' => null,
                        'theme_key' => $boxConfig['theme_key'] ?? 'grape',
                        'packaging_slug' => $boxConfig['packaging_slug'] ?? 'standard',
                        'discount_type' => 'none',
                        'discount_value' => 0,
                        'is_active' => true,
                        'is_featured' => true,
                        'sort_order' => (int) ($boxConfig['sort_order'] ?? 0),
                        'main_product_id' => (int) $main->id,
                        'main_default_variant_id' => (int) $this->firstAvailableVariantId($main, $slug),
                        'addons' => $addons->map(fn (Product $product): array => [
                            'product_id' => (int) $product->id,
                            'default_variant_id' => (int) $this->firstAvailableVariantId($product, $slug),
                        ])->all(),
                    ];

                    if ($existing) {
                        $manager->update($existing, $data);
                        $updated++;
                        $this->info("UPDATE {$slug}");
                    } else {
                        $manager->create($data);
                        $created++;
                        $this->info("CREATE {$slug}");
                    }
                }
            });
        } catch (\Throwable $exception) {
            $this->error('Backfill rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Ready gift box backfill complete: {$created} created, {$updated} updated, {$skipped} skipped.");

        return self::SUCCESS;
    }

    private function strictProduct(string $slug, string $role): Product
    {
        $product = Product::query()->with('variants')->where('slug', $slug)->first();
        if (! $product) {
            throw new RuntimeException("Missing product: {$slug}.");
        }
        $product->variants->each(fn ($variant) => $variant->setRelation('product', $product));

        $allowedRoles = $role === 'main' ? ['main', 'both'] : ['addon', 'both'];
        if (! $product->is_active || ! $product->gift_builder_enabled || $product->fulfillment_mode !== 'local_stock' || ! in_array($product->gift_builder_role, $allowedRoles, true)) {
            throw new RuntimeException("Ineligible {$role} product: {$slug}.");
        }

        if ((float) ($product->sale_price ?? $product->price ?? 0) <= 0) {
            throw new RuntimeException("Invalid product price: {$slug}.");
        }

        return $product;
    }

    private function firstAvailableVariantId(Product $product, string $boxSlug): int
    {
        $variant = $product->variants->first(fn ($item): bool => $item->canFulfillQuantity(1));
        if (! $variant) {
            throw new RuntimeException("{$boxSlug}: {$product->slug} has no in-stock variant.");
        }

        return (int) $variant->id;
    }
}
