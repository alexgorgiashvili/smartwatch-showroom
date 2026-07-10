<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class ExportMymarketBatch extends Command
{
    protected $signature = 'mymarket:export-batch
        {--preset=initial-5-models : Config preset name from config/mymarket.php}
        {--path= : Output JSON file path}
        {--csv-path= : Output CSV file path}
    ';

    protected $description = 'Export a MyMarket-ready batch payload from configured slugs and live product data.';

    public function handle(): int
    {
        $presetName = (string) $this->option('preset');
        $preset = config("mymarket.presets.{$presetName}");

        if (! is_array($preset) || ! isset($preset['models']) || ! is_array($preset['models'])) {
            $this->error("Unknown MyMarket preset: {$presetName}");

            return self::FAILURE;
        }

        $models = collect($preset['models'])
            ->filter(fn ($model) => is_array($model) && filled($model['slug'] ?? null))
            ->values();

        if ($models->isEmpty()) {
            $this->error("Preset {$presetName} does not contain any exportable models.");

            return self::FAILURE;
        }

        $products = Product::query()
            ->with([
                'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
                'variants' => fn ($query) => $query->orderBy('id'),
            ])
            ->whereIn('slug', $models->pluck('slug')->all())
            ->get()
            ->keyBy('slug');

        $missingSlugs = $models->pluck('slug')
            ->reject(fn (string $slug) => $products->has($slug))
            ->values();

        if ($missingSlugs->isNotEmpty()) {
            $this->error('Missing products for slugs: '.$missingSlugs->join(', '));

            return self::FAILURE;
        }

        $payload = [
            'batch_id' => $preset['batch_id'] ?? $presetName,
            'generated_at' => now()->toIso8601String(),
            'generated_from' => config('app.url') ?: 'local',
            'preset' => $presetName,
            'source_policy' => [
                'primary_source_of_truth' => 'Production site/admin and current live MyMarket form state',
                'repo_role' => 'Fallback only when live values are temporarily unavailable',
                'must_recheck_live_before_publish' => [
                    'price',
                    'sale_price',
                    'variant stock',
                    'active colors',
                    'photo selection order',
                ],
            ],
            'listing_defaults' => config('mymarket.listing_defaults', []),
            'models' => $models->map(function (array $model) use ($products): array {
                /** @var Product $product */
                $product = $products->get($model['slug']);

                $variants = $product->variants->map(function (ProductVariant $variant): array {
                    return [
                        'color_ka' => $variant->color_name ?: $variant->name,
                        'stock' => $variant->available_quantity,
                        'include' => $variant->available_quantity > 0,
                    ];
                })->values();

                $firstImage = $product->images->first();

                return array_filter([
                    'sequence' => $model['sequence'] ?? null,
                    'model_code' => $model['model_code'] ?? null,
                    'slug' => $product->slug,
                    'name_ka' => $product->name_ka,
                    'name_en' => $product->name_en,
                    'positioning_angle_ka' => $model['positioning_angle_ka'] ?? null,
                    'price_gel' => $this->normalizeNumber($product->price),
                    'sale_price_gel' => $this->normalizeNullableNumber($product->sale_price),
                    'discount_expected' => (bool) ($model['discount_expected'] ?? false),
                    'discount_target_price_gel' => isset($model['discount_target_price_gel'])
                        ? $this->normalizeNumber($model['discount_target_price_gel'])
                        : null,
                    'warranty_months' => $product->warranty_months,
                    'operating_system' => $product->operating_system,
                    'screen_size' => $product->screen_size,
                    'display_type' => $product->display_type,
                    'screen_resolution' => $product->screen_resolution,
                    'battery_capacity_mah' => $product->battery_capacity_mah,
                    'battery_life_hours' => $product->battery_life_hours,
                    'battery_life_range' => $product->battery_life_range,
                    'water_resistant' => $product->water_resistant,
                    'camera_raw' => $product->camera,
                    'camera_if_mandatory' => $model['camera_if_mandatory'] ?? null,
                    'memory_card_if_mandatory' => config('mymarket.attribute_defaults.memory_card_if_mandatory'),
                    'short_description_ka' => $product->short_description_ka,
                    'functions_ka' => array_values(array_filter((array) $product->functions)),
                    'variants' => $variants->all(),
                    'image_count' => $product->images->count(),
                    'primary_image_url' => $firstImage?->thumbnail_url,
                    'must_emphasize_ka' => array_values(array_filter((array) ($model['must_emphasize_ka'] ?? []))),
                    'must_avoid_ka' => array_values(array_filter((array) ($model['must_avoid_ka'] ?? []))),
                    'user_note_possible_live_discount' => $model['user_note_possible_live_discount'] ?? null,
                ], fn ($value) => $value !== null);
            })->values()->all(),
        ];

        $jsonPath = $this->option('path') ?: base_path("docs/mymarket-export-{$presetName}.json");
        $csvPath = $this->option('csv-path') ?: base_path("docs/mymarket-export-{$presetName}.csv");

        $this->writeJson($jsonPath, $payload);
        $this->writeCsv($csvPath, $payload['models']);

        $this->info(sprintf(
            'Exported MyMarket batch "%s" with %d model(s) to %s and %s',
            $payload['batch_id'],
            count($payload['models']),
            $jsonPath,
            $csvPath
        ));

        return self::SUCCESS;
    }

    private function writeJson(string $path, array $payload): void
    {
        $this->ensureDirectory(dirname($path));

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode MyMarket batch payload as JSON.');
        }

        file_put_contents($path, $json);
    }

    /**
     * @param  array<int, array<string, mixed>>  $models
     */
    private function writeCsv(string $path, array $models): void
    {
        $this->ensureDirectory(dirname($path));

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open CSV path for writing: {$path}");
        }

        $headers = [
            'sequence',
            'model_code',
            'slug',
            'name_ka',
            'positioning_angle_ka',
            'price_gel',
            'sale_price_gel',
            'discount_expected',
            'discount_target_price_gel',
            'warranty_months',
            'operating_system',
            'screen_size',
            'display_type',
            'screen_resolution',
            'battery_capacity_mah',
            'battery_life_hours',
            'battery_life_range',
            'water_resistant',
            'camera_raw',
            'camera_if_mandatory',
            'memory_card_if_mandatory',
            'short_description_ka',
            'image_count',
            'primary_image_url',
            'in_stock_colors_ka',
            'out_of_stock_colors_ka',
            'must_emphasize_ka',
            'must_avoid_ka',
            'notes',
        ];

        fputcsv($handle, $headers);

        foreach ($models as $model) {
            $variants = collect((array) ($model['variants'] ?? []));
            $inStockColors = $variants->filter(fn (array $variant) => (bool) ($variant['include'] ?? false))
                ->pluck('color_ka')
                ->filter()
                ->implode('|');
            $outOfStockColors = $variants->reject(fn (array $variant) => (bool) ($variant['include'] ?? false))
                ->pluck('color_ka')
                ->filter()
                ->implode('|');

            fputcsv($handle, [
                $model['sequence'] ?? '',
                $model['model_code'] ?? '',
                $model['slug'] ?? '',
                $model['name_ka'] ?? '',
                $model['positioning_angle_ka'] ?? '',
                $model['price_gel'] ?? '',
                $model['sale_price_gel'] ?? '',
                $this->csvBool($model['discount_expected'] ?? false),
                $model['discount_target_price_gel'] ?? '',
                $model['warranty_months'] ?? '',
                $model['operating_system'] ?? '',
                $model['screen_size'] ?? '',
                $model['display_type'] ?? '',
                $model['screen_resolution'] ?? '',
                $model['battery_capacity_mah'] ?? '',
                $model['battery_life_hours'] ?? '',
                $model['battery_life_range'] ?? '',
                $model['water_resistant'] ?? '',
                $model['camera_raw'] ?? '',
                $model['camera_if_mandatory'] ?? '',
                $model['memory_card_if_mandatory'] ?? '',
                $model['short_description_ka'] ?? '',
                $model['image_count'] ?? '',
                $model['primary_image_url'] ?? '',
                $inStockColors,
                $outOfStockColors,
                implode('|', (array) ($model['must_emphasize_ka'] ?? [])),
                implode('|', (array) ($model['must_avoid_ka'] ?? [])),
                $model['user_note_possible_live_discount'] ?? '',
            ]);
        }

        fclose($handle);
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create directory: {$directory}");
        }
    }

    private function normalizeNumber(mixed $value): float|int
    {
        return (float) $value;
    }

    private function normalizeNullableNumber(mixed $value): float|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function csvBool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
