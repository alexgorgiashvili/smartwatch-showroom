<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotDocument;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Log;

class RagContextBuilder
{
    private const RERANK_MIN_SCORE = 0.3;

    private const TYPE_LABELS = [
        'product' => 'პროდუქტი',
        'faq' => 'ხშირი კითხვა',
        'policy' => 'პოლიტიკა',
        'support' => 'ტექნიკური დახმარება',
    ];

    public function __construct(
        private HybridSearchService $hybridSearch,
        private RerankService $rerank,
        private UnifiedAiPolicyService $policy
    ) {
    }

    public function build(string $question, int $topK = 5, array $filters = [], ?IntentResult $intent = null): ?string
    {
        $spanId = $this->langfuse()->startSpan('rag.build', [
            'question' => $question,
        ], [
            'top_k' => $topK,
            'intent' => $intent?->intent(),
        ]);

        if (!$this->hybridSearch->isConfigured()) {
            $this->langfuse()->endSpan($spanId, [
                'reason' => 'hybrid_search_not_configured',
            ]);

            return null;
        }

        try {
            $normalizedQuestion = $this->policy->normalizeIncomingMessage($question);
            $intentQuery = trim((string) ($intent?->standaloneQuery() ?? ''));
            $searchQuery = $intentQuery !== ''
                ? $intentQuery
                : ($normalizedQuestion !== '' ? $normalizedQuestion : $question);

            $effectiveFilters = $this->applyIntentFilters($filters, $intent);
            $alphaOverride = $this->intentAlpha($intent);

            $matches = $this->hybridSearch->hybridSearch($searchQuery, 50, $effectiveFilters, $alphaOverride);

            if ($matches === []) {
                $this->langfuse()->endSpan($spanId, [
                    'match_count' => 0,
                ]);

                return null;
            }

            $keys = collect($matches)
                ->map(fn ($match) => data_get($match, 'metadata.key'))
                ->filter()
                ->values()
                ->all();

            if ($keys === []) {
                $this->langfuse()->endSpan($spanId, [
                    'match_count' => count($matches),
                    'document_key_count' => 0,
                ]);

                return null;
            }

            $documents = ChatbotDocument::active()
                ->whereIn('key', $keys)
                ->get()
                ->keyBy('key');

            $candidates = [];

            foreach ($matches as $match) {
                $key = data_get($match, 'metadata.key');

                if (!$key || !$documents->has($key)) {
                    continue;
                }

                $document = $documents->get($key);
                $candidates[] = [
                    'id' => $key,
                    'text' => trim(($document->title ?? '') . "\n" . ($document->content_ka ?? '')),
                    'metadata' => [
                        'key' => $key,
                    ],
                ];
            }

            if ($candidates === []) {
                $this->langfuse()->endSpan($spanId, [
                    'match_count' => count($matches),
                    'document_count' => $documents->count(),
                    'candidate_count' => 0,
                ]);

                return null;
            }

            $limit = min(5, max(1, $topK));
            $reranked = $this->rerank->rerank($searchQuery, $candidates, $limit);

            $useReranked = $reranked !== [];

            if ($useReranked && $this->rerank->isConfigured()) {
                $topScore = (float) ($reranked[0]['score'] ?? 0.0);
                $useReranked = $topScore >= self::RERANK_MIN_SCORE;
            }

            $selectedResults = $useReranked
                ? $reranked
                : array_slice(array_map(static function (array $match): array {
                    return [
                        'metadata' => [
                            'key' => data_get($match, 'metadata.key'),
                        ],
                    ];
                }, $matches), 0, $limit);

            $parts = [];

            foreach ($selectedResults as $result) {
                $key = data_get($result, 'metadata.key');

                if (!$key || !$documents->has($key)) {
                    continue;
                }

                $parts[] = $this->formatDocument($documents->get($key));
            }

            if ($parts === []) {
                $this->langfuse()->endSpan($spanId, [
                    'match_count' => count($matches),
                    'document_count' => $documents->count(),
                    'candidate_count' => count($candidates),
                    'selected_count' => 0,
                ]);

                return null;
            }

            $ragContext = implode("\n\n", $parts);
            $maxChars = max(500, (int) config('chatbot.rag.max_chars', 3000));
            $wasTruncated = false;

            if (mb_strlen($ragContext) > $maxChars) {
                $ragContext = rtrim(mb_substr($ragContext, 0, $maxChars));
                $wasTruncated = true;
            }

            $this->langfuse()->endSpan($spanId, [
                'match_count' => count($matches),
                'document_count' => $documents->count(),
                'candidate_count' => count($candidates),
                'selected_count' => count($parts),
                'used_reranked' => $useReranked,
                'truncated' => $wasTruncated,
            ], $ragContext);

            return $ragContext;
        } catch (\Throwable $exception) {
            Log::warning('RAG context build failed', [
                'error' => $exception->getMessage(),
            ]);

            $this->langfuse()->endSpan($spanId, [
                'reason' => 'rag_exception',
                'error' => $exception->getMessage(),
            ], null, $exception->getMessage());

            return null;
        }
    }

    private function formatDocument(ChatbotDocument $document): string
    {
        $label = self::TYPE_LABELS[$document->type] ?? ucfirst($document->type);
        $header = $document->title ? $document->title : $label;
        $lines = [$header, $document->content_ka];
        $metadata = $document->metadata ?? [];

        if (!empty($metadata['slug'])) {
            $lines[] = 'ბმული: ' . url('/products/' . $metadata['slug']);
        }

        return implode("\n", array_filter($lines));
    }

    private function applyIntentFilters(array $filters, ?IntentResult $intent): array
    {
        $brand = trim((string) ($intent?->brand() ?? ''));

        if ($brand === '') {
            return $filters;
        }

        $brandFilter = ['brand' => mb_strtolower($brand)];

        if ($filters === []) {
            return $brandFilter;
        }

        if (isset($filters['$and']) && is_array($filters['$and'])) {
            $andFilters = $filters['$and'];
            $andFilters[] = $brandFilter;

            return ['$and' => $andFilters];
        }

        return ['$and' => [$filters, $brandFilter]];
    }

    private function intentAlpha(?IntentResult $intent): ?float
    {
        if (!$intent) {
            return null;
        }

        return match ($intent->intent()) {
            'price_query', 'stock_query' => 0.3,
            'recommendation' => 0.7,
            'comparison', 'features' => 0.5,
            default => 0.5,
        };
    }

    private function langfuse(): LangfuseService
    {
        try {
            return app(LangfuseService::class);
        } catch (BindingResolutionException) {
            return new LangfuseService();
        }
    }
}
