<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AlibabaDataProcessorService;
use App\Services\AlibabaScraperService;
use App\Services\Chatbot\ChatbotContentSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AlibabaImportController extends Controller
{
    public function __construct(
        private readonly AlibabaScraperService $scraper,
        private readonly AlibabaDataProcessorService $processor,
    ) {
    }

    public function index(): View
    {
        return view('admin.products.import-alibaba');
    }

    public function parse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['nullable', 'url', 'required_without:raw_html'],
            'raw_html' => ['nullable', 'string', 'min:1000'],
        ]);

        try {
            $raw = $this->scraper->scrape($data['url'] ?? null, $data['raw_html'] ?? null);
            $processed = $this->processor->process($raw);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'captcha_detected' => true,
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Failed to parse this page. Try direct product URL or paste full Page Source from browser.',
            ], 422);
        }

        return response()->json([
            'message' => 'Product parsed successfully.',
            'data' => $processed,
        ]);
    }

    public function confirm(Request $request, ChatbotContentSyncService $contentSync): JsonResponse
    {
        $payload = $request->validate([
            'source_url' => ['nullable', 'url'],
            'name_en' => ['required', 'string', 'max:160'],
            'name_ka' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:200'],
            'short_description_en' => ['nullable', 'string', 'max:255'],
            'short_description_ka' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_ka' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'sim_support' => ['nullable', 'boolean'],
            'gps_features' => ['nullable', 'boolean'],
            'water_resistant' => ['nullable', 'string', 'max:50'],
            'battery_life_hours' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'operating_system' => ['nullable', 'string', 'max:100'],
            'screen_size' => ['nullable', 'string', 'max:100'],
            'display_type' => ['nullable', 'string', 'max:100'],
            'screen_resolution' => ['nullable', 'string', 'max:100'],
            'battery_capacity_mah' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'charging_time_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'case_material' => ['nullable', 'string', 'max:100'],
            'band_material' => ['nullable', 'string', 'max:100'],
            'camera' => ['nullable', 'string', 'max:100'],
            'functions' => ['nullable'],
            'is_active' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'selected_images' => ['nullable', 'array'],
            'selected_images.*' => ['url'],
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['required', 'string', 'max:160'],
            'variants.*.quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $productData = [
            'name_en' => $payload['name_en'],
            'name_ka' => $payload['name_ka'],
            'slug' => $this->ensureSlug($payload['slug'] ?? null, $payload['name_en']),
            'short_description_en' => $payload['short_description_en'] ?? null,
            'short_description_ka' => $payload['short_description_ka'] ?? null,
            'description_en' => $payload['description_en'] ?? null,
            'description_ka' => $payload['description_ka'] ?? null,
            'price' => $payload['price'] ?? null,
            'sale_price' => $payload['sale_price'] ?? null,
            'currency' => strtoupper($payload['currency'] ?? 'GEL'),
            'sim_support' => $request->boolean('sim_support'),
            'gps_features' => $request->boolean('gps_features'),
            'water_resistant' => $payload['water_resistant'] ?? null,
            'battery_life_hours' => $payload['battery_life_hours'] ?? null,
            'warranty_months' => $payload['warranty_months'] ?? null,
            'operating_system' => $payload['operating_system'] ?? null,
            'screen_size' => $payload['screen_size'] ?? null,
            'display_type' => $payload['display_type'] ?? null,
            'screen_resolution' => $payload['screen_resolution'] ?? null,
            'battery_capacity_mah' => $payload['battery_capacity_mah'] ?? null,
            'charging_time_hours' => $payload['charging_time_hours'] ?? null,
            'case_material' => $payload['case_material'] ?? null,
            'band_material' => $payload['band_material'] ?? null,
            'camera' => $payload['camera'] ?? null,
            'functions' => $this->normalizeFunctions($payload['functions'] ?? null),
            'is_active' => $request->boolean('is_active', true),
            'featured' => $request->boolean('featured'),
        ];

        $product = DB::transaction(function () use ($productData, $payload) {
            $product = Product::create($productData);

            $selectedImages = array_slice((array) ($payload['selected_images'] ?? []), 0, 12);
            if ($selectedImages !== []) {
                $paths = $this->scraper->downloadImages($selectedImages, $product->slug);
                foreach ($paths as $index => $path) {
                    $product->images()->create([
                        'path' => 'storage/' . $path,
                        'alt_en' => $product->name_en,
                        'alt_ka' => $product->name_ka,
                        'sort_order' => $index,
                        'is_primary' => $index === 0,
                    ]);
                }
            }

            foreach (array_slice((array) ($payload['variants'] ?? []), 0, 30) as $variant) {
                $name = trim((string) ($variant['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $product->variants()->create([
                    'name' => $name,
                    'quantity' => max(0, (int) ($variant['quantity'] ?? 0)),
                    'low_stock_threshold' => max(0, (int) ($variant['low_stock_threshold'] ?? 5)),
                ]);
            }

            return $product;
        });

        $contentSync->syncProduct($product->fresh('variants'));

        return response()->json([
            'message' => 'Product created from Alibaba import.',
            'redirect' => route('admin.products.edit', $product),
            'product_id' => $product->id,
        ]);
    }

    private function ensureSlug(?string $slug, string $name): string
    {
        $baseSlug = $slug ? Str::slug($slug) : Str::slug($name);
        $candidate = $baseSlug;
        $counter = 1;

        while (Product::where('slug', $candidate)->exists()) {
            $candidate = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function normalizeFunctions(mixed $value): ?array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $text = trim((string) ($value ?? ''));
            if ($text === '') {
                return null;
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

        $normalized = array_values(array_unique($normalized));

        return $normalized === [] ? null : $normalized;
    }
}
