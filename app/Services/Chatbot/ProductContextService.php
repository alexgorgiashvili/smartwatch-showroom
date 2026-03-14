<?php

namespace App\Services\Chatbot;

use App\Models\ContactSetting;
use App\Models\Product;
use Illuminate\Support\Collection;

class ProductContextService
{
    private const MIN_REALISTIC_PRODUCT_PRICE = 0.5;
    private const PRODUCT_CONTEXT_LIMIT = 4;

    /**
     * Filter products with realistic prices
     *
     * @param Collection<int, Product> $products
     * @return Collection<int, Product>
     */
    public function filterRealisticPrices(Collection $products): Collection
    {
        return $products
            ->filter(fn (Product $product): bool => $this->productHasRealisticPrice($product->sale_price, $product->price))
            ->values();
    }

    /**
     * Select products for prompt context based on intent and preferences
     *
     * @param Collection<int, Product> $products
     * @return Collection<int, Product>
     */
    public function selectForPrompt(Collection $products, IntentResult $intentResult, array $preferences = []): Collection
    {
        $productCollection = $products->values();

        if (isset($preferences['budget_max_gel']) && is_numeric($preferences['budget_max_gel'])) {
            $budget = (float) $preferences['budget_max_gel'];

            $productCollection = $productCollection
                ->sortBy(function (Product $product) use ($budget): array {
                    $effectivePrice = is_numeric($product->sale_price) && (float) $product->sale_price > 0
                        ? (float) $product->sale_price
                        : (is_numeric($product->price) ? (float) $product->price : INF);

                    $withinBudget = $effectivePrice <= $budget;
                    $distance = abs($effectivePrice - $budget);

                    return [$withinBudget ? 0 : 1, $distance, $effectivePrice];
                })
                ->values();
        }

        if (in_array($intentResult->intent(), ['price_query', 'stock_query'], true)) {
            if ($intentResult->hasSpecificProduct()) {
                return $productCollection->take(1)->values();
            }
            return $productCollection->take(4)->values();
        }

        if ($intentResult->intent() === 'comparison') {
            return $productCollection->take(self::PRODUCT_CONTEXT_LIMIT)->values();
        }

        return $productCollection->take(self::PRODUCT_CONTEXT_LIMIT)->values();
    }

    /**
     * Build validation context for response validator
     *
     * @param Collection<int, Product> $products
     * @return array{products: array<int, array<string, mixed>>, allowed_urls: array<int, string>}
     */
    public function buildValidationContext(Collection $products, array $contactSettings): array
    {
        $productRows = $products
            ->map(function (Product $product): array {
                return [
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => is_numeric($product->price) ? (float) $product->price : null,
                    'sale_price' => is_numeric($product->sale_price) ? (float) $product->sale_price : null,
                    'is_in_stock' => (int) ($product->total_stock ?? 0) > 0,
                    'url' => url('/products/' . $product->slug),
                ];
            })
            ->values()
            ->all();

        $allowedUrls = [
            rtrim(route('home'), '/'),
            rtrim(route('products.index'), '/'),
            rtrim(route('contact'), '/'),
        ];

        if (!empty($contactSettings['whatsapp_url'])) {
            $allowedUrls[] = rtrim((string) $contactSettings['whatsapp_url'], '/');
        }

        foreach ($productRows as $productRow) {
            if (!empty($productRow['url'])) {
                $allowedUrls[] = rtrim((string) $productRow['url'], '/');
            }
        }

        return [
            'products' => $productRows,
            'allowed_urls' => array_values(array_unique(array_filter($allowedUrls))),
        ];
    }

    /**
     * Format products for prompt display
     *
     * @param Collection<int, Product> $products
     */
    public function formatProductsForPrompt(Collection $products): string
    {
        $productLines = $products
            ->map(function (Product $product): string {
                $price = $product->sale_price
                    ? $product->sale_price . ' ₾ (ფასდაკლება, ძველი ფასი ' . $product->price . ' ₾)'
                    : $product->price . ' ₾';

                $stockTotal = max(0, (int) ($product->total_stock ?? 0));
                $stockStatus = $stockTotal > 0 ? 'მარაგშია' : 'ამოწურულია';

                return '- ' . $product->name
                    . ' | ბმული იდენტიფიკატორი: ' . $product->slug
                    . ' | ფასი: ' . $price
                    . ' | მარაგი: ' . $stockStatus;
            })
            ->implode("\n");

        return $productLines !== '' ? $productLines : 'პროდუქტები ვერ მოიძებნა.';
    }

    private function productHasRealisticPrice(mixed $salePrice, mixed $price): bool
    {
        $effectivePrice = is_numeric($salePrice) && (float) $salePrice > 0
            ? (float) $salePrice
            : (is_numeric($price) ? (float) $price : null);

        return $effectivePrice !== null && $effectivePrice >= self::MIN_REALISTIC_PRODUCT_PRICE;
    }
}
