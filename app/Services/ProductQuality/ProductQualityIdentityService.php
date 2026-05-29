<?php

namespace App\Services\ProductQuality;

use App\Models\Product;

class ProductQualityIdentityService
{
    public function buildForCatalogProduct(Product $product, array $input = []): array
    {
        $sourceUrl = $this->nullableString($input['source_url'] ?? $product->external_source_url);
        $externalSource = $this->nullableString($input['external_source'] ?? $product->external_source ?? $this->inferExternalSource($sourceUrl));
        $externalProductId = $this->nullableString($input['external_product_id'] ?? $product->external_product_id);

        return [
            'product_id' => $product->id,
            'mode' => 'catalog',
            'source_url' => $sourceUrl,
            'external_source' => $externalSource,
            'external_product_id' => $externalProductId,
            'brand' => $this->nullableString($input['brand'] ?? $product->brand),
            'model' => $this->nullableString($input['model'] ?? $product->model),
            'name' => $this->nullableString($input['name'] ?? $product->name_en ?? $product->name),
            'identity_payload' => [
                'product_snapshot' => [
                    'id' => $product->id,
                    'name_en' => $product->name_en,
                    'name_ka' => $product->name_ka,
                    'brand' => $product->brand,
                    'model' => $product->model,
                    'external_source' => $product->external_source,
                    'external_source_url' => $product->external_source_url,
                    'external_product_id' => $product->external_product_id,
                ],
                'research_input' => $this->buildResearchInput($input, $sourceUrl, $externalSource, $externalProductId),
            ],
        ];
    }

    public function buildForAdHoc(array $input = []): array
    {
        $sourceUrl = $this->nullableString($input['source_url'] ?? null);
        $externalSource = $this->nullableString($input['external_source'] ?? $this->inferExternalSource($sourceUrl));
        $externalProductId = $this->nullableString($input['external_product_id'] ?? null);

        return [
            'product_id' => null,
            'mode' => 'ad_hoc',
            'source_url' => $sourceUrl,
            'external_source' => $externalSource,
            'external_product_id' => $externalProductId,
            'brand' => $this->nullableString($input['brand'] ?? null),
            'model' => $this->nullableString($input['model'] ?? null),
            'name' => $this->nullableString($input['name'] ?? trim(implode(' ', array_filter([
                $input['brand'] ?? null,
                $input['model'] ?? null,
            ])))),
            'identity_payload' => [
                'product_snapshot' => null,
                'research_input' => $this->buildResearchInput($input, $sourceUrl, $externalSource, $externalProductId),
            ],
        ];
    }

    public function inferExternalSource(?string $sourceUrl): ?string
    {
        if (!$sourceUrl) {
            return null;
        }

        $host = strtolower((string) parse_url($sourceUrl, PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'alibaba.') => 'alibaba',
            str_contains($host, 'amazon.') => 'amazon',
            str_contains($host, 'ebay.') => 'ebay',
            default => 'web',
        };
    }

    private function buildResearchInput(array $input, ?string $sourceUrl, ?string $externalSource, ?string $externalProductId): array
    {
        return array_filter([
            'source_url' => $sourceUrl,
            'external_source' => $externalSource,
            'external_product_id' => $externalProductId,
            'apify_json' => $this->nullableString($input['apify_json'] ?? null),
            'manual_evidence_input' => $this->nullableString($input['manual_evidence_input'] ?? null),
        ], static fn ($value) => $value !== null);
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
