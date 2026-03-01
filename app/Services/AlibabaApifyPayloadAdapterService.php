<?php

namespace App\Services;

use Illuminate\Support\Str;

class AlibabaApifyPayloadAdapterService
{
    public function adaptFromJson(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Invalid Apify JSON payload.');
        }

        return $this->adapt($decoded);
    }

    public function adapt(array $payload): array
    {
        $item = $this->extractItem($payload);

        $title = $this->firstString($item, [
            'title',
            'pageTitle',
            'name',
            'productTitle',
            'productName',
            'h1',
            'first_h2',
            'product.title',
            'product.name',
        ]) ?? 'Smart Watch';

        $description = $this->firstString($item, [
            'description',
            'random_text_from_the_page',
            'productDescription',
            'summary',
            'product.description',
        ]) ?? '';

        $sourceUrl = $this->firstString($item, [
            'url',
            'productUrl',
            'product.url',
            'productLink',
            'link',
            'sourceUrl',
        ]);

        $sourceProductId = $this->extractSourceProductId($item, $sourceUrl);

        $images = $this->extractImages($item);
        $variants = $this->extractVariants($item);

        $price = $this->extractPrice($item);

        $specs = $this->extractSpecs($item);

        return [
            'source_url' => $sourceUrl,
            'source_product_id' => $sourceProductId,
            'title' => trim($title),
            'description' => trim($description),
            'price_min' => $price['min'],
            'price_max' => $price['max'],
            'currency' => $price['currency'] ?? 'USD',
            'specs' => $specs,
            'brand' => $this->nullableString($specs['brand'] ?? null),
            'model' => $this->nullableString($specs['model'] ?? null),
            'memory_size' => $this->nullableString($specs['memory_size'] ?? null),
            'operating_system' => $this->nullableString($specs['operating_system'] ?? null),
            'screen_size' => $this->nullableString($specs['screen_size'] ?? null),
            'display_type' => $this->nullableString($specs['display_type'] ?? null),
            'screen_resolution' => $this->nullableString($specs['screen_resolution'] ?? null),
            'battery_capacity_mah' => $this->nullableInt($specs['battery_capacity_mah'] ?? null),
            'charging_time_hours' => $this->nullableFloat($specs['charging_time_hours'] ?? null),
            'case_material' => $this->nullableString($specs['case_material'] ?? null),
            'band_material' => $this->nullableString($specs['band_material'] ?? null),
            'camera' => $this->nullableString($specs['camera'] ?? null),
            'functions' => $this->extractFunctions($item, $specs),
            'images' => $images,
            'variants' => $variants,
        ];
    }

    private function extractItem(array $payload): array
    {
        if (isset($payload['item']) && is_array($payload['item'])) {
            return $payload['item'];
        }

        if (isset($payload['items']) && is_array($payload['items']) && isset($payload['items'][0]) && is_array($payload['items'][0])) {
            return $payload['items'][0];
        }

        if (isset($payload[0]) && is_array($payload[0])) {
            return $payload[0];
        }

        return $payload;
    }

    private function extractSourceProductId(array $item, ?string $sourceUrl): ?string
    {
        $id = $this->firstString($item, [
            'productId',
            'product_id',
            'id',
            'itemId',
            'offerId',
            'product.id',
        ]);

        if ($id !== null) {
            return Str::limit(trim($id), 120, '');
        }

        if (!$sourceUrl) {
            return null;
        }

        if (preg_match('/(?:product-detail|product)\/[^\/_]+_([0-9]{6,})\.html/i', $sourceUrl, $match) === 1) {
            return $match[1];
        }

        if (preg_match('/[?&](?:id|productId)=([0-9]{6,})/i', $sourceUrl, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private function extractImages(array $item): array
    {
        $candidates = [
            data_get($item, 'images', []),
            data_get($item, 'imageUrls', []),
            data_get($item, 'productImages', []),
            data_get($item, 'media.images', []),
            data_get($item, 'gallery', []),
        ];

        $urls = [];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            foreach ($candidate as $entry) {
                if (is_string($entry)) {
                    $urls[] = trim($entry);
                    continue;
                }

                if (!is_array($entry)) {
                    continue;
                }

                foreach (['url', 'src', 'image', 'original', 'large', 'big', 'origin'] as $key) {
                    $value = data_get($entry, $key);
                    if (is_string($value) && trim($value) !== '') {
                        $urls[] = trim($value);
                    }
                }
            }
        }

        $mainImage = $this->firstString($item, ['mainImage', 'image', 'thumbnail', 'productImage']);
        if ($mainImage !== null) {
            $urls[] = $mainImage;
        }

        $valid = array_values(array_filter(array_unique($urls), static function ($url) {
            return is_string($url) && str_starts_with($url, 'http');
        }));

        return array_slice($valid, 0, 12);
    }

    private function extractVariants(array $item): array
    {
        $variantSources = [
            data_get($item, 'variants', []),
            data_get($item, 'options', []),
            data_get($item, 'skuVariants', []),
            data_get($item, 'product.variants', []),
        ];

        $variants = [];

        foreach ($variantSources as $source) {
            if (!is_array($source)) {
                continue;
            }

            foreach ($source as $variant) {
                if (is_string($variant)) {
                    $name = trim($variant);
                    if ($name !== '') {
                        $variants[] = [
                            'name' => Str::limit($name, 160, ''),
                            'color_name' => null,
                            'color_hex' => null,
                            'quantity' => 0,
                            'low_stock_threshold' => 5,
                        ];
                    }

                    continue;
                }

                if (!is_array($variant)) {
                    continue;
                }

                $name = $this->firstString($variant, ['name', 'label', 'value', 'title', 'skuName']);
                if ($name === null || trim($name) === '') {
                    continue;
                }

                $variants[] = [
                    'name' => Str::limit(trim($name), 160, ''),
                    'color_name' => $this->nullableString(data_get($variant, 'color_name') ?? data_get($variant, 'color') ?? null),
                    'color_hex' => $this->nullableColorHex(data_get($variant, 'color_hex') ?? data_get($variant, 'hex') ?? null),
                    'quantity' => max(0, (int) data_get($variant, 'quantity', 0)),
                    'low_stock_threshold' => max(0, (int) data_get($variant, 'low_stock_threshold', 5)),
                ];
            }
        }

        if ($variants === []) {
            $fallback = $this->firstString($item, ['color', 'colour', 'defaultVariant']);
            if ($fallback !== null && trim($fallback) !== '') {
                $variants[] = [
                    'name' => Str::limit(trim($fallback), 160, ''),
                    'color_name' => null,
                    'color_hex' => null,
                    'quantity' => 0,
                    'low_stock_threshold' => 5,
                ];
            }
        }

        if ($variants === []) {
            $variants[] = [
                'name' => 'Default',
                'color_name' => null,
                'color_hex' => null,
                'quantity' => 0,
                'low_stock_threshold' => 5,
            ];
        }

        $unique = [];
        foreach ($variants as $variant) {
            $key = strtolower($variant['name']);
            $unique[$key] = $variant;
        }

        return array_values(array_slice($unique, 0, 30));
    }

    private function extractPrice(array $item): array
    {
        $currency = strtoupper(substr((string) ($this->firstString($item, [
            'currency',
            'price.currency',
            'priceCurrency',
            'pricing.currency',
        ]) ?? 'USD'), 0, 3));

        $min = $this->nullableFloat(
            data_get($item, 'price_min')
            ?? data_get($item, 'minPrice')
            ?? data_get($item, 'price.min')
            ?? data_get($item, 'priceMin')
            ?? data_get($item, 'price')
            ?? data_get($item, 'offerPrice')
        );

        $max = $this->nullableFloat(
            data_get($item, 'price_max')
            ?? data_get($item, 'maxPrice')
            ?? data_get($item, 'price.max')
            ?? data_get($item, 'priceMax')
        );

        if ($min === null) {
            $priceText = $this->firstString($item, ['priceText', 'price_range', 'priceRange']);
            if ($priceText !== null && preg_match_all('/[0-9]+(?:\.[0-9]{1,2})?/', $priceText, $matches) > 0) {
                $numbers = array_map('floatval', $matches[0]);
                sort($numbers);
                $min = $numbers[0] ?? null;
                $max = $numbers[count($numbers) - 1] ?? null;
            }
        }

        return [
            'min' => $min,
            'max' => $max,
            'currency' => $currency ?: 'USD',
        ];
    }

    private function extractSpecs(array $item): array
    {
        $specs = [];

        $specSources = [
            data_get($item, 'specs', []),
            data_get($item, 'specifications', []),
            data_get($item, 'attributes', []),
            data_get($item, 'details.specs', []),
        ];

        foreach ($specSources as $source) {
            if (is_array($source)) {
                foreach ($source as $key => $value) {
                    if (is_string($key)) {
                        $specs[strtolower($key)] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                        continue;
                    }

                    if (!is_array($value)) {
                        continue;
                    }

                    $name = $this->firstString($value, ['name', 'label', 'key']);
                    $val = $this->firstString($value, ['value', 'text', 'content']);

                    if ($name !== null && $val !== null) {
                        $specs[strtolower(trim($name))] = $val;
                    }
                }
            }
        }

        $directMap = [
            'operating_system' => ['operating_system', 'os', 'operationSystem'],
            'screen_size' => ['screen_size', 'screenSize', 'display_size'],
            'display_type' => ['display_type', 'displayType', 'screen_type'],
            'screen_resolution' => ['screen_resolution', 'resolution', 'display_resolution'],
            'battery_capacity_mah' => ['battery_capacity_mah', 'batteryCapacity', 'battery'],
            'charging_time_hours' => ['charging_time_hours', 'chargingTime', 'charge_time'],
            'case_material' => ['case_material', 'caseMaterial', 'watch_case_material'],
            'band_material' => ['band_material', 'strapMaterial', 'bandMaterial'],
            'camera' => ['camera', 'cameraSpec'],
            'brand' => ['brand', 'manufacturer', 'productBrand'],
            'model' => ['model', 'modelNumber', 'productModel'],
            'memory_size' => ['memory_size', 'memorySize', 'ram', 'rom', 'memory'],
        ];

        foreach ($directMap as $key => $aliases) {
            foreach ($aliases as $alias) {
                $candidate = data_get($item, $alias);
                if ($candidate !== null && $candidate !== '') {
                    $specs[$key] = (string) $candidate;
                    break;
                }

                if (isset($specs[strtolower($alias)])) {
                    $specs[$key] = (string) $specs[strtolower($alias)];
                    break;
                }
            }
        }

        return $specs;
    }

    private function extractFunctions(array $item, array $specs): array
    {
        $functions = data_get($item, 'functions');

        if (is_array($functions)) {
            return array_values(array_slice(array_unique(array_values(array_filter(array_map(static function ($entry) {
                return is_string($entry) ? trim($entry) : null;
            }, $functions)))), 0, 30));
        }

        if (is_string($functions) && trim($functions) !== '') {
            return array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $functions) ?: [])));
        }

        foreach ($specs as $key => $value) {
            if (str_contains($key, 'function') && is_string($value) && trim($value) !== '') {
                return array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $value) ?: [])));
            }
        }

        return [];
    }

    private function firstString(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            if (preg_match('/[0-9]+(?:\.[0-9]{1,2})?/', $value, $match) !== 1) {
                return null;
            }

            return (float) $match[0];
        }

        return (float) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && preg_match('/[0-9]+/', $value, $match) === 1) {
            return (int) $match[0];
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : Str::limit($text, 255, '');
    }

    private function nullableColorHex(mixed $value): ?string
    {
        $hex = strtoupper(trim((string) ($value ?? '')));
        if ($hex === '') {
            return null;
        }

        return preg_match('/^#[0-9A-F]{6}$/', $hex) === 1 ? $hex : null;
    }
}
