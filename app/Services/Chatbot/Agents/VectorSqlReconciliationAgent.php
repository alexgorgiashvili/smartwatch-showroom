<?php

namespace App\Services\Chatbot\Agents;

use App\Models\Product;
use App\Services\Chatbot\IntentResult;
use Illuminate\Support\Collection;

class VectorSqlReconciliationAgent
{
    /**
     * Reconcile RAG candidates with real-time SQL inventory
     *
     * @param Collection<int, Product> $ragCandidates
     * @return array{products: Collection<int, Product>, out_of_stock_count: int, reconciliation_meta: array}
     */
    public function reconcile(Collection $ragCandidates, IntentResult $intent): array
    {
        if ($ragCandidates->isEmpty()) {
            return [
                'products' => collect(),
                'out_of_stock_count' => 0,
                'reconciliation_meta' => [
                    'strategy' => 'empty_input',
                ],
            ];
        }

        $productIds = $ragCandidates->pluck('id')->unique()->values()->all();

        $liveInventory = Product::query()
            ->whereIn('id', $productIds)
            ->active()
            ->with(['primaryImage', 'variants'])
            ->withSum('variants as total_stock', 'quantity')
            ->get()
            ->keyBy('id');

        $reconciled = collect();
        $outOfStockCount = 0;
        $missingCount = 0;

        foreach ($ragCandidates as $candidate) {
            $liveProduct = $liveInventory->get($candidate->id);

            if (!$liveProduct) {
                $missingCount++;
                continue;
            }

            $isInStock = (int) ($liveProduct->total_stock ?? 0) > 0;

            if (!$isInStock && $this->shouldFilterOutOfStock($intent)) {
                $outOfStockCount++;
                continue;
            }

            $reconciled->push($liveProduct);
        }

        return [
            'products' => $reconciled->values(),
            'out_of_stock_count' => $outOfStockCount,
            'reconciliation_meta' => [
                'strategy' => 'vector_sql_merge',
                'rag_candidates' => $ragCandidates->count(),
                'live_verified' => $liveInventory->count(),
                'reconciled' => $reconciled->count(),
                'out_of_stock_filtered' => $outOfStockCount,
                'missing_from_db' => $missingCount,
            ],
        ];
    }

    /**
     * Determine if out-of-stock products should be filtered
     */
    private function shouldFilterOutOfStock(IntentResult $intent): bool
    {
        return match ($intent->intent()) {
            'stock_query' => false,
            'price_query' => false,
            'comparison' => true,
            'general' => true,
            default => true,
        };
    }

    /**
     * Enrich products with semantic context
     *
     * @param Collection<int, Product> $products
     */
    public function enrichWithSemanticContext(Collection $products, string $ragContext): Collection
    {
        return $products->map(function (Product $product) use ($ragContext) {
            $product->semantic_context = $this->extractRelevantContext($product, $ragContext);
            return $product;
        });
    }

    private function extractRelevantContext(Product $product, string $ragContext): string
    {
        $productName = mb_strtolower($product->name ?? '');
        $lines = explode("\n", $ragContext);

        $relevantLines = array_filter($lines, function ($line) use ($productName) {
            return mb_stripos($line, $productName) !== false;
        });

        return implode("\n", array_slice($relevantLines, 0, 3));
    }
}
