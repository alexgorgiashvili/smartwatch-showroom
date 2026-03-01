<?php

namespace App\Services;

use App\Services\Contracts\CompetitorCategoryScraper;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;

class IMobileCategoryScraperService implements CompetitorCategoryScraper
{
    public function scrapeCategory(string $categoryUrl): array
    {
        $normalizedCategoryUrl = $this->normalizeCategoryUrl($categoryUrl);
        $firstPageHtml = $this->fetchHtml($normalizedCategoryUrl);
        $maxPage = $this->detectMaxPage($firstPageHtml);

        $products = $this->extractProductsFromHtml($firstPageHtml);

        for ($page = 2; $page <= $maxPage; $page++) {
            $pageHtml = $this->fetchHtml($this->appendPageQuery($normalizedCategoryUrl, $page));
            $products = array_merge($products, $this->extractProductsFromHtml($pageHtml));
        }

        $deduped = [];
        foreach ($products as $product) {
            $url = (string) ($product['product_url'] ?? '');
            if ($url === '') {
                continue;
            }

            $deduped[$url] = $product;
        }

        return array_values($deduped);
    }

    private function fetchHtml(string $url): string
    {
        $request = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9,ka;q=0.8',
            'Referer' => 'https://i-mobile.ge/',
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
            throw new \RuntimeException('Failed to fetch i-mobile category page.');
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

        $imageAnchors = $xpath->query('//a[contains(@class, "productitem-image") and contains(@href, "/products/")]');
        if (!$imageAnchors) {
            return [];
        }

        $products = [];

        foreach ($imageAnchors as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }

            $relativeUrl = trim((string) $anchor->getAttribute('href'));
            if (!preg_match('/\/products\/(\d+)/', $relativeUrl, $idMatch)) {
                continue;
            }

            $productId = $idMatch[1];
            $productUrl = $this->toAbsoluteUrl($relativeUrl);
            $card = $this->findClosestByClass($anchor, 'card');
            if (!$card) {
                continue;
            }

            $titleNode = $xpath->query('.//h5[contains(@class, "card-title")]', $card)?->item(0);
            $title = $titleNode ? $this->cleanText($titleNode->textContent) : '';
            if ($title === '') {
                continue;
            }

            $priceContainer = $xpath->query('.//div[contains(@class, "my-1") and contains(@class, "my-md-2")]', $card)?->item(0);
            $currentPrice = null;
            $oldPrice = null;

            if ($priceContainer instanceof DOMElement) {
                $currentPriceNode = $xpath->query('.//span[contains(@class, "font-weight-bold")]', $priceContainer)?->item(0);
                if ($currentPriceNode) {
                    $currentPrice = $this->parsePrice($currentPriceNode->textContent);
                }

                $oldPriceNode = $xpath->query('.//span[contains(@class, "custom-old-price")]', $priceContainer)?->item(0);
                if ($oldPriceNode) {
                    $oldPrice = $this->parsePrice($oldPriceNode->textContent);
                }
            }

            $style = (string) $anchor->getAttribute('style');
            $imageUrl = $this->extractImageUrlFromStyle($style);

            $stockButton = $xpath->query('.//button[contains(@onclick, "add_to_cart(' . $productId . ')")]', $card)?->item(0);
            $isInStock = $stockButton !== null;

            $products[] = [
                'external_product_id' => $productId,
                'product_url' => $productUrl,
                'title' => $title,
                'image_url' => $imageUrl,
                'current_price' => $currentPrice,
                'old_price' => $oldPrice,
                'currency' => 'GEL',
                'availability' => $isInStock ? 'In stock' : 'Unknown',
                'is_in_stock' => $isInStock,
            ];
        }

        return $products;
    }

    private function detectMaxPage(string $html): int
    {
        preg_match_all('/\?page=(\d+)/i', $html, $matches);
        if (!isset($matches[1]) || $matches[1] === []) {
            return 1;
        }

        $pages = array_map('intval', $matches[1]);
        $maxPage = max($pages);

        return max(1, $maxPage);
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

    private function appendPageQuery(string $baseUrl, int $page): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . 'page=' . $page;
    }

    private function extractImageUrlFromStyle(string $style): ?string
    {
        if (preg_match('/background-image\s*:\s*url\(([^)]+)\)/i', $style, $match) !== 1) {
            return null;
        }

        $candidate = trim($match[1], "'\" ");
        if ($candidate === '') {
            return null;
        }

        return $this->toAbsoluteUrl($candidate);
    }

    private function toAbsoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return 'https://i-mobile.ge/' . ltrim($url, '/');
    }

    private function parsePrice(string $text): ?float
    {
        if (preg_match('/([0-9]+(?:[\.,][0-9]{1,2})?)/', $text, $match) !== 1) {
            return null;
        }

        return round((float) str_replace(',', '.', $match[1]), 2);
    }

    private function cleanText(string $text): string
    {
        $text = $this->fixMojibake($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }

    private function fixMojibake(string $text): string
    {
        $hasMojibake = str_contains($text, 'áƒ')
            || str_contains($text, 'Ã')
            || str_contains($text, 'Ð');

        if (!$hasMojibake) {
            return $text;
        }

        $fixed = @mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');

        return is_string($fixed) && $fixed !== '' ? $fixed : $text;
    }

    private function findClosestByClass(DOMNode $node, string $className): ?DOMElement
    {
        $current = $node;

        while ($current instanceof DOMNode) {
            if ($current instanceof DOMElement) {
                $classes = ' ' . trim((string) $current->getAttribute('class')) . ' ';
                if (str_contains($classes, ' ' . $className . ' ')) {
                    return $current;
                }
            }

            $current = $current->parentNode;
        }

        return null;
    }
}
