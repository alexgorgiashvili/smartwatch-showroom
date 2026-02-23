<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AlibabaScraperService
{
    public function scrape(?string $url, ?string $rawHtml = null): array
    {
        $html = $rawHtml && trim($rawHtml) !== ''
            ? $rawHtml
            : $this->fetchHtml((string) $url);

        $detailData = $this->extractDetailData($html);

        if ($this->isBlockedPage($html)) {
            throw new \RuntimeException(
                'Alibaba blocked this request (captcha/unusual traffic). Open the product in your browser, solve captcha, then paste full Page Source into the fallback field.'
            );
        }

        if (!$this->looksLikeProductPage($html)) {
            throw new \RuntimeException('Could not find product data on this page. Please use a direct Alibaba product-detail URL or paste full page source.');
        }

        $title = $this->extractMetaContent($html, 'property="og:title"')
            ?: $this->extractMetaContent($html, 'name="twitter:title"')
            ?: $this->extractTitleTag($html)
            ?: 'Smart Watch';

        $description = $this->extractMetaContent($html, 'property="og:description"')
            ?: $this->extractMetaContent($html, 'name="description"')
            ?: '';

        $imageUrls = $this->extractImageUrls($html, $detailData);
        $priceData = $this->extractPriceData($html);
        $specs = $this->extractSpecs($html);
        $mappedSpecs = $this->mapProductSpecs($html, $specs);
        $variants = $this->extractVariants($html, $detailData);

        return [
            'source_url' => $url,
            'title' => trim($title),
            'description' => trim($description),
            'price_min' => $priceData['min'],
            'price_max' => $priceData['max'],
            'currency' => $priceData['currency'] ?: 'USD',
            'specs' => $specs,
            'operating_system' => $mappedSpecs['operating_system'],
            'screen_size' => $mappedSpecs['screen_size'],
            'display_type' => $mappedSpecs['display_type'],
            'screen_resolution' => $mappedSpecs['screen_resolution'],
            'battery_capacity_mah' => $mappedSpecs['battery_capacity_mah'],
            'charging_time_hours' => $mappedSpecs['charging_time_hours'],
            'case_material' => $mappedSpecs['case_material'],
            'band_material' => $mappedSpecs['band_material'],
            'camera' => $mappedSpecs['camera'],
            'functions' => $mappedSpecs['functions'],
            'images' => $imageUrls,
            'variants' => $variants,
        ];
    }

    public function downloadImages(array $imageUrls, string $slug): array
    {
        $saved = [];

        foreach (array_values(array_unique($imageUrls)) as $index => $url) {
            if (!is_string($url) || !str_starts_with($url, 'http')) {
                continue;
            }

            $response = Http::timeout(25)->get($url);
            if (!$response->successful()) {
                continue;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            $extension = $this->detectExtension($url, $contentType);
            if (!$extension) {
                continue;
            }

            $filePath = 'images/products/' . $slug . '/' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '.' . $extension;
            Storage::disk('public')->put($filePath, $response->body());
            $saved[] = $filePath;

            if (count($saved) >= 8) {
                break;
            }
        }

        return $saved;
    }

    private function fetchHtml(string $url): string
    {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        ];

        $response = Http::withHeaders([
            'User-Agent' => $userAgents[array_rand($userAgents)],
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Referer' => 'https://www.alibaba.com/',
        ])->timeout(30)->get($url);

        if (!$response->successful()) {
            abort(422, 'Could not fetch Alibaba page. Try another product URL.');
        }

        $html = (string) $response->body();

        if ($this->isBlockedPage($html)) {
            throw new \RuntimeException(
                'Alibaba blocked this request (captcha/unusual traffic). Open the product in your browser, solve captcha, then paste full Page Source into the fallback field.'
            );
        }

        return $html;
    }

    private function isBlockedPage(string $html): bool
    {
        $haystack = strtolower($html);

        return str_contains($haystack, 'detected unusual traffic from your network')
            || str_contains($haystack, 'please slide to verify')
            || str_contains($haystack, 'captcha')
            || str_contains($haystack, 'sec-captcha')
            || str_contains($haystack, 'sorry, we have detected unusual traffic')
            || str_contains($haystack, 'challenge.alibaba.com');
    }

    private function looksLikeProductPage(string $html): bool
    {
        $haystack = strtolower($html);

        return str_contains($haystack, 'product-detail')
            || str_contains($haystack, 'og:title')
            || str_contains($haystack, 'og:image')
            || str_contains($haystack, 'alibaba.com/product')
            || preg_match('/(?:us\s*\$|\$|usd)\s*[0-9]/i', $html) === 1;
    }

    private function extractMetaContent(string $html, string $attribute): ?string
    {
        $pattern = '/<meta[^>]*' . preg_quote($attribute, '/') . '[^>]*content="([^"]+)"[^>]*>/i';
        if (preg_match($pattern, $html, $match) === 1) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    private function extractTitleTag(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match) === 1) {
            return html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    private function extractImageUrls(string $html, ?array $detailData = null): array
    {
        $urls = [];

        $mediaItems = data_get($detailData, 'globalData.product.mediaItems', []);
        if (is_array($mediaItems)) {
            foreach ($mediaItems as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if ((string) ($item['type'] ?? '') !== 'image') {
                    continue;
                }

                $big = data_get($item, 'imageUrl.big');
                $origin = data_get($item, 'imageUrl.origin');
                $normal = data_get($item, 'imageUrl.normal');

                foreach ([$big, $origin, $normal] as $candidate) {
                    if (is_string($candidate) && trim($candidate) !== '') {
                        $urls[] = $candidate;
                    }
                }
            }
        }

        foreach ($this->extractJsonLdImageUrls($html) as $jsonLdImage) {
            $urls[] = $jsonLdImage;
        }

        if (preg_match_all('/(?:https?:)?\/\/[^"\']+\.(?:jpg|jpeg|png|webp)(?:\?[^"\']*)?/i', $html, $matches) > 0) {
            foreach ($matches[0] as $url) {
                if (str_contains($url, 'alicdn.com') || str_contains($url, 'alibaba.com')) {
                    $urls[] = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
                }
            }
        }

        $ogImage = $this->extractMetaContent($html, 'property="og:image"');
        if ($ogImage) {
            $urls[] = $ogImage;
        }

        $normalized = [];
        foreach ($urls as $url) {
            $normalizedUrl = $this->normalizeImageUrl($url);
            if ($normalizedUrl !== null) {
                $normalized[] = $normalizedUrl;
            }
        }

        return array_values(array_slice(array_unique($normalized), 0, 12));
    }

    private function extractPriceData(string $html): array
    {
        $currency = null;
        $numbers = [];

        if (preg_match_all('/(?:US\s*\$|\$|USD)\s*([0-9]+(?:\.[0-9]{1,2})?)/i', $html, $matches) > 0) {
            $numbers = array_map('floatval', $matches[1]);
            $currency = 'USD';
        }

        if ($numbers === [] && preg_match_all('/([0-9]+(?:\.[0-9]{1,2})?)\s*(USD|EUR|GEL)/i', $html, $matches) > 0) {
            $numbers = array_map('floatval', $matches[1]);
            $currency = strtoupper($matches[2][0] ?? 'USD');
        }

        if ($numbers === []) {
            return ['min' => null, 'max' => null, 'currency' => $currency];
        }

        sort($numbers);

        return [
            'min' => $numbers[0],
            'max' => $numbers[count($numbers) - 1],
            'currency' => $currency,
        ];
    }

    private function extractSpecs(string $html): array
    {
        $specs = [];

        $keys = [
            'battery',
            'water',
            'waterproof',
            'ip67',
            'ip68',
            'sim',
            'gps',
            'warranty',
            'screen',
            'display',
        ];

        foreach ($keys as $key) {
            if (preg_match('/' . preg_quote($key, '/') . '.{0,80}/i', $html, $match) === 1) {
                $value = trim(strip_tags($match[0]));
                if ($value !== '') {
                    $specs[$key] = Str::limit($value, 120, '');
                }
            }
        }

        return $specs;
    }

    private function extractVariants(string $html, ?array $detailData = null): array
    {
        $variants = [];

        $skuAttrSources = [
            data_get($detailData, 'globalData.product.sku.skuAttrs', []),
            data_get($detailData, 'globalData.product.sku.skuSummaryAttrs', []),
        ];

        foreach ($skuAttrSources as $attrs) {
            if (!is_array($attrs)) {
                continue;
            }

            foreach ($attrs as $attr) {
                if (!is_array($attr)) {
                    continue;
                }

                $attrName = strtolower(trim((string) ($attr['name'] ?? '')));
                if (!str_contains($attrName, 'color') && !str_contains($attrName, 'colour')) {
                    continue;
                }

                $values = $attr['values'] ?? [];
                if (!is_array($values)) {
                    continue;
                }

                foreach ($values as $value) {
                    $name = trim((string) data_get($value, 'name', ''));
                    if ($name === '' || strlen($name) > 160) {
                        continue;
                    }

                    $variants[] = [
                        'name' => $name,
                        'quantity' => 0,
                        'low_stock_threshold' => 5,
                    ];
                }
            }
        }

        if ($variants === [] && preg_match_all('/(?:color|colour)\s*[:\-]\s*([A-Za-z0-9\-\/,\s]{2,80})/i', $html, $matches) > 0) {
            foreach ($matches[1] as $raw) {
                $name = trim(preg_replace('/\s+/', ' ', $raw));
                if ($name !== '' && strlen($name) <= 160) {
                    $variants[] = [
                        'name' => $name,
                        'quantity' => 0,
                        'low_stock_threshold' => 5,
                    ];
                }
            }
        }

        if ($variants === []) {
            $title = $this->extractTitleTag($html) ?: 'Default Variant';
            $variants[] = [
                'name' => Str::limit($title, 120),
                'quantity' => 0,
                'low_stock_threshold' => 5,
            ];
        }

        $unique = [];
        foreach ($variants as $variant) {
            $key = strtolower($variant['name']);
            $unique[$key] = $variant;
        }

        return array_values(array_slice($unique, 0, 20));
    }

    private function detectExtension(string $url, string $contentType): ?string
    {
        if (str_contains($contentType, 'image/jpeg')) {
            return 'jpg';
        }

        if (str_contains($contentType, 'image/png')) {
            return 'png';
        }

        if (str_contains($contentType, 'image/webp')) {
            return 'webp';
        }

        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
            ? ($extension === 'jpeg' ? 'jpg' : $extension)
            : null;
    }

    private function extractDetailData(string $html): ?array
    {
        $markerPos = strpos($html, 'window.detailData');
        if ($markerPos === false) {
            return null;
        }

        $equalPos = strpos($html, '=', $markerPos);
        if ($equalPos === false) {
            return null;
        }

        $startPos = strpos($html, '{', $equalPos);
        if ($startPos === false) {
            return null;
        }

        $json = $this->extractBalancedJsonObject($html, $startPos);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function extractBalancedJsonObject(string $text, int $startPos): ?string
    {
        $length = strlen($text);
        if ($startPos < 0 || $startPos >= $length || $text[$startPos] !== '{') {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $quote = '';

        for ($index = $startPos; $index < $length; $index++) {
            $char = $text[$index];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }

                if ($char === '\\') {
                    $escape = true;
                    continue;
                }

                if ($char === $quote) {
                    $inString = false;
                    $quote = '';
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = true;
                $quote = $char;
                continue;
            }

            if ($char === '{') {
                $depth++;
                continue;
            }

            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $startPos, $index - $startPos + 1);
                }
            }
        }

        return null;
    }

    private function extractJsonLdImageUrls(string $html): array
    {
        $urls = [];

        if (preg_match_all('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches) <= 0) {
            return [];
        }

        foreach ($matches[1] as $scriptContent) {
            $decoded = json_decode(trim($scriptContent), true);
            if (!is_array($decoded)) {
                continue;
            }

            $items = array_is_list($decoded) ? $decoded : [$decoded];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if (($item['@type'] ?? null) !== 'Product') {
                    continue;
                }

                $images = $item['image'] ?? [];
                if (is_string($images)) {
                    $images = [$images];
                }

                if (!is_array($images)) {
                    continue;
                }

                foreach ($images as $image) {
                    if (is_string($image) && trim($image) !== '') {
                        $urls[] = $image;
                    }
                }
            }
        }

        return $urls;
    }

    private function normalizeImageUrl(mixed $url): ?string
    {
        if (!is_string($url)) {
            return null;
        }

        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return null;
    }

    private function mapProductSpecs(string $html, array $specs): array
    {
        $pairs = $this->extractSpecTablePairs($html);

        $operatingSystem = $this->findSpecValue($pairs, ['operating system', 'os']);
        $screenSize = $this->findSpecValue($pairs, ['screen size', 'screen']);
        $displayType = $this->findSpecValue($pairs, ['display type']);
        $screenResolution = $this->findSpecValue($pairs, ['screen resolution', 'resolution']);
        $caseMaterial = $this->findSpecValue($pairs, ['case material']);
        $bandMaterial = $this->findSpecValue($pairs, ['band material', 'strap material']);
        $camera = $this->findSpecValue($pairs, ['camera']);

        $batteryRaw = $this->findSpecValue($pairs, ['battery time', 'battery capacity', 'battery']);
        $chargingRaw = $this->findSpecValue($pairs, ['charging time', 'charge time']);

        $functionRaw = $this->findSpecValue($pairs, ['function', 'functions']);
        $featureRaw = $this->findSpecValue($pairs, ['feature', 'features']);

        if (!$operatingSystem && isset($specs['system'])) {
            $operatingSystem = $specs['system'];
        }

        return [
            'operating_system' => $this->nullableLimitedString($operatingSystem),
            'screen_size' => $this->nullableLimitedString($screenSize),
            'display_type' => $this->nullableLimitedString($displayType),
            'screen_resolution' => $this->nullableLimitedString($screenResolution),
            'battery_capacity_mah' => $this->parseFirstInt($batteryRaw),
            'charging_time_hours' => $this->parseFirstFloat($chargingRaw),
            'case_material' => $this->nullableLimitedString($caseMaterial),
            'band_material' => $this->nullableLimitedString($bandMaterial),
            'camera' => $this->nullableLimitedString($camera),
            'functions' => $this->splitFunctionList([$functionRaw, $featureRaw]),
        ];
    }

    private function extractSpecTablePairs(string $html): array
    {
        $pairs = [];

        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rows) <= 0) {
            return $pairs;
        }

        foreach ($rows[1] as $rowHtml) {
            if (preg_match_all('/<(?:th|td)[^>]*>(.*?)<\/(?:th|td)>/is', $rowHtml, $cells) < 2) {
                continue;
            }

            $cellValues = array_values(array_filter(array_map(function (string $cell): string {
                return trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($cell, ENT_QUOTES | ENT_HTML5))) ?: '');
            }, $cells[1]), static fn (string $value) => $value !== ''));

            for ($index = 0; $index + 1 < count($cellValues); $index += 2) {
                $key = $this->normalizeSpecKey($cellValues[$index]);
                $value = trim($cellValues[$index + 1]);

                if ($key !== '' && $value !== '') {
                    $pairs[$key] = $value;
                }
            }
        }

        return $pairs;
    }

    private function normalizeSpecKey(string $key): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $key) ?: ''));
    }

    private function findSpecValue(array $pairs, array $candidateKeys): ?string
    {
        foreach ($candidateKeys as $candidateKey) {
            $normalized = $this->normalizeSpecKey($candidateKey);
            if (isset($pairs[$normalized])) {
                return $pairs[$normalized];
            }
        }

        return null;
    }

    private function parseFirstInt(?string $value): ?int
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        if (preg_match('/([0-9]{1,6})/', $value, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function parseFirstFloat(?string $value): ?float
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $value, $match) !== 1) {
            return null;
        }

        return (float) $match[1];
    }

    private function splitFunctionList(array $values): array
    {
        $items = [];

        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $parts = preg_split('/[,;\n]+/', $value) ?: [];
            foreach ($parts as $part) {
                $clean = trim($part);
                if ($clean !== '') {
                    $items[] = Str::limit($clean, 100, '');
                }
            }
        }

        return array_values(array_unique($items));
    }

    private function nullableLimitedString(?string $value, int $max = 100): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $clean = trim($value);

        return $clean === '' ? null : Str::limit($clean, $max, '');
    }
}
