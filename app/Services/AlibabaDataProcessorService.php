<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AlibabaDataProcessorService
{
    public function process(array $raw): array
    {
        $aiResult = $this->processWithAi($raw);

        if ($aiResult !== null) {
            return $this->normalizeProcessedData($aiResult, $raw);
        }

        return $this->fallbackProcess($raw);
    }

    private function processWithAi(array $raw): ?array
    {
        $apiKey = config('ai.openai.api_key');
        $orgId = config('ai.openai.org_id');
        $model = config('ai.openai.model', 'gpt-4-turbo');

        if (!$apiKey) {
            return null;
        }

        $systemPrompt = 'You clean and structure e-commerce smartwatch data. Return valid JSON only.';
        $userPrompt = "Convert scraped Alibaba product data into JSON with shape: \n"
            . "{\n"
            . "  \"product\": {\n"
            . "    \"name_en\": string,\n"
            . "    \"name_ka\": string,\n"
            . "    \"slug\": string,\n"
            . "    \"short_description_en\": string|null,\n"
            . "    \"short_description_ka\": string|null,\n"
            . "    \"description_en\": string|null,\n"
            . "    \"description_ka\": string|null,\n"
            . "    \"price\": number|null,\n"
            . "    \"sale_price\": number|null,\n"
            . "    \"currency\": string,\n"
            . "    \"sim_support\": boolean,\n"
            . "    \"gps_features\": boolean,\n"
            . "    \"water_resistant\": string|null,\n"
            . "    \"battery_life_hours\": integer|null,\n"
            . "    \"warranty_months\": integer|null,\n"
            . "    \"is_active\": boolean,\n"
            . "    \"featured\": boolean\n"
            . "  },\n"
            . "  \"variants\": [{\"name\": string, \"quantity\": integer, \"low_stock_threshold\": integer}],\n"
            . "  \"images\": [string]\n"
            . "}\n"
            . "Rules: translate to Georgian for *_ka fields; keep concise descriptions; infer boolean specs from source; variants must be clean human-readable names."
            . "\nScraped input JSON:\n"
            . json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $request = Http::withHeaders(array_filter([
            'Authorization' => 'Bearer ' . $apiKey,
            'OpenAI-Organization' => $orgId,
            'Content-Type' => 'application/json',
        ]))->timeout(45);

        $response = $request->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            Log::warning('Alibaba AI processing failed', [
                'status' => $response->status(),
                'body' => Str::limit((string) $response->body(), 400),
            ]);
            return null;
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');
        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeProcessedData(array $data, array $raw): array
    {
        $product = (array) ($data['product'] ?? []);
        $nameEn = trim((string) ($product['name_en'] ?? $raw['title'] ?? 'Smart Watch'));
        $slug = trim((string) ($product['slug'] ?? Str::slug($nameEn)));

        $normalizedProduct = [
            'name_en' => $nameEn,
            'name_ka' => trim((string) ($product['name_ka'] ?? $nameEn)),
            'slug' => $slug !== '' ? $slug : Str::slug($nameEn),
            'short_description_en' => $this->nullableString($product['short_description_en'] ?? null),
            'short_description_ka' => $this->nullableString($product['short_description_ka'] ?? null),
            'description_en' => $this->nullableString($product['description_en'] ?? ($raw['description'] ?? null)),
            'description_ka' => $this->nullableString($product['description_ka'] ?? ($raw['description'] ?? null)),
            'price' => $this->nullableFloat($product['price'] ?? ($raw['price_min'] ?? null)),
            'sale_price' => $this->nullableFloat($product['sale_price'] ?? null),
            'currency' => strtoupper(substr((string) ($product['currency'] ?? ($raw['currency'] ?? 'USD')), 0, 3)),
            'sim_support' => (bool) ($product['sim_support'] ?? $this->guessBoolean($raw, ['sim'])),
            'gps_features' => (bool) ($product['gps_features'] ?? $this->guessBoolean($raw, ['gps', 'location'])),
            'water_resistant' => $this->nullableString($product['water_resistant'] ?? $this->guessWaterResistance($raw)),
            'battery_life_hours' => $this->nullableInt($product['battery_life_hours'] ?? $this->guessBatteryHours($raw)),
            'warranty_months' => $this->nullableInt($product['warranty_months'] ?? null),
            'operating_system' => $this->nullableString($raw['operating_system'] ?? null),
            'screen_size' => $this->nullableString($raw['screen_size'] ?? null),
            'display_type' => $this->nullableString($raw['display_type'] ?? null),
            'screen_resolution' => $this->nullableString($raw['screen_resolution'] ?? null),
            'battery_capacity_mah' => $this->nullableInt($raw['battery_capacity_mah'] ?? null),
            'charging_time_hours' => $this->nullableFloat($raw['charging_time_hours'] ?? null),
            'case_material' => $this->nullableString($raw['case_material'] ?? null),
            'band_material' => $this->nullableString($raw['band_material'] ?? null),
            'camera' => $this->nullableString($raw['camera'] ?? null),
            'functions' => $this->normalizeFunctions($raw['functions'] ?? []),
            'is_active' => (bool) ($product['is_active'] ?? true),
            'featured' => (bool) ($product['featured'] ?? false),
        ];

        return [
            'product' => $normalizedProduct,
            'variants' => $this->normalizeVariants($raw['variants'] ?? ($data['variants'] ?? [])),
            'images' => $this->normalizeImages($raw['images'] ?? ($data['images'] ?? [])),
            'source_url' => $raw['source_url'] ?? null,
        ];
    }

    private function fallbackProcess(array $raw): array
    {
        $title = trim((string) ($raw['title'] ?? 'Smart Watch'));

        return [
            'product' => [
                'name_en' => $title,
                'name_ka' => $title,
                'slug' => Str::slug($title),
                'short_description_en' => $this->nullableString($raw['description'] ?? null),
                'short_description_ka' => $this->nullableString($raw['description'] ?? null),
                'description_en' => $this->nullableString($raw['description'] ?? null),
                'description_ka' => $this->nullableString($raw['description'] ?? null),
                'price' => $this->nullableFloat($raw['price_min'] ?? null),
                'sale_price' => null,
                'currency' => strtoupper(substr((string) ($raw['currency'] ?? 'USD'), 0, 3)),
                'sim_support' => $this->guessBoolean($raw, ['sim']),
                'gps_features' => $this->guessBoolean($raw, ['gps', 'location']),
                'water_resistant' => $this->nullableString($this->guessWaterResistance($raw)),
                'battery_life_hours' => $this->nullableInt($this->guessBatteryHours($raw)),
                'warranty_months' => null,
                'operating_system' => $this->nullableString($raw['operating_system'] ?? null),
                'screen_size' => $this->nullableString($raw['screen_size'] ?? null),
                'display_type' => $this->nullableString($raw['display_type'] ?? null),
                'screen_resolution' => $this->nullableString($raw['screen_resolution'] ?? null),
                'battery_capacity_mah' => $this->nullableInt($raw['battery_capacity_mah'] ?? null),
                'charging_time_hours' => $this->nullableFloat($raw['charging_time_hours'] ?? null),
                'case_material' => $this->nullableString($raw['case_material'] ?? null),
                'band_material' => $this->nullableString($raw['band_material'] ?? null),
                'camera' => $this->nullableString($raw['camera'] ?? null),
                'functions' => $this->normalizeFunctions($raw['functions'] ?? []),
                'is_active' => true,
                'featured' => false,
            ],
            'variants' => $this->normalizeVariants($raw['variants'] ?? []),
            'images' => $this->normalizeImages($raw['images'] ?? []),
            'source_url' => $raw['source_url'] ?? null,
        ];
    }

    private function normalizeVariants(array $variants): array
    {
        $normalized = [];

        foreach ($variants as $variant) {
            $name = trim((string) data_get($variant, 'name', ''));
            if ($name === '') {
                continue;
            }

            $normalized[] = [
                'name' => Str::limit($name, 160, ''),
                'quantity' => max(0, (int) data_get($variant, 'quantity', 0)),
                'low_stock_threshold' => max(0, (int) data_get($variant, 'low_stock_threshold', 5)),
            ];
        }

        if ($normalized === []) {
            $normalized[] = [
                'name' => 'Default',
                'quantity' => 0,
                'low_stock_threshold' => 5,
            ];
        }

        return array_values(array_slice($normalized, 0, 30));
    }

    private function normalizeImages(array $images): array
    {
        $valid = array_values(array_filter($images, static function ($url) {
            return is_string($url) && str_starts_with($url, 'http');
        }));

        return array_values(array_slice(array_unique($valid), 0, 12));
    }

    private function normalizeFunctions(mixed $functions): array
    {
        if (is_array($functions)) {
            $items = $functions;
        } else {
            $text = trim((string) ($functions ?? ''));
            if ($text === '') {
                return [];
            }

            $items = preg_split('/[,\n]+/', $text) ?: [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $clean = trim((string) $item);
            if ($clean !== '') {
                $normalized[] = Str::limit($clean, 100, '');
            }
        }

        return array_values(array_unique($normalized));
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function guessBoolean(array $raw, array $keywords): bool
    {
        $blob = strtolower(json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        foreach ($keywords as $keyword) {
            if (str_contains($blob, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function guessWaterResistance(array $raw): ?string
    {
        $blob = strtoupper(json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        if (preg_match('/IP\s?([0-9]{2})/', $blob, $match) === 1) {
            return 'IP' . $match[1];
        }

        return null;
    }

    private function guessBatteryHours(array $raw): ?int
    {
        $blob = strtolower(json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        if (preg_match('/([0-9]{1,3})\s*(?:hours|hrs|hour|h)\b/', $blob, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }
}
