<?php

namespace App\Services;

use App\Services\Contracts\CompetitorCategoryScraper;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;

class LuckyStoreCategoryScraperService implements CompetitorCategoryScraper
{
    public function scrapeCategory(string $categoryUrl): array
    {
        $normalizedCategoryUrl = $this->normalizeCategoryUrl($categoryUrl);
        $firstPageHtml = $this->fetchHtml($normalizedCategoryUrl);
        $maxPage = $this->detectMaxPage($firstPageHtml);

        $items = $this->extractProductsFromHtml($firstPageHtml);

        $seenUrls = [];
        foreach ($items as $item) {
            $url = trim((string) ($item['product_url'] ?? ''));
            if ($url !== '') {
                $seenUrls[$url] = true;
            }
        }

        $safetyMaxPage = max($maxPage, 20);
        for ($page = 2; $page <= $safetyMaxPage; $page++) {
            $pageHtml = $this->fetchHtml($this->appendPageQuery($normalizedCategoryUrl, $page));
            $pageItems = $this->extractProductsFromHtml($pageHtml);

            if ($pageItems === []) {
                break;
            }

            $newOnPage = 0;
            foreach ($pageItems as $pageItem) {
                $url = trim((string) ($pageItem['product_url'] ?? ''));
                if ($url === '') {
                    continue;
                }

                if (!isset($seenUrls[$url])) {
                    $seenUrls[$url] = true;
                    $newOnPage++;
                }
            }

            $items = array_merge($items, $pageItems);

            if ($newOnPage === 0 && $page >= $maxPage) {
                break;
            }
        }

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
            'Referer' => 'https://www.luckystore.ge/',
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
            throw new \RuntimeException('Failed to fetch luckystore category page.');
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
        $cards = $xpath->query('//li[@data-hook="product-list-grid-item"]');

        if (!$cards) {
            return [];
        }

        $items = [];

        foreach ($cards as $card) {
            if (!$card instanceof DOMElement) {
                continue;
            }

            $linkNode = $xpath->query('.//a[contains(@href, "/product-page/") and @data-hook="product-item-container"]', $card)?->item(0)
                ?? $xpath->query('.//a[contains(@href, "/product-page/")][1]', $card)?->item(0);

            $href = $linkNode instanceof DOMElement
                ? trim((string) $linkNode->getAttribute('href'))
                : '';

            if ($href === '' || !str_contains($href, '/product-page/')) {
                continue;
            }

            $titleNode = $xpath->query('.//*[@data-hook="product-item-name"][1]', $card)?->item(0);
            $title = $this->cleanText((string) ($titleNode?->textContent ?? ''));
            if ($title === '') {
                continue;
            }

            $productUrl = $this->toAbsoluteUrl($href);

            $regularPriceNode = $xpath->query('.//*[@data-hook="product-item-price-before-discount"][1]', $card)?->item(0);
            $salePriceNode = $xpath->query('.//*[@data-hook="product-item-price-to-pay"][1]', $card)?->item(0);

            $regularPriceRaw = $regularPriceNode instanceof DOMElement
                ? (string) ($regularPriceNode->getAttribute('data-wix-original-price') ?: $regularPriceNode->textContent)
                : '';

            $salePriceRaw = $salePriceNode instanceof DOMElement
                ? (string) ($salePriceNode->getAttribute('data-wix-price') ?: $salePriceNode->textContent)
                : '';

            $regularPrice = $this->parsePrice($regularPriceRaw);
            $salePrice = $this->parsePrice($salePriceRaw);

            if ($regularPrice === null && $salePrice === null) {
                $fallbackText = $this->cleanText($card->textContent);
                [, $regularPrice, $salePrice] = $this->extractTitleAndPrices($fallbackText);
            }

            $imageUrl = null;
            $imageNode = $xpath->query('.//img[1]', $card)?->item(0);
            if ($imageNode instanceof DOMElement) {
                $imageUrl = $this->normalizeImageUrl((string) $imageNode->getAttribute('src'));
            }

            $items[] = [
                'external_product_id' => $this->extractExternalProductId($productUrl),
                'product_url' => $productUrl,
                'title' => $title,
                'image_url' => $imageUrl,
                'current_price' => $salePrice ?? $regularPrice,
                'old_price' => $salePrice !== null ? $regularPrice : null,
                'currency' => 'GEL',
                'availability' => 'Unknown',
                'is_in_stock' => null,
            ];
        }

        return $items;
    }

    private function detectMaxPage(string $html): int
    {
        preg_match_all('/[?&]page=(\d+)/i', $html, $matches);
        if (!isset($matches[1]) || $matches[1] === []) {
            return 1;
        }

        $pages = array_map('intval', $matches[1]);
        $maxPage = max($pages);

        return max(1, $maxPage);
    }

    private function appendPageQuery(string $baseUrl, int $page): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . 'page=' . $page;
    }

    private function extractTitleAndPrices(string $text): array
    {
        $title = $text;
        $regularPrice = null;
        $salePrice = null;

        $pattern = '/^(.*?)\s*Regular\s*Price\s*([0-9\.,]+)\s*GEL(?:\s*Sale\s*Price\s*([0-9\.,]+)\s*GEL)?/iu';
        if (preg_match($pattern, $text, $match) === 1) {
            $title = trim((string) ($match[1] ?? $text));
            $regularPrice = $this->parsePrice((string) ($match[2] ?? ''));
            $salePrice = $this->parsePrice((string) ($match[3] ?? ''));
        } else {
            if (preg_match('/^(.*?)\s*(Regular\s*Price|Sale\s*Price)\b/iu', $text, $titleMatch) === 1) {
                $title = trim((string) ($titleMatch[1] ?? $text));
            }

            preg_match_all('/([0-9]+(?:[\.,][0-9]{1,2})?)\s*GEL/iu', $text, $priceMatches);
            $priceValues = array_map(fn (string $value) => $this->parsePrice($value), $priceMatches[1] ?? []);
            $priceValues = array_values(array_filter($priceValues, fn (?float $price) => $price !== null));

            if (count($priceValues) >= 2) {
                $regularPrice = $priceValues[0];
                $salePrice = $priceValues[1];
            } elseif (count($priceValues) === 1) {
                $regularPrice = $priceValues[0];
            }
        }

        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $text);

        return [
            $title !== '' ? $title : $text,
            $regularPrice,
            $salePrice,
        ];
    }

    private function extractExternalProductId(string $productUrl): ?string
    {
        $path = (string) parse_url($productUrl, PHP_URL_PATH);
        if ($path === '') {
            return null;
        }

        $slug = trim((string) basename($path));

        return $slug !== '' ? $slug : null;
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

        return 'https://www.luckystore.ge/' . ltrim($url, '/');
    }

    private function normalizeImageUrl(string $url): ?string
    {
        $candidate = trim($url);
        if ($candidate === '') {
            return null;
        }

        $candidate = str_replace(',blur_2', '', $candidate);

        return $this->toAbsoluteUrl($candidate);
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
