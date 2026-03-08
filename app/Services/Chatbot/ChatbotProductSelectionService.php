<?php

namespace App\Services\Chatbot;

class ChatbotProductSelectionService
{
    private const MIN_REALISTIC_WIDGET_PRICE = 0.5;

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    public function selectWidgetProductsForResponse(array $products, PipelineResult $pipelineResult): array
    {
        $products = array_values(array_filter(
            $products,
            fn (array $product): bool => $this->productHasRealisticPrice($product)
        ));

        if ($products === []) {
            return [];
        }

        $responseText = $pipelineResult->response();
        $response = mb_strtolower($responseText);
        $intentResult = $pipelineResult->intentResult();
        $intent = $intentResult?->intent();
        $rankedProducts = $this->rankWidgetProductsForResponse($products, $response, $intentResult);
        $explicitMentions = $this->extractExplicitMentions($responseText);
        $preferAlternativeProductsOnly = $this->shouldPreferAlternativeProductsOnly($responseText, $intentResult);

        $explicitlyMentionedProducts = array_values(array_filter(
            $rankedProducts,
            fn (array $product): bool => $this->responseExplicitlyMentionsProduct($explicitMentions, $product['product'])
        ));

        if ($preferAlternativeProductsOnly) {
            $rankedProducts = $this->filterRequestedSubjectProducts($rankedProducts, $intentResult);
            $explicitlyMentionedProducts = $this->filterRequestedSubjectProducts($explicitlyMentionedProducts, $intentResult);
        }

        if ($explicitlyMentionedProducts !== []) {
            return array_map(
                fn (array $product): array => $product['product'],
                array_slice($explicitlyMentionedProducts, 0, 3)
            );
        }

        if (in_array($intent, ['recommendation', 'general'], true) || $intent === null) {
            return [];
        }

        $matchedProducts = array_values(array_filter(
            $rankedProducts,
            fn (array $product): bool => ($product['response_score'] ?? 0) > 0
        ));

        $strongMatchedProducts = array_values(array_filter(
            $matchedProducts,
            fn (array $product): bool => ($product['response_score'] ?? 0) >= 6
        ));

        if ($strongMatchedProducts !== []) {
            return array_map(
                fn (array $product): array => $product['product'],
                array_slice($strongMatchedProducts, 0, 3)
            );
        }

        if (in_array($intent, ['price_query', 'stock_query', 'features'], true)) {
            $topIntentMatch = $this->selectTopIntentMatchedProduct($rankedProducts, $intentResult);

            return $topIntentMatch === null ? [] : [$topIntentMatch];
        }

        return [];
    }

    public function shouldIncludeWidgetProducts(PipelineResult $pipelineResult): bool
    {
        if (!$pipelineResult->validationPassed() || !$pipelineResult->georgianPassed()) {
            return false;
        }

        if ($pipelineResult->fallbackReason() !== null) {
            return false;
        }

        if ($pipelineResult->regenerationAttempted() && !$pipelineResult->regenerationSucceeded()) {
            return false;
        }

        $intentResult = $pipelineResult->intentResult();

        if (in_array($intentResult?->intent(), ['clarification_needed', 'out_of_domain'], true)) {
            return false;
        }

        if ($this->responseSignalsNoProductCards($pipelineResult->response())) {
            return false;
        }

        return !$this->responseSignalsUncertainFallback($pipelineResult->response());
    }

    /**
     * @param array<int, array<string, string>> $products
     * @return array<int, array<string, string>>
     */
    public function selectDiscoveryProductsForCarousel(array $products, string $query): array
    {
        if ($products === []) {
            return [];
        }

        if ($this->queryLooksProductSpecific($query, $products)) {
            return [];
        }

        return array_values(array_slice($products, 0, 10));
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array{product: array<string, mixed>, response_score: int, intent_score: int}>
     */
    private function rankWidgetProductsForResponse(array $products, string $response, ?IntentResult $intentResult): array
    {
        $rankedProducts = array_map(function (array $product) use ($response, $intentResult): array {
            return [
                'product' => $product,
                'response_score' => $this->responseMentionScore($response, $product),
                'intent_score' => $this->intentProductScore($product, $intentResult),
            ];
        }, $products);

        usort($rankedProducts, function (array $left, array $right): int {
            if ($left['response_score'] !== $right['response_score']) {
                return $right['response_score'] <=> $left['response_score'];
            }

            if ($left['intent_score'] !== $right['intent_score']) {
                return $right['intent_score'] <=> $left['intent_score'];
            }

            return 0;
        });

        return $rankedProducts;
    }

    /**
     * @param array<int, array{product: array<string, mixed>, response_score: int, intent_score: int}> $rankedProducts
     * @return array<string, mixed>|null
     */
    private function selectTopIntentMatchedProduct(array $rankedProducts, ?IntentResult $intentResult): ?array
    {
        if ($intentResult === null || $rankedProducts === []) {
            return null;
        }

        $topProduct = $rankedProducts[0];
        $nextProduct = $rankedProducts[1] ?? null;

        if (($topProduct['intent_score'] ?? 0) < 8) {
            return null;
        }

        if ($nextProduct !== null && ($nextProduct['intent_score'] ?? 0) >= (($topProduct['intent_score'] ?? 0) - 2)) {
            return null;
        }

        return $topProduct['product'];
    }

    /**
     * @param array<string, mixed> $product
     */
    private function responseMentionScore(string $response, array $product): int
    {
        $name = mb_strtolower(trim((string) ($product['name'] ?? '')));
        $slug = mb_strtolower(trim((string) ($product['slug'] ?? '')));

        if ($name !== '' && str_contains($response, $name)) {
            return 10;
        }

        if ($slug !== '' && str_contains($response, $slug)) {
            return 9;
        }

        $distinctiveTokens = collect([
            ...preg_split('/[-\s]+/u', $slug) ?: [],
            ...preg_split('/[-\s]+/u', $name) ?: [],
        ])
            ->filter(fn ($token) => is_string($token) && $token !== '')
            ->map(fn ($token) => mb_strtolower(trim((string) $token)))
            ->filter(fn (string $token): bool => $this->isDistinctiveProductToken($token))
            ->unique()
            ->values();

        if ($distinctiveTokens->isEmpty()) {
            return 0;
        }

        $matchedTokens = $distinctiveTokens
            ->filter(fn (string $token): bool => str_contains($response, $token))
            ->count();

        if ($distinctiveTokens->count() === 1) {
            return $matchedTokens === 1 ? 6 : 0;
        }

        return $matchedTokens >= 2 ? $matchedTokens + 4 : 0;
    }

    /**
     * @param array<string, mixed> $product
     */
    private function responseExplicitlyMentionsProduct(array $explicitMentions, array $product): bool
    {
        if ($explicitMentions === []) {
            return false;
        }

        $aliases = collect($this->explicitResponseAliases($product))
            ->map(fn (string $alias): string => $this->normalizeProductText($alias))
            ->filter()
            ->unique()
            ->values();

        return $aliases->contains(
            fn (string $alias): bool => $alias !== ''
                && collect($explicitMentions)->contains(
                    fn (string $mention): bool => $mention === $alias || $this->productTextContains($mention, $alias)
                )
        );
    }

    /**
     * @return array<int, string>
     */
    private function extractExplicitMentions(string $response): array
    {
        $mentions = [];

        if (preg_match_all('/\*\*(.+?)\*\*/u', $response, $boldMatches) === 1 || (!empty($boldMatches[1]) && is_array($boldMatches[1]))) {
            foreach ($boldMatches[1] ?? [] as $match) {
                $mentions[] = $this->normalizeProductText((string) $match);
            }
        }

        if (preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/u', $response, $linkMatches, PREG_SET_ORDER) === 1 || (!empty($linkMatches) && is_array($linkMatches))) {
            foreach ($linkMatches ?? [] as $match) {
                $mentions[] = $this->normalizeProductText((string) ($match[1] ?? ''));
                $mentions[] = $this->normalizeProductText($this->slugFromProductUrl((string) ($match[2] ?? '')));
            }
        }

        return array_values(array_filter(array_unique($mentions)));
    }

    /**
     * @param array<string, mixed> $product
     */
    private function intentProductScore(array $product, ?IntentResult $intentResult): int
    {
        if ($intentResult === null) {
            return 0;
        }

        $name = $this->normalizeProductText((string) ($product['name'] ?? ''));
        $slug = $this->normalizeProductText((string) ($product['slug'] ?? ''));
        $productText = trim($name . ' ' . $slug);

        if ($productText === '') {
            return 0;
        }

        $score = 0;
        $brand = $this->normalizeProductText((string) ($intentResult->brand() ?? ''));
        $model = $this->normalizeProductText((string) ($intentResult->model() ?? ''));
        $slugHint = $this->normalizeProductText((string) ($intentResult->productSlugHint() ?? ''));
        $targetPhrase = trim($brand . ' ' . $model);

        $brandMatched = $brand !== '' && $this->productTextContains($productText, $brand);
        $modelMatched = $model !== '' && $this->productTextContains($productText, $model);
        $slugMatched = $slugHint !== '' && ($slug === $slugHint || $name === $slugHint);

        if ($slugMatched) {
            $score += 10;
        }

        if ($targetPhrase !== '') {
            if ($name === $targetPhrase || $slug === $targetPhrase) {
                $score += 8;
            } elseif ($this->productTextContains($productText, $targetPhrase)) {
                $score += 3;
            }
        }

        if ($intentResult->hasSpecificProduct() && !$slugMatched && !$modelMatched) {
            return 0;
        }

        if ($brand !== '' && !$brandMatched && !$slugMatched) {
            return 0;
        }

        if ($brandMatched) {
            $score += 3;
        }

        if ($modelMatched) {
            $score += 5;
        }

        foreach ($intentResult->searchKeywords() as $keyword) {
            $normalizedKeyword = $this->normalizeProductText((string) $keyword);

            if ($normalizedKeyword !== '' && mb_strlen($normalizedKeyword) >= 4 && $this->productTextContains($productText, $normalizedKeyword)) {
                $score += 1;
            }
        }

        return $score;
    }

    private function responseSignalsUncertainFallback(string $response): bool
    {
        $normalized = mb_strtolower($response);

        foreach ([
            'გადამოწმებას საჭიროებს',
            'დაგვიკავშირდეთ',
            'დაუყოვნებლივ დაგიზუსტებთ',
            'რომელი კონკრეტული მოდელი',
            'მითხარით რომელი მოდელი ან ფუნქცია',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function responseSignalsNoProductCards(string $response): bool
    {
        $normalized = mb_strtolower($response);

        foreach ([
            'არ გვაქვს',
            'არ მოიპოვება',
            'კატალოგი ამ ეტაპზე ძირითადად საბავშვო',
            'ზრდასრულის smartwatch-ები არ გვაქვს',
            'ზრდასრულის სმარტსაათები არ გვაქვს',
            'მხოლოდ საბავშვო',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, string>> $products
     */
    private function queryLooksProductSpecific(string $query, array $products): bool
    {
        $normalizedQuery = $this->normalizeProductText($query);

        if ($normalizedQuery === '') {
            return false;
        }

        $strongMatches = 0;

        foreach ($products as $product) {
            $score = $this->discoveryQueryProductScore($normalizedQuery, $product);

            if ($score >= 8) {
                return true;
            }

            if ($score >= 4) {
                $strongMatches++;
            }
        }

        return $strongMatches >= 2;
    }

    /**
     * @param array<string, string> $product
     */
    private function discoveryQueryProductScore(string $query, array $product): int
    {
        $title = $this->normalizeProductText((string) ($product['title'] ?? ''));
        $slug = $this->normalizeProductText($this->slugFromProductUrl((string) ($product['product_url'] ?? '')));

        if ($title !== '' && $this->productTextContains($query, $title)) {
            return 10;
        }

        if ($slug !== '' && $this->productTextContains($query, $slug)) {
            return 9;
        }

        $titleTokens = collect(preg_split('/\s+/u', $title))
            ->filter(fn ($token) => is_string($token) && mb_strlen($token) >= 4)
            ->values();

        $slugTokens = collect(preg_split('/\s+/u', $slug))
            ->filter(fn ($token) => is_string($token) && mb_strlen($token) >= 4)
            ->values();

        $matchedTitleTokens = $titleTokens
            ->filter(fn (string $token): bool => $this->productTextContains($query, $token))
            ->count();

        if ($matchedTitleTokens >= 2) {
            return 8;
        }

        if ($matchedTitleTokens === 1) {
            return 4;
        }

        $matchedSlugTokens = $slugTokens
            ->filter(fn (string $token): bool => $this->productTextContains($query, $token))
            ->count();

        return $matchedSlugTokens >= 2 ? 6 : ($matchedSlugTokens === 1 ? 4 : 0);
    }

    private function normalizeProductText(string $value): string
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value));

        return trim((string) $normalized);
    }

    /**
     * @param array<string, mixed> $product
     * @return array<int, string>
     */
    private function explicitResponseAliases(array $product): array
    {
        $aliases = [];
        $name = trim((string) ($product['name'] ?? ''));
        $slug = trim((string) ($product['slug'] ?? ''));

        if ($name !== '') {
            $aliases[] = $name;
        }

        if ($slug !== '') {
            $aliases[] = str_replace('-', ' ', $slug);
            $aliases[] = $slug;
        }

        foreach ([$name, str_replace('-', ' ', $slug)] as $source) {
            $aliases = array_merge($aliases, $this->modelBasedAliases($source));
        }

        return array_values(array_filter(array_unique($aliases)));
    }

    /**
     * @return array<int, string>
     */
    private function modelBasedAliases(string $source): array
    {
        $tokens = collect(preg_split('/[^\p{L}\p{N}]+/u', $source) ?: [])
            ->filter(fn ($token): bool => is_string($token) && trim($token) !== '')
            ->map(fn ($token): string => trim((string) $token))
            ->values();

        $aliases = [];

        foreach ($tokens as $index => $token) {
            $normalizedToken = mb_strtolower($token);

            if (preg_match('/(?=.*\d)(?=.*\p{L})/u', $normalizedToken) !== 1 || !$this->isDistinctiveProductToken($normalizedToken)) {
                continue;
            }

            $aliases[] = $token;

            $previousToken = $tokens->get($index - 1);
            if (is_string($previousToken) && $this->isBrandLikeToken($previousToken)) {
                $aliases[] = $previousToken . ' ' . $token;
            }
        }

        return array_values(array_filter(array_unique($aliases)));
    }

    private function productHasRealisticPrice(array $product): bool
    {
        $salePrice = $product['sale_price'] ?? null;
        $price = $product['price'] ?? null;
        $effectivePrice = is_numeric($salePrice) && (float) $salePrice > 0
            ? (float) $salePrice
            : (is_numeric($price) ? (float) $price : null);

        return $effectivePrice !== null && $effectivePrice >= self::MIN_REALISTIC_WIDGET_PRICE;
    }

    private function isDistinctiveProductToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        if (in_array($token, [
            'smart', 'watch', 'kids', 'kid', 'child', 'children', 'gps', 'sos', 'call', 'video', 'network',
            'waterproof', 'android', 'tracker', 'anti', 'lost', 'camera', 'phone', 'color', 'sim', '4g', '2g',
        ], true)) {
            return false;
        }

        if (preg_match('/\d/', $token) === 1) {
            return mb_strlen($token) >= 3;
        }

        return mb_strlen($token) >= 4;
    }

    private function isBrandLikeToken(string $token): bool
    {
        $normalized = mb_strtolower(trim($token));

        if ($normalized === '') {
            return false;
        }

        if (preg_match('/\d/', $normalized) === 1) {
            return false;
        }

        return mb_strlen($normalized) >= 4;
    }

    private function productTextContains(string $haystack, string $needle): bool
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        return str_contains(' ' . $haystack . ' ', ' ' . $needle . ' ');
    }

    private function slugFromProductUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);

        return (string) end($segments);
    }

    private function shouldPreferAlternativeProductsOnly(string $responseText, ?IntentResult $intentResult): bool
    {
        if ($intentResult === null) {
            return false;
        }

        if (trim((string) ($intentResult->model() ?? '')) === '' && trim((string) ($intentResult->productSlugHint() ?? '')) === '') {
            return false;
        }

        $normalized = mb_strtolower($responseText);

        foreach (['მსგავსი', 'ალტერნატივ', 'სხვა'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{product: array<string, mixed>, response_score: int, intent_score: int}> $rankedProducts
     * @return array<int, array{product: array<string, mixed>, response_score: int, intent_score: int}>
     */
    private function filterRequestedSubjectProducts(array $rankedProducts, ?IntentResult $intentResult): array
    {
        return array_values(array_filter(
            $rankedProducts,
            fn (array $ranked): bool => !$this->productMatchesRequestedSubject($ranked['product'], $intentResult)
        ));
    }

    /**
     * @param array<string, mixed> $product
     */
    private function productMatchesRequestedSubject(array $product, ?IntentResult $intentResult): bool
    {
        if ($intentResult === null) {
            return false;
        }

        $model = $this->normalizeProductText((string) ($intentResult->model() ?? ''));
        $slugHint = $this->normalizeProductText((string) ($intentResult->productSlugHint() ?? ''));
        $name = $this->normalizeProductText((string) ($product['name'] ?? ''));
        $slug = $this->normalizeProductText((string) ($product['slug'] ?? ''));

        if ($slugHint !== '' && ($slug === $slugHint || $this->productTextContains($slug, $slugHint))) {
            return true;
        }

        if ($model !== '' && ($this->productTextContains($name, $model) || $this->productTextContains($slug, $model))) {
            return true;
        }

        return false;
    }
}
