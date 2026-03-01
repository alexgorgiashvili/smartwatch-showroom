<?php

namespace App\Services;

use App\Models\CompetitorProduct;
use App\Models\CompetitorSource;
use App\Services\Contracts\CompetitorCategoryScraper;
use Illuminate\Support\Facades\DB;

class CompetitorMonitorService
{
    public function __construct(
        private readonly IMobileCategoryScraperService $iMobileScraper,
        private readonly LuckyStoreCategoryScraperService $luckyStoreScraper,
        private readonly AltaCategoryScraperService $altaScraper,
    ) {
    }

    public function refreshSource(CompetitorSource $source): array
    {
        $capturedAt = now();

        try {
            $items = $this->resolveScraperForSource($source)->scrapeCategory($source->category_url);
            $created = 0;
            $updated = 0;

            DB::transaction(function () use ($source, $items, $capturedAt, &$created, &$updated) {
                foreach ($items as $item) {
                    $url = trim((string) ($item['product_url'] ?? ''));
                    if ($url === '') {
                        continue;
                    }

                    $product = CompetitorProduct::updateOrCreate(
                        [
                            'competitor_source_id' => $source->id,
                            'product_url_hash' => hash('sha256', mb_strtolower($url)),
                        ],
                        [
                            'external_product_id' => $this->nullableString($item['external_product_id'] ?? null),
                            'product_url' => $url,
                            'title' => (string) ($item['title'] ?? ''),
                            'image_url' => $this->nullableString($item['image_url'] ?? null),
                            'current_price' => $item['current_price'] ?? null,
                            'old_price' => $item['old_price'] ?? null,
                            'currency' => strtoupper((string) ($item['currency'] ?? 'GEL')),
                            'availability' => $this->nullableString($item['availability'] ?? null),
                            'is_in_stock' => $item['is_in_stock'] ?? null,
                            'last_seen_at' => $capturedAt,
                        ]
                    );

                    if ($product->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }

                    $product->snapshots()->create([
                        'title' => $product->title,
                        'image_url' => $product->image_url,
                        'price' => $product->current_price,
                        'old_price' => $product->old_price,
                        'currency' => $product->currency,
                        'availability' => $product->availability,
                        'is_in_stock' => $product->is_in_stock,
                        'captured_at' => $capturedAt,
                    ]);
                }

                $source->update([
                    'last_synced_at' => $capturedAt,
                    'last_status' => 'success',
                    'last_error' => null,
                ]);
            });

            return [
                'total_scraped' => count($items),
                'created' => $created,
                'updated' => $updated,
            ];
        } catch (\Throwable $exception) {
            $source->update([
                'last_status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function resolveScraperForSource(CompetitorSource $source): CompetitorCategoryScraper
    {
        $domain = $this->normalizeDomain($source->domain, $source->category_url);

        if (str_contains($domain, 'i-mobile.ge')) {
            return $this->iMobileScraper;
        }

        if (str_contains($domain, 'luckystore.ge')) {
            return $this->luckyStoreScraper;
        }

        if (str_contains($domain, 'alta.ge')) {
            return $this->altaScraper;
        }

        throw new \RuntimeException('Unsupported competitor source domain: ' . $domain);
    }

    private function normalizeDomain(?string $domain, string $categoryUrl): string
    {
        $normalizedDomain = trim(mb_strtolower((string) ($domain ?? '')));
        if ($normalizedDomain !== '') {
            return str_starts_with($normalizedDomain, 'www.') ? substr($normalizedDomain, 4) : $normalizedDomain;
        }

        $host = parse_url($categoryUrl, PHP_URL_HOST);
        if (!is_string($host) || trim($host) === '') {
            throw new \RuntimeException('Invalid competitor source URL.');
        }

        $normalizedHost = mb_strtolower($host);

        return str_starts_with($normalizedHost, 'www.') ? substr($normalizedHost, 4) : $normalizedHost;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
