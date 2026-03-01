<?php

namespace App\Services;

use App\Services\Contracts\CompetitorCategoryScraper;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AltaCategoryScraperService implements CompetitorCategoryScraper
{
    public function scrapeCategory(string $categoryUrl): array
    {
        $normalizedCategoryUrl = $this->normalizeCategoryUrl($categoryUrl);
        $html = $this->fetchHtml($normalizedCategoryUrl);

        $items = $this->extractProductsFromHtml($html);

        $deduped = [];
        foreach ($items as $item) {
            $productUrl = trim((string) ($item['product_url'] ?? ''));
            if ($productUrl === '') {
                continue;
            }

            $deduped[$productUrl] = $item;
        }

        return array_values($deduped);
    }

    private function fetchHtml(string $url): string
    {
        $request = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9,ka;q=0.8',
            'Referer' => 'https://alta.ge/',
        ])->timeout(30);

        try {
            $response = $request->get($url);
        } catch (\Throwable $exception) {
            if (!$this->isSslCertificateError($exception)) {
                throw $exception;
            }

            $response = $request
                ->withoutVerifying()
                ->get($url);
        }

        if (!$response->successful()) {
            if ($this->isCloudflareChallenge($response)) {
                return $this->fetchViaScrapingBee($url);
            }

            throw new \RuntimeException('Failed to fetch alta category page. HTTP ' . $response->status() . '.');
        }

        return (string) $response->body();
    }

    private function isCloudflareChallenge(Response $response): bool
    {
        if ($response->status() !== 403) {
            return false;
        }

        $cfMitigated = mb_strtolower((string) $response->header('cf-mitigated'));
        if (str_contains($cfMitigated, 'challenge')) {
            return true;
        }

        $body = mb_strtolower((string) $response->body());

        return str_contains($body, 'just a moment')
            || str_contains($body, 'performing security verification')
            || str_contains($body, 'cloudflare');
    }

    private function fetchViaScrapingBee(string $url): string
    {
        $apiKey = trim((string) config('services.scrapingbee.api_key'));
        if ($apiKey === '') {
            throw new \RuntimeException(
                'Alta blocked server scraping with Cloudflare challenge. Add SCRAPINGBEE_API_KEY to enable proxy rendering for alta.ge sources.'
            );
        }

        $query = [
            'api_key' => $apiKey,
            'url' => $url,
            'render_js' => config('services.scrapingbee.render_js', true) ? 'true' : 'false',
            'premium_proxy' => config('services.scrapingbee.premium_proxy', true) ? 'true' : 'false',
        ];

        $countryCode = trim((string) config('services.scrapingbee.country_code'));
        if ($countryCode !== '') {
            $query['country_code'] = $countryCode;
        }

        $response = Http::timeout((int) config('services.scrapingbee.timeout', 60))
            ->get((string) config('services.scrapingbee.base_url', 'https://app.scrapingbee.com/api/v1/'), $query);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to fetch alta category page through ScrapingBee. HTTP ' . $response->status() . '.');
        }

        return (string) $response->body();
    }

    private function isSslCertificateError(\Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'curl error 60')
            || str_contains($message, 'ssl certificate')
            || str_contains($message, 'unable to get local issuer certificate')
            || str_contains($message, 'certificate verify failed');
    }

    private function extractProductsFromHtml(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $normalizedHtml = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8">' . $normalizedHtml, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);
        $anchors = $xpath->query('//a[contains(@href, "-p") and contains(@href, "alta.ge")]');
        if (!$anchors) {
            return [];
        }

        $items = [];

        foreach ($anchors as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }

            $href = trim((string) $anchor->getAttribute('href'));
            if (!$this->isProductUrl($href)) {
                continue;
            }

            $productUrl = $this->toAbsoluteUrl($href);
            $title = $this->cleanText($anchor->textContent);
            if ($title === '' || mb_strlen($title) < 2) {
                $title = $this->extractTitleFromUrl($productUrl);
            }

            $container = $this->findCardContainer($anchor);
            $contextText = $container ? $this->cleanText($container->textContent) : $title;

            [$currentPrice, $oldPrice] = $this->extractPrices($contextText);
            $imageUrl = $container ? $this->extractImageFromContainer($xpath, $container) : null;

            $items[] = [
                'external_product_id' => $this->extractExternalProductId($productUrl),
                'product_url' => $productUrl,
                'title' => $title,
                'image_url' => $imageUrl,
                'current_price' => $currentPrice,
                'old_price' => $oldPrice,
                'currency' => 'GEL',
                'availability' => 'Unknown',
                'is_in_stock' => null,
            ];
        }

        return $items;
    }

    private function isProductUrl(string $url): bool
    {
        $candidate = $this->toAbsoluteUrl($url);

        if (!str_starts_with($candidate, 'https://alta.ge/') && !str_starts_with($candidate, 'http://alta.ge/')) {
            return false;
        }

        if (str_contains($candidate, '/topic/') || str_contains($candidate, '/search/') || str_contains($candidate, '/branches')) {
            return false;
        }

        return preg_match('/-p\d+(?:\?|$)/i', $candidate) === 1;
    }

    private function extractExternalProductId(string $productUrl): ?string
    {
        if (preg_match('/-p(\d+)(?:\?|$)/i', $productUrl, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private function extractTitleFromUrl(string $productUrl): string
    {
        $path = (string) parse_url($productUrl, PHP_URL_PATH);
        $slug = trim((string) basename($path));
        if ($slug === '') {
            return 'Alta product';
        }

        $slug = preg_replace('/-p\d+$/i', '', $slug) ?? $slug;
        $slug = str_replace('-', ' ', $slug);

        return trim(ucwords($slug));
    }

    private function findCardContainer(DOMElement $node): ?DOMElement
    {
        $current = $node;

        while ($current instanceof DOMElement) {
            if ($current->tagName === 'li' || $current->tagName === 'article') {
                return $current;
            }

            $current = $current->parentNode instanceof DOMElement ? $current->parentNode : null;
        }

        return null;
    }

    private function extractImageFromContainer(DOMXPath $xpath, DOMElement $container): ?string
    {
        $img = $xpath->query('.//img[1]', $container)?->item(0);
        if (!$img instanceof DOMElement) {
            return null;
        }

        $src = trim((string) ($img->getAttribute('src') ?: $img->getAttribute('data-src')));
        if ($src === '' || str_starts_with($src, 'data:image')) {
            return null;
        }

        return $this->toAbsoluteUrl($src);
    }

    private function extractPrices(string $text): array
    {
        preg_match_all('/([0-9][0-9\s\.,]*)\s*₾/u', $text, $matches);

        $values = [];
        foreach ($matches[1] ?? [] as $match) {
            $parsed = $this->parsePrice($match);
            if ($parsed !== null) {
                $values[] = $parsed;
            }
        }

        $values = array_values(array_unique($values));

        if (count($values) >= 2) {
            sort($values);
            $current = $values[0];
            $old = $values[count($values) - 1];

            return [$current, $old === $current ? null : $old];
        }

        if (count($values) === 1) {
            return [$values[0], null];
        }

        return [null, null];
    }

    private function parsePrice(string $value): ?float
    {
        $candidate = trim($value);
        if ($candidate === '') {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $candidate);
        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function toAbsoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return 'https://alta.ge/' . ltrim($url, '/');
    }

    private function normalizeCategoryUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw new \InvalidArgumentException('Category URL is required.');
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . ltrim($url, '/');
        }

        return rtrim($url, '/');
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }
}
