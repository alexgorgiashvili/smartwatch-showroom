<?php

namespace App\Services\Chatbot;

class IntentResult
{
    public function __construct(
        private string $standaloneQuery,
        private string $intent,
        private ?string $brand,
        private ?string $model,
        private ?string $productSlugHint,
        private ?string $color,
        private ?string $category,
        private bool $needsProductData,
        private array $searchKeywords,
        private bool $isOutOfDomain,
        private float $confidence,
        private int $latencyMs,
        private bool $isFallback = false
    ) {
    }

    public static function fromArray(array $data, int $latencyMs): self
    {
        $entities = is_array($data['entities'] ?? null) ? $data['entities'] : [];

        $standaloneQuery = trim((string) ($data['standalone_query'] ?? ''));
        if ($standaloneQuery === '') {
            $standaloneQuery = trim((string) ($data['refined_query'] ?? ''));
        }

        $intent = trim((string) ($data['intent'] ?? 'general'));
        if ($intent === '') {
            $intent = 'general';
        }

        $searchKeywords = collect($data['search_keywords'] ?? [])
            ->filter(fn ($keyword) => is_string($keyword) && trim($keyword) !== '')
            ->map(fn (string $keyword) => trim($keyword))
            ->values()
            ->all();

        return new self(
            $standaloneQuery,
            $intent,
            self::normalizeNullableString($entities['brand'] ?? null),
            self::normalizeNullableString($entities['model'] ?? null),
            self::normalizeNullableString($entities['product_slug_hint'] ?? null),
            self::normalizeNullableString($entities['color'] ?? null),
            self::normalizeNullableString($entities['category'] ?? null),
            (bool) ($data['needs_product_data'] ?? true),
            $searchKeywords,
            (bool) ($data['is_out_of_domain'] ?? false),
            (float) ($data['confidence'] ?? 0.0),
            max(0, $latencyMs),
            false
        );
    }

    public static function fallback(string $rawMessage): self
    {
        $normalized = trim($rawMessage);

        return new self(
            $normalized,
            'general',
            null,
            null,
            null,
            null,
            null,
            true,
            [],
            false,
            0.0,
            0,
            true
        );
    }

    public function standaloneQuery(): string
    {
        return $this->standaloneQuery;
    }

    public function intent(): string
    {
        return $this->intent;
    }

    public function brand(): ?string
    {
        return $this->brand;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function productSlugHint(): ?string
    {
        return $this->productSlugHint;
    }

    public function color(): ?string
    {
        return $this->color;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function needsProductData(): bool
    {
        return $this->needsProductData;
    }

    public function searchKeywords(): array
    {
        return $this->searchKeywords;
    }

    public function isOutOfDomain(): bool
    {
        return $this->isOutOfDomain;
    }

    public function confidence(): float
    {
        return $this->confidence;
    }

    public function latencyMs(): int
    {
        return $this->latencyMs;
    }

    public function isFallback(): bool
    {
        return $this->isFallback;
    }

    public function requiresSearch(): bool
    {
        return $this->needsProductData && !$this->isOutOfDomain;
    }

    public function hasCatalogFacet(): bool
    {
        return $this->mentionsTwoGCatalog()
            || $this->mentionsFourGCatalog()
            || $this->mentionsDiscountCatalog();
    }

    public function mentionsTwoGCatalog(): bool
    {
        return $this->mentionsGenerationCatalog('2g');
    }

    public function mentionsFourGCatalog(): bool
    {
        return $this->mentionsGenerationCatalog('4g');
    }

    public function mentionsDiscountCatalog(): bool
    {
        $text = $this->catalogFacetText();

        if ($text === '') {
            return false;
        }

        return $this->containsAnyFacetNeedle($text, [
            'ფასდაკლ',
            'discount',
            'sale',
            'offer',
            'reduc',
        ]);
    }

    public function hasSpecificProduct(): bool
    {
        return $this->brand !== null && $this->model !== null;
    }

    private function mentionsGenerationCatalog(string $generation): bool
    {
        $text = $this->catalogFacetText();

        if ($text === '') {
            return false;
        }

        $patterns = $generation === '2g'
            ? [
                '/(?:^|\s)2\s*g(?:\s|$)/u',
                '/(?:^|\s)2\s*გ(?:\s|$)/u',
                '/(?:^|\s)2გ(?:\s|$)/u',
            ]
            : [
                '/(?:^|\s)4\s*g(?:\s|$)/u',
                '/(?:^|\s)4\s*გ(?:\s|$)/u',
                '/(?:^|\s)4გ(?:\s|$)/u',
            ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private function catalogFacetText(): string
    {
        $parts = array_filter([
            $this->standaloneQuery,
            $this->category ?? '',
            implode(' ', $this->searchKeywords),
        ], fn (mixed $part): bool => is_string($part) && trim($part) !== '');

        if ($parts === []) {
            return '';
        }

        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(implode(' ', $parts)));

        return trim((string) $normalized);
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAnyFacetNeedle(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (!is_string($needle) || trim($needle) === '') {
                continue;
            }

            if (mb_stripos($haystack, mb_strtolower(trim($needle))) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
