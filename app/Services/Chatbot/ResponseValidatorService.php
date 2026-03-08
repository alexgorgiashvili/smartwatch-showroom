<?php

namespace App\Services\Chatbot;

class ResponseValidatorService
{
    public function validatePriceIntegrity(string $response, array $ragContext): ValidationResult
    {
        $mentionedPrices = $this->extractPrices($response);
        $budgetPrices = $this->extractBudgetPrices($response);

        if ($mentionedPrices === []) {
            return ValidationResult::pass();
        }

        $allowedPrices = $this->collectAllowedPrices($ragContext);

        if ($allowedPrices === []) {
            return ValidationResult::pass();
        }

        $violations = [];
        $minAllowed = min($allowedPrices);
        $maxAllowed = max($allowedPrices);

        foreach ($mentionedPrices as $mentionedPrice) {
            if (in_array($mentionedPrice, $budgetPrices, true)) {
                continue;
            }

            $matchesKnown = collect($allowedPrices)->contains(function (float $allowedPrice) use ($mentionedPrice): bool {
                $tolerance = max($allowedPrice * 0.01, 0.01);

                return abs($mentionedPrice - $allowedPrice) <= $tolerance;
            });

            if (!$matchesKnown && $this->isWithinReasonableCatalogRange($mentionedPrice, $minAllowed, $maxAllowed)) {
                continue;
            }

            if (!$matchesKnown) {
                $violations[] = [
                    'type' => 'price_mismatch',
                    'price' => $mentionedPrice,
                ];
            }
        }

        return $violations === [] ? ValidationResult::pass() : ValidationResult::fail($violations);
    }

    public function validateStockClaims(string $response, array $ragContext): ValidationResult
    {
        $normalized = mb_strtolower($response);
        $products = $ragContext['products'] ?? [];

        if (!is_array($products) || $products === []) {
            return ValidationResult::pass();
        }

        $hasInStock = collect($products)->contains(fn (array $product): bool => (bool) ($product['is_in_stock'] ?? false));
        $hasOutOfStock = collect($products)->contains(fn (array $product): bool => !((bool) ($product['is_in_stock'] ?? false)));

        $positiveClaims = ['მარაგშია', 'ხელმისაწვდომია', 'in stock'];
        $negativeClaims = ['ამოწურულია', 'არ არის მარაგში', 'out of stock'];

        $mentionsPositive = collect($positiveClaims)->contains(fn (string $term): bool => str_contains($normalized, $term));
        $mentionsNegative = collect($negativeClaims)->contains(fn (string $term): bool => str_contains($normalized, $term));

        $violations = [];

        if ($mentionsPositive && !$hasInStock) {
            $violations[] = [
                'type' => 'stock_claim_mismatch',
                'claim' => 'in_stock',
            ];
        }

        if ($mentionsNegative && !$hasOutOfStock) {
            $violations[] = [
                'type' => 'stock_claim_mismatch',
                'claim' => 'out_of_stock',
            ];
        }

        return $violations === [] ? ValidationResult::pass() : ValidationResult::fail($violations);
    }

    public function validateUrls(string $response, array $ragContext): ValidationResult
    {
        preg_match_all('/https?:\/\/[^\s\)\]]+/iu', $response, $matches);
        $urls = collect($matches[0] ?? [])->map(fn (string $url): string => rtrim($url, ".,;!?\"'"))->unique()->values()->all();

        $markdownLinks = [];
        if (preg_match_all('/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/iu', $response, $linkMatches, PREG_SET_ORDER)) {
            $markdownLinks = $linkMatches;
        }

        if ($urls === [] && $markdownLinks === []) {
            return ValidationResult::pass();
        }

        $allowedUrls = collect($ragContext['allowed_urls'] ?? [])->filter()->map(fn ($url) => rtrim((string) $url, '/'))->unique()->values();
        $allowedPaths = $allowedUrls
            ->map(fn (string $url): string => rtrim((string) parse_url($url, PHP_URL_PATH), '/'))
            ->filter()
            ->unique()
            ->values();

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $violations = [];

        foreach ($urls as $url) {
            $normalizedUrl = rtrim($url, '/');
            $host = parse_url($normalizedUrl, PHP_URL_HOST);
            $path = rtrim((string) parse_url($normalizedUrl, PHP_URL_PATH), '/');

            if (str_starts_with($path, '/products/')) {
                if ($this->isLocalHost($host) || !$host || ($appHost && mb_strtolower($host) === mb_strtolower((string) $appHost))) {
                    continue;
                }
            }

            if ($this->isLocalHost($host) || ($appHost && $host && mb_strtolower($host) === mb_strtolower((string) $appHost))) {
                if ($path === '' || $allowedPaths->contains($path)) {
                    continue;
                }
            }

            if ($path !== '' && $allowedPaths->contains($path)) {
                continue;
            }

            if ($appHost && $host && !$this->isLocalHost($host) && mb_strtolower($host) !== mb_strtolower((string) $appHost)) {
                $violations[] = [
                    'type' => 'unknown_url_host',
                    'url' => $url,
                ];
                continue;
            }

            if (!$allowedUrls->contains($normalizedUrl)) {
                $violations[] = [
                    'type' => 'unknown_url_path',
                    'url' => $url,
                ];
            }
        }

        foreach ($markdownLinks as $linkMatch) {
            $label = trim((string) ($linkMatch[1] ?? ''));
            $url = rtrim((string) ($linkMatch[2] ?? ''), '/');
            $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');

            if ($label !== '' && preg_match('/\b[A-Za-z]{0,4}\d{1,4}\b/u', $label) === 1 && $path === '/products') {
                $violations[] = [
                    'type' => 'misleading_product_link',
                    'url' => $url,
                    'label' => $label,
                ];
            }
        }

        return $violations === [] ? ValidationResult::pass() : ValidationResult::fail($violations);
    }

    public function validateAll(string $response, array $ragContext, ?IntentResult $intentResult = null): ValidationResult
    {
        $results = [];

        if ($this->shouldEnforceStrictPriceIntegrity($intentResult)) {
            $results[] = $this->validatePriceIntegrity($response, $ragContext);
        }

        $results[] = $this->validateStockClaims($response, $ragContext);
        $results[] = $this->validateUrls($response, $ragContext);

        $violations = collect($results)
            ->filter(fn (ValidationResult $result): bool => !$result->isValid())
            ->flatMap(fn (ValidationResult $result): array => $result->violations())
            ->values()
            ->all();

        return $violations === [] ? ValidationResult::pass() : ValidationResult::fail($violations);
    }

    public function integrityFallback(): string
    {
        return 'ზუსტი ფასი და მარაგი ამ მომენტში დამატებით გადამოწმებას საჭიროებს. გთხოვთ, დაგვიკავშირდეთ და დაუყოვნებლივ დაგიზუსტებთ ინფორმაციას.';
    }

    private function shouldEnforceStrictPriceIntegrity(?IntentResult $intentResult): bool
    {
        if (!$intentResult instanceof IntentResult) {
            return true;
        }

        if ($intentResult->hasSpecificProduct()) {
            return true;
        }

        return in_array($intentResult->intent(), ['price_query', 'stock_query', 'comparison'], true);
    }

    private function extractPrices(string $text): array
    {
        preg_match_all('/(\d+(?:[\.,]\d+)?)\s*(?:₾|ლარ(?:ი|ამდე)?|lari|gel)/iu', $text, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $value): float => (float) str_replace(',', '.', $value))
            ->filter(fn (float $value): bool => $value >= 20)
            ->unique()
            ->values()
            ->all();
    }

    private function extractBudgetPrices(string $text): array
    {
        $patterns = [
            '/(\d+(?:[\.,]\d+)?)\s*(?:₾\s*-?\s*მდე|ლარამდე|lari\s*(?:up\s+to|or\s+less)|gel\s*(?:up\s+to|or\s+less))/iu',
            '/(\d+(?:[\.,]\d+)?)\s*(?:₾|ლარი|lari|gel)\s*(?:მდე|ფარგლებ(?:ში)?|under|below|up\s+to|within)/iu',
            '/(?:ბიუჯეტ(?:ი|ში|ად)?|ფარგლებ(?:ში)?)\D{0,20}?(\d+(?:[\.,]\d+)?)\s*(?:₾|ლარი|lari|gel)?/iu',
        ];

        $values = [];

        foreach ($patterns as $pattern) {
            $matches = [];
            preg_match_all($pattern, $text, $matches);

            foreach ($matches[1] ?? [] as $value) {
                $values[] = (float) str_replace(',', '.', (string) $value);
            }
        }

        return collect($values)
            ->filter(fn (float $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function isWithinReasonableCatalogRange(float $price, float $minAllowed, float $maxAllowed): bool
    {
        if ($minAllowed <= 0 || $maxAllowed <= 0) {
            return false;
        }

        $lowerBound = $minAllowed * 0.7;
        $upperBound = $maxAllowed * 1.3;

        return $price >= $lowerBound && $price <= $upperBound;
    }

    private function isLocalHost(?string $host): bool
    {
        if (!$host) {
            return false;
        }

        $normalized = mb_strtolower($host);

        return in_array($normalized, ['localhost', '127.0.0.1', '::1'], true);
    }

    private function collectAllowedPrices(array $ragContext): array
    {
        $products = $ragContext['products'] ?? [];

        if (!is_array($products)) {
            return [];
        }

        $prices = [];

        foreach ($products as $product) {
            $price = $product['price'] ?? null;
            $salePrice = $product['sale_price'] ?? null;

            if (is_numeric($price)) {
                $prices[] = (float) $price;
            }

            if (is_numeric($salePrice)) {
                $prices[] = (float) $salePrice;
            }
        }

        return collect($prices)
            ->filter(fn (float $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }
}
