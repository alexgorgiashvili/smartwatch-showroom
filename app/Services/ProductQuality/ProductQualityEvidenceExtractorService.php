<?php

namespace App\Services\ProductQuality;

use Illuminate\Support\Str;

class ProductQualityEvidenceExtractorService
{
    public function extractManualEvidence(string $payload, array $defaults = []): array
    {
        $trimmed = trim($payload);

        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            $items = $decoded;

            if (isset($decoded['items']) && is_array($decoded['items'])) {
                $items = $decoded['items'];
            }

            $results = [];

            foreach ($items as $item) {
                if (is_string($item)) {
                    $candidate = $this->buildPlainTextCandidate($item, $defaults);
                    if ($candidate !== null) {
                        $results[] = $candidate;
                    }

                    continue;
                }

                if (is_array($item)) {
                    $candidate = $this->mapStructuredNode($item, ['manual_input'], $defaults);
                    if ($candidate !== null) {
                        $results[] = $candidate;
                    }
                }
            }

            return $this->dedupeCandidates($results);
        }

        $blocks = preg_split("/(?:\r?\n){2,}/", $trimmed) ?: [];
        if (count($blocks) <= 1) {
            $blocks = preg_split("/\r?\n/", $trimmed) ?: [];
        }

        $results = [];

        foreach ($blocks as $block) {
            $candidate = $this->buildPlainTextCandidate($block, $defaults);
            if ($candidate !== null) {
                $results[] = $candidate;
            }
        }

        return $this->dedupeCandidates($results);
    }

    public function extractFromApifyPayload(array $payload, array $defaults = []): array
    {
        $results = [];
        $this->walkNode($payload, [], $defaults, $results);

        $results = $this->dedupeCandidates($results);

        if ($results !== []) {
            return $results;
        }

        return $this->extractSupplierProductEvidence($payload, $defaults);
    }

    private function walkNode(mixed $node, array $path, array $defaults, array &$results): void
    {
        if (!is_array($node)) {
            return;
        }

        $candidate = $this->mapStructuredNode($node, $path, $defaults);
        if ($candidate !== null) {
            $results[] = $candidate;
        }

        foreach ($node as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $nextPath = [...$path, (string) $key];
            $this->walkNode($value, $nextPath, $defaults, $results);
        }
    }

    private function mapStructuredNode(array $node, array $path, array $defaults): ?array
    {
        $pathText = strtolower(implode('.', $path));
        $title = $this->firstString($node, ['title', 'headline', 'summary', 'subject']);
        $body = $this->extractBodyText($node);
        $rating = $this->extractRating($node);

        if ($this->looksLikeProductListingNode($node, $pathText)) {
            return null;
        }

        $lowerKeys = array_map(static fn ($key) => strtolower((string) $key), array_keys($node));
        $reviewKeys = array_intersect($lowerKeys, [
            'source_type',
            'author_type',
            'review',
            'review_text',
            'reviewtext',
            'body_text',
            'comment',
            'comments',
            'feedback',
            'message',
            'content',
            'body',
            'pros',
            'cons',
            'rating',
            'score',
        ]);

        $looksLikeReview = preg_match('/review|comment|feedback|testimonial|rating|buyer|seller|forum|discussion/', $pathText) === 1
            || $reviewKeys !== []
            || $rating !== null;

        if (!$looksLikeReview) {
            return null;
        }

        if (($body === null || mb_strlen($body) < 12) && ($title === null || mb_strlen($title) < 6)) {
            return null;
        }

        $authorType = $this->inferAuthorType($node, $pathText, $defaults);
        $sourceUrl = $this->firstString($node, ['source_url', 'url', 'link', 'permalink']) ?? ($defaults['source_url'] ?? null);
        $publishedAt = $this->extractPublishedAt($node);
        $sourceType = $this->firstString($node, ['source_type'])
            ?? $this->inferSourceType($pathText, $sourceUrl, $authorType);

        return [
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
            'source_item_id' => $this->firstString($node, ['source_item_id', 'review_id', 'reviewId', 'comment_id', 'commentId', 'id']),
            'author_name' => $this->firstString($node, ['author_name', 'author', 'reviewer_name', 'reviewer', 'user_name', 'username', 'buyer_name', 'buyerName']),
            'author_type' => $authorType,
            'rating_raw' => $rating,
            'title' => $title,
            'body_text' => $body,
            'language' => $this->firstString($node, ['language', 'lang']),
            'published_at' => $publishedAt,
            'country' => $this->firstString($node, ['country', 'region', 'locale']),
            'credibility_weight' => $this->inferCredibilityWeight($authorType),
            'raw_payload' => $node,
        ];
    }

    private function looksLikeProductListingNode(array $node, string $pathText): bool
    {
        $lowerKeys = array_map(static fn ($key) => strtolower((string) $key), array_keys($node));
        $listingSignals = array_intersect($lowerKeys, [
            'images',
            'imageurls',
            'productimages',
            'variants',
            'functions',
            'specs',
            'specifications',
            'supplier',
            'prices',
            'price',
            'price_min',
            'price_max',
            'currency',
            'producturl',
            'url',
        ]);

        $explicitEvidenceSignals = array_intersect($lowerKeys, [
            'source_type',
            'author_type',
            'review',
            'review_text',
            'reviewtext',
            'body_text',
            'comment',
            'comments',
            'feedback',
            'message',
        ]);

        if ($explicitEvidenceSignals !== []) {
            return false;
        }

        if (preg_match('/review|comment|feedback|testimonial|rating|buyer|seller|forum|discussion/', $pathText) === 1) {
            return false;
        }

        return $listingSignals !== [];
    }

    private function buildPlainTextCandidate(string $text, array $defaults): ?array
    {
        $body = trim($text);

        if ($body === '') {
            return null;
        }

        return [
            'source_type' => $this->inferSourceType('manual_input', $defaults['source_url'] ?? null, 'unknown'),
            'source_url' => $defaults['source_url'] ?? null,
            'source_item_id' => null,
            'author_name' => null,
            'author_type' => 'unknown',
            'rating_raw' => null,
            'title' => null,
            'body_text' => $body,
            'language' => null,
            'published_at' => null,
            'country' => null,
            'credibility_weight' => $this->inferCredibilityWeight('unknown'),
            'raw_payload' => ['text' => $body],
        ];
    }

    private function extractSupplierProductEvidence(array $payload, array $defaults): array
    {
        $item = $this->extractTopLevelItem($payload);
        $sourceUrl = $this->firstString($item, ['source_url', 'sourceUrl', 'url', 'productUrl', 'link']) ?? ($defaults['source_url'] ?? null);
        $candidates = [];

        $title = $this->firstString($item, ['title', 'pageTitle', 'name', 'productTitle', 'productName', 'h1']);
        $description = $this->firstString($item, ['description', 'productDescription', 'summary', 'body_text', 'body']);

        $listingParts = array_filter([
            $title ? 'Product: ' . $title : null,
            $description,
        ]);

        if ($listingParts !== []) {
            $candidates[] = $this->buildSupplierCandidate(
                'supplier_listing',
                'Product listing summary',
                implode("\n\n", $listingParts),
                $sourceUrl,
                $item
            );
        }

        $specText = $this->stringifySpecs(
            $item['specs']
            ?? $item['specifications']
            ?? data_get($item, 'details.specs')
        );
        if ($specText !== null) {
            $candidates[] = $this->buildSupplierCandidate(
                'supplier_spec_sheet',
                'Product specifications',
                $specText,
                $sourceUrl,
                $item
            );
        }

        $functionList = $this->normalizeTextList(
            $item['functions']
            ?? data_get($item, 'specifications.function')
            ?? data_get($item, 'specifications.feature')
        );
        if ($functionList !== []) {
            $candidates[] = $this->buildSupplierCandidate(
                'supplier_feature_list',
                'Product features',
                'Functions: ' . implode('; ', $functionList),
                $sourceUrl,
                $item
            );
        }

        return $this->dedupeCandidates(array_values(array_filter($candidates)));
    }

    private function extractTopLevelItem(array $payload): array
    {
        if (isset($payload['item']) && is_array($payload['item'])) {
            return $payload['item'];
        }

        if (isset($payload['items'][0]) && is_array($payload['items'][0])) {
            return $payload['items'][0];
        }

        if (isset($payload[0]) && is_array($payload[0])) {
            return $payload[0];
        }

        return $payload;
    }

    private function buildSupplierCandidate(string $sourceType, ?string $title, string $bodyText, ?string $sourceUrl, array $rawPayload): ?array
    {
        $bodyText = trim($bodyText);

        if ($bodyText === '') {
            return null;
        }

        return [
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
            'source_item_id' => null,
            'author_name' => null,
            'author_type' => 'supplier',
            'rating_raw' => null,
            'title' => $title,
            'body_text' => $bodyText,
            'language' => null,
            'published_at' => null,
            'country' => null,
            'credibility_weight' => $this->inferCredibilityWeight('supplier'),
            'raw_payload' => $rawPayload,
        ];
    }

    private function stringifySpecs(mixed $value): ?string
    {
        if (!is_array($value)) {
            return null;
        }

        $pairs = [];

        foreach ($value as $key => $item) {
            if (is_scalar($item)) {
                $name = is_scalar($key) ? trim((string) $key) : '';
                $text = trim((string) $item);

                if ($name !== '' && $text !== '') {
                    $pairs[] = $name . ': ' . $text;
                } elseif ($text !== '') {
                    $pairs[] = $text;
                }

                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $name = $this->firstString($item, ['name', 'key', 'attribute', 'attributeName', 'attrName']);
            $text = $this->firstString($item, ['value', 'text', 'attributeValue', 'attrValue']);

            if ($name !== null && $text !== null) {
                $pairs[] = $name . ': ' . $text;
            }
        }

        if ($pairs === []) {
            return null;
        }

        return implode(' | ', array_slice($pairs, 0, 30));
    }

    private function extractBodyText(array $node): ?string
    {
        $value = $this->firstString($node, [
            'body_text',
            'body',
            'text',
            'content',
            'review',
            'review_text',
            'reviewText',
            'comment',
            'message',
            'feedback',
            'description',
        ]);

        $pros = $this->normalizeTextList($node['pros'] ?? null);
        $cons = $this->normalizeTextList($node['cons'] ?? null);

        $parts = array_filter([
            $value,
            $pros !== [] ? 'Pros: ' . implode('; ', $pros) : null,
            $cons !== [] ? 'Cons: ' . implode('; ', $cons) : null,
        ]);

        if ($parts === []) {
            return null;
        }

        return trim(implode("\n", $parts));
    }

    private function extractPublishedAt(array $node): ?string
    {
        $value = $this->firstString($node, ['published_at', 'publishedAt', 'created_at', 'createdAt', 'date', 'posted_at']);

        if ($value === null) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function extractRating(array $node): ?float
    {
        foreach (['rating_raw', 'rating', 'score', 'stars', 'star'] as $key) {
            $value = data_get($node, $key);

            if (is_numeric($value)) {
                return round((float) $value, 2);
            }
        }

        return null;
    }

    private function inferAuthorType(array $node, string $pathText, array $defaults): string
    {
        $hint = strtolower(implode(' ', array_filter([
            $pathText,
            $this->firstString($node, ['author_type', 'role', 'author_role', 'user_type', 'reviewer_type']),
            $defaults['external_source'] ?? null,
        ])));

        return match (true) {
            str_contains($hint, 'supplier'),
            str_contains($hint, 'seller'),
            str_contains($hint, 'manufacturer') => 'supplier',
            str_contains($hint, 'buyer'),
            str_contains($hint, 'customer'),
            str_contains($hint, 'user'),
            str_contains($hint, 'end_user') => 'end_user',
            default => 'unknown',
        };
    }

    private function inferSourceType(string $pathText, ?string $sourceUrl, string $authorType): string
    {
        $host = strtolower((string) parse_url((string) $sourceUrl, PHP_URL_HOST));
        $sourceHint = strtolower(trim($pathText . ' ' . $host));

        if (str_contains($sourceHint, 'forum')) {
            return 'forum_comment';
        }

        if (str_contains($sourceHint, 'article') || str_contains($sourceHint, 'blog')) {
            return 'article_comment';
        }

        if (str_contains($sourceHint, 'alibaba')) {
            return $authorType === 'supplier' ? 'alibaba_supplier_review' : 'marketplace_review';
        }

        if (str_contains($sourceHint, 'marketplace') || str_contains($sourceHint, 'amazon') || str_contains($sourceHint, 'ebay')) {
            return 'marketplace_review';
        }

        return 'other';
    }

    private function inferCredibilityWeight(string $authorType): float
    {
        return match ($authorType) {
            'end_user' => 0.90,
            'supplier' => 0.45,
            default => 0.65,
        };
    }

    private function dedupeCandidates(array $candidates): array
    {
        $seen = [];
        $results = [];

        foreach ($candidates as $candidate) {
            $signature = sha1(mb_strtolower(trim(($candidate['title'] ?? '') . ' ' . ($candidate['body_text'] ?? '') . ' ' . ($candidate['source_item_id'] ?? ''))));

            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $results[] = $candidate;
        }

        return $results;
    }

    private function firstString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if (!is_scalar($value)) {
                continue;
            }

            $string = trim((string) $value);

            if ($string !== '') {
                return Str::limit($string, 65535, '');
            }
        }

        return null;
    }

    private function normalizeTextList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,;\n]+/', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($item) {
            if (!is_scalar($item)) {
                return null;
            }

            $string = trim((string) $item);

            return $string === '' ? null : $string;
        }, $value)));
    }
}
