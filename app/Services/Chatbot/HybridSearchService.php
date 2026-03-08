<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotDocument;
use Illuminate\Support\Facades\Cache;

class HybridSearchService
{
    private const BM25_K1 = 1.2;
    private const BM25_B = 0.75;

    public function __construct(
        private EmbeddingService $embedding,
        private PineconeService $pinecone,
        private UnifiedAiPolicyService $policy
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->embedding->isConfigured() && $this->pinecone->isConfigured();
    }

    public function classifyQueryIntent(string $query): float
    {
        $normalized = trim($this->policy->normalizeIncomingMessage($query));

        if ($normalized === '') {
            return 0.5;
        }

        if (preg_match('/\b[A-Z]{2,}-?\d{2,}\b/u', $query) === 1) {
            return 0.2;
        }

        if (preg_match('/\b(model|sku|part\s?number)\b/i', $query) === 1) {
            return 0.2;
        }

        if (preg_match('/(ფასი|ღირს|ღირებულ|რამდენი|რა\s*ღირს|მარაგ|საწყობ|დარჩენილი)/u', $normalized) === 1) {
            return 0.3;
        }

        if (preg_match('/\b(price|cost|stock|availability|in\s*stock)\b/i', $query) === 1) {
            return 0.3;
        }

        if (preg_match('/(სტილი|ლუქი|სასიამოვნო|ლამაზი|ჩამოსაცმელი)/u', $normalized) === 1) {
            return 0.7;
        }

        if (preg_match('/\b(style|look|nice|fashion|wedding|gift)\b/i', $query) === 1) {
            return 0.7;
        }

        return 0.5;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function hybridSearch(string $query, int $topK = 50, array $filters = [], ?float $alphaOverride = null): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $normalized = trim($this->policy->normalizeIncomingMessage($query));
        $searchQuery = $normalized !== '' ? $normalized : $query;

        $denseVector = $this->embedding->embed($searchQuery);

        if ($denseVector === []) {
            return [];
        }

        $alpha = $alphaOverride ?? $this->classifyQueryIntent($searchQuery);
        $alpha = max(0.0, min(1.0, (float) $alpha));
        $sparseVector = $this->buildSparseVector($searchQuery);

        return $this->pinecone->query(
            $denseVector,
            max(1, $topK),
            $filters,
            null,
            $sparseVector,
            $alpha
        );
    }

    /**
     * @return array{indices: array<int, int>, values: array<int, float>}
     */
    private function buildSparseVector(string $query): array
    {
        $tokens = $this->tokenize($query);

        if ($tokens === []) {
            return ['indices' => [], 'values' => []];
        }

        $stats = $this->corpusStats();
        $docCount = max(1, (int) ($stats['doc_count'] ?? 1));
        $avgDocLength = max(1.0, (float) ($stats['avg_doc_length'] ?? 1.0));

        $termFrequency = [];
        foreach ($tokens as $token) {
            $termFrequency[$token] = ($termFrequency[$token] ?? 0) + 1;
        }

        $queryLength = max(1, count($tokens));
        $indices = [];
        $values = [];

        foreach ($termFrequency as $token => $tf) {
            $documentFrequency = (int) ($stats['document_frequency'][$token] ?? 0);
            $idf = log((($docCount - $documentFrequency + 0.5) / ($documentFrequency + 0.5)) + 1);

            $bm25Numerator = $tf * (self::BM25_K1 + 1);
            $bm25Denominator = $tf + self::BM25_K1 * (1 - self::BM25_B + self::BM25_B * ($queryLength / $avgDocLength));
            $weight = $idf * ($bm25Numerator / max(0.001, $bm25Denominator));

            if ($weight <= 0) {
                continue;
            }

            $indices[] = $this->tokenToSparseIndex($token);
            $values[] = round((float) $weight, 6);
        }

        return ['indices' => $indices, 'values' => $values];
    }

    /**
     * @return array{doc_count: int, avg_doc_length: float, document_frequency: array<string, int>}
     */
    private function corpusStats(): array
    {
        $contextVersion = (int) Cache::get('product_context_version', 1);
        $cacheKey = 'chatbot:bm25:stats:v1:' . $contextVersion;

        return Cache::remember($cacheKey, now()->addMinutes(10), function (): array {
            $docCount = 0;
            $totalLength = 0;
            $documentFrequency = [];

            ChatbotDocument::active()
                ->select(['id', 'title', 'content_ka'])
                ->chunkById(200, function ($documents) use (&$docCount, &$totalLength, &$documentFrequency): void {
                    foreach ($documents as $document) {
                        $tokens = $this->tokenize(trim(($document->title ?? '') . ' ' . ($document->content_ka ?? '')));

                        if ($tokens === []) {
                            continue;
                        }

                        $docCount++;
                        $totalLength += count($tokens);

                        foreach (array_keys(array_flip($tokens)) as $token) {
                            $documentFrequency[$token] = ($documentFrequency[$token] ?? 0) + 1;
                        }
                    }
                });

            return [
                'doc_count' => max(1, $docCount),
                'avg_doc_length' => $docCount > 0 ? ($totalLength / $docCount) : 1.0,
                'document_frequency' => $documentFrequency,
            ];
        });
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? $text;

        if ($text === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $text) ?: [];

        return array_values(array_filter($parts, static fn (string $token): bool => mb_strlen($token) >= 2));
    }

    private function tokenToSparseIndex(string $token): int
    {
        return (int) sprintf('%u', crc32($token));
    }
}
