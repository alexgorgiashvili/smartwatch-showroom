<?php

namespace App\Services\ProductQuality;

use App\Models\ProductEvidenceItem;
use App\Models\ResearchTarget;
use App\Services\AlibabaApifyLiveScraperService;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class ProductQualityEvidenceIngestionService
{
    public function __construct(
        private readonly ProductQualityEvidenceExtractorService $extractor,
        private readonly ProductQualityHeuristicService $heuristics,
        private readonly AlibabaApifyLiveScraperService $apifyScraper,
    ) {
    }

    public function ingest(ResearchTarget $target): array
    {
        $identity = is_array($target->identity_payload) ? $target->identity_payload : [];
        $input = (array) data_get($identity, 'research_input', []);
        $defaults = [
            'source_url' => $target->source_url,
            'external_source' => $target->external_source,
        ];

        $candidates = [];

        $manualEvidenceInput = trim((string) ($input['manual_evidence_input'] ?? ''));
        if ($manualEvidenceInput !== '') {
            $candidates = array_merge($candidates, $this->extractor->extractManualEvidence($manualEvidenceInput, $defaults));
        }

        $apifyJson = trim((string) ($input['apify_json'] ?? ''));
        if ($apifyJson !== '') {
            $decoded = json_decode($apifyJson, true);

            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Manual Apify JSON could not be decoded.');
            }

            $candidates = array_merge($candidates, $this->extractor->extractFromApifyPayload($decoded, $defaults));
        } elseif ($manualEvidenceInput === '' && $target->source_url && $target->external_source === 'alibaba') {
            $payload = $this->apifyScraper->scrapeProductUrl($target->source_url);
            $candidates = array_merge($candidates, $this->extractor->extractFromApifyPayload($payload, $defaults));
        }

        $storedCount = 0;
        $duplicateCount = 0;

        foreach ($candidates as $candidate) {
            $normalized = $this->heuristics->normalizeEvidenceCandidate($candidate);

            $attributes = [
                'research_target_id' => $target->id,
                'dedupe_hash' => $normalized['dedupe_hash'],
            ];

            $values = Arr::only($normalized, [
                'source_type',
                'source_url',
                'source_item_id',
                'author_name',
                'author_type',
                'rating_raw',
                'title',
                'body_text',
                'language',
                'published_at',
                'country',
                'credibility_weight',
                'raw_payload',
                'normalized_payload',
            ]);

            $values['product_id'] = $target->product_id;

            $record = ProductEvidenceItem::query()->firstOrNew($attributes);
            $exists = $record->exists;
            $record->fill($values);
            $record->save();

            if ($exists) {
                $duplicateCount++;
            } else {
                $storedCount++;
            }
        }

        return [
            'stored_count' => $storedCount,
            'duplicate_count' => $duplicateCount,
            'evidence_count' => $target->evidenceItems()->count(),
            'end_user_evidence_count' => $target->evidenceItems()->where('author_type', 'end_user')->count(),
            'supplier_evidence_count' => $target->evidenceItems()->where('author_type', 'supplier')->count(),
        ];
    }
}
