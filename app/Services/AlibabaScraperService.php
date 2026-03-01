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
        $priceData = $this->extractPriceData($html, $detailData);
        $tableSpecs = $this->extractSpecTablePairs($html);
        $detailSpecs = $this->extractSpecPairsFromDetailData($detailData);
        $specs = array_merge($tableSpecs, $detailSpecs, $this->extractSpecs($html));
        $mappedSpecs = $this->mapProductSpecs($html, $specs, $detailData);
        $variants = $this->extractVariants($html, $detailData);
        $sourceProductId = $this->extractSourceProductId($detailData, $url);

        return [
            'source_url' => $url,
            'source_product_id' => $sourceProductId,
            'title' => trim($title),
            'product_name' => $mappedSpecs['product_name'] ?? null,
            'description' => trim($description),
            'price_min' => $priceData['min'],
            'price_max' => $priceData['max'],
            'currency' => $priceData['currency'] ?: 'USD',
            'specs' => $specs,
            'brand' => $mappedSpecs['brand'],
            'model' => $mappedSpecs['model'],
            'memory_size' => $mappedSpecs['memory_size'],
            'operating_system' => $mappedSpecs['operating_system'],
            'screen_size' => $mappedSpecs['screen_size'],
            'display_type' => $mappedSpecs['display_type'],
            'screen_resolution' => $mappedSpecs['screen_resolution'],
            'battery_capacity_mah' => $mappedSpecs['battery_capacity_mah'],
            'charging_time_hours' => $mappedSpecs['charging_time_hours'],
            'warranty_months' => $mappedSpecs['warranty_months'],
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

            $baseName = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $filePath = 'images/products/' . $slug . '/' . $baseName . '.' . $extension;
            Storage::disk('public')->put($filePath, $response->body());

            $thumbnailPath = $this->createThumbnailFromBinary($response->body(), $slug, $baseName, $extension);

            $saved[] = [
                'path' => $filePath,
                'thumbnail_path' => $thumbnailPath,
            ];

            if (count($saved) >= 8) {
                break;
            }
        }

        return $saved;
    }

    private function createThumbnailFromBinary(string $binary, string $slug, string $baseName, string $extension): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $sourceImage = @imagecreatefromstring($binary);
        if ($sourceImage === false) {
            return null;
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($sourceImage);
            return null;
        }

        $targetWidth = 320;
        $targetHeight = 320;

        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $srcX = (int) round(($sourceWidth - $cropWidth) / 2);
            $srcY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $srcX = 0;
            $srcY = (int) round(($sourceHeight - $cropHeight) / 2);
        }

        $thumb = imagecreatetruecolor($targetWidth, $targetHeight);

        if (in_array($extension, ['png', 'webp'], true)) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefilledrectangle($thumb, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled(
            $thumb,
            $sourceImage,
            0,
            0,
            $srcX,
            $srcY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight
        );

        ob_start();
        $written = match ($extension) {
            'png' => imagepng($thumb, null, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($thumb, null, 80) : imagejpeg($thumb, null, 82),
            default => imagejpeg($thumb, null, 82),
        };
        $thumbBinary = ob_get_clean();

        imagedestroy($thumb);
        imagedestroy($sourceImage);

        if (!$written || !is_string($thumbBinary) || $thumbBinary === '') {
            return null;
        }

        $thumbnailPath = 'images/products/' . $slug . '/' . $baseName . '_thumb.' . ($extension === 'webp' && !function_exists('imagewebp') ? 'jpg' : $extension);
        Storage::disk('public')->put($thumbnailPath, $thumbBinary);

        return $thumbnailPath;
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
        $hasMediaImages = false;

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

                foreach ([$origin, $big, $normal] as $candidate) {
                    $normalizedCandidate = $this->normalizeImageUrl($candidate);
                    if ($normalizedCandidate !== null) {
                        $urls[] = $normalizedCandidate;
                        $hasMediaImages = true;
                        break;
                    }
                }
            }
        }

        if (!$hasMediaImages) {
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
        }

        $normalizedByKey = [];
        foreach ($urls as $url) {
            $normalizedUrl = $this->normalizeImageUrl($url);
            if ($normalizedUrl !== null) {
                $key = $this->imageDedupKey($normalizedUrl);
                if (!isset($normalizedByKey[$key])) {
                    $normalizedByKey[$key] = $normalizedUrl;
                }
            }
        }

        return array_values(array_slice(array_values($normalizedByKey), 0, 12));
    }

    private function extractPriceData(string $html, ?array $detailData = null): array
    {
        $ladderPrices = data_get($detailData, 'globalData.product.price.productLadderPrices', []);
        if (is_array($ladderPrices) && $ladderPrices !== []) {
            $values = [];
            foreach ($ladderPrices as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $price = data_get($entry, 'price');
                if (is_numeric($price)) {
                    $values[] = (float) $price;
                }
            }

            if ($values !== []) {
                sort($values);

                return [
                    'min' => $values[0],
                    'max' => $values[count($values) - 1],
                    'currency' => 'USD',
                ];
            }
        }

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

                    $colorHex = $this->normalizeColorHex((string) data_get($value, 'color', ''));

                    $variants[] = [
                        'name' => $name,
                        'color_name' => str_contains($attrName, 'color') ? $name : null,
                        'color_hex' => $colorHex,
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
                        'color_name' => $name,
                        'color_hex' => null,
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
                'color_name' => null,
                'color_hex' => null,
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

    private function imageDedupKey(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));

        if ($path === '') {
            return strtolower($url);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $filename = pathinfo($path, PATHINFO_FILENAME);

        $filename = preg_replace('/(?:[_-])(?:\d{2,4}x\d{2,4})(?:[a-z0-9]*)$/i', '', (string) $filename) ?: (string) $filename;
        $filename = preg_replace('/(?:[_-])(?:q\d{2,3}|quality\d{2,3}|small|normal|thumb|thumbnail)$/i', '', (string) $filename) ?: (string) $filename;

        $directory = trim(strtolower((string) pathinfo($path, PATHINFO_DIRNAME)), '/.');

        return $host . '/' . $directory . '/' . trim($filename, '_-') . '.' . strtolower((string) $extension);
    }

    private function mapProductSpecs(string $html, array $specs, ?array $detailData = null): array
    {
        $pairs = array_merge($this->extractSpecTablePairs($html), $this->extractSpecPairsFromDetailData($detailData));

        $operatingSystem = $this->findSpecValue($pairs, ['operating system', 'operation system', 'operating system (os)', 'os', 'system']);
        $screenSize = $this->findSpecValue($pairs, ['screen size', 'screen']);
        $displayType = $this->findSpecValue($pairs, ['display type', 'display']);
        $screenResolution = $this->findSpecValue($pairs, ['screen resolution', 'resolution']);
        $caseMaterial = $this->findSpecValue($pairs, ['case material']);
        $bandMaterial = $this->findSpecValue($pairs, ['band material', 'strap material']);
        $camera = $this->findSpecValue($pairs, ['camera']);

        $batteryRaw = $this->findSpecValue($pairs, ['battery capacity', 'battery capacity (mah)', 'battery capacity mah', 'battery']);
        $chargingRaw = $this->findSpecValue($pairs, ['charging time', 'charge time']);
        $warrantyRaw = $this->findSpecValue($pairs, ['warranty', 'warranty year', 'warranty period', 'after-sales service']);

        $productName = $this->findSpecValue($pairs, ['product name', 'item name']);
        $model = $this->findSpecValue($pairs, ['model number', 'model', 'model no']);
        $memorySize = $this->findSpecValue($pairs, ['memory size', 'ram', 'rom', 'memory', 'ram rom']);

        $functionRaw = $this->findSpecValue($pairs, ['function', 'functions', 'featured functions', 'feature', 'features']);
        $featureRaw = $this->findSpecValue($pairs, ['feature', 'features']);

        if (!$screenResolution && is_string($screenSize) && preg_match('/([0-9]{2,4}\s*[x\*]\s*[0-9]{2,4})/i', $screenSize, $match) === 1) {
            $screenResolution = strtoupper(str_replace(' ', '', $match[1]));
        }

        if (!$operatingSystem && isset($specs['system'])) {
            $operatingSystem = $specs['system'];
        }

        return [
            'product_name' => $this->nullableLimitedString($productName, 160),
            'operating_system' => $this->nullableLimitedString($operatingSystem),
            'brand' => $this->nullableLimitedString($this->findSpecValue($pairs, ['brand', 'brand name', 'manufacturer'])),
            'model' => $this->nullableLimitedString($model),
            'memory_size' => $this->nullableLimitedString($memorySize),
            'screen_size' => $this->nullableLimitedString($screenSize),
            'display_type' => $this->nullableLimitedString($displayType),
            'screen_resolution' => $this->nullableLimitedString($screenResolution),
            'battery_capacity_mah' => $this->parseFirstInt($batteryRaw),
            'charging_time_hours' => $this->parseFirstFloat($chargingRaw),
            'warranty_months' => $this->parseWarrantyMonths($warrantyRaw),
            'case_material' => $this->nullableLimitedString($caseMaterial),
            'band_material' => $this->nullableLimitedString($bandMaterial),
            'camera' => $this->nullableLimitedString($camera),
            'functions' => $this->splitFunctionList([$functionRaw, $featureRaw]),
        ];
    }

    private function extractSpecPairsFromDetailData(?array $detailData): array
    {
        if (!is_array($detailData)) {
            return [];
        }

        $pairs = [];

        $propertySources = [
            data_get($detailData, 'globalData.product.productBasicProperties', []),
            data_get($detailData, 'globalData.product.productKeyIndustryProperties', []),
            data_get($detailData, 'globalData.product.productOtherProperties', []),
        ];

        foreach ($propertySources as $properties) {
            if (!is_array($properties)) {
                continue;
            }

            foreach ($properties as $property) {
                if (!is_array($property)) {
                    continue;
                }

                $name = $this->normalizeSpecKey((string) ($property['attrName'] ?? ''));
                $value = trim((string) ($property['attrValue'] ?? ''));

                if ($name !== '' && $value !== '') {
                    $pairs[$name] = $value;
                }
            }
        }

        $mediaItems = data_get($detailData, 'globalData.product.mediaItems', []);
        if (is_array($mediaItems)) {
            foreach ($mediaItems as $item) {
                if (!is_array($item) || (string) ($item['group'] ?? '') !== 'attributes') {
                    continue;
                }

                $attributeGroups = [
                    data_get($item, 'attributeData.keyAttributes', []),
                    data_get($item, 'attributeData.otherAttributes', []),
                ];

                foreach ($attributeGroups as $group) {
                    if (!is_array($group)) {
                        continue;
                    }

                    foreach ($group as $attribute) {
                        if (!is_array($attribute)) {
                            continue;
                        }

                        $name = $this->normalizeSpecKey((string) ($attribute['attributeName'] ?? ''));
                        $value = trim((string) ($attribute['attributeValue'] ?? ''));

                        if ($name !== '' && $value !== '') {
                            $pairs[$name] = $value;
                        }
                    }
                }
            }
        }

        $sortedProperties = data_get($detailData, 'nodeMap.module_sorted_attribute.privateData.productSortedProperties', []);
        if (is_array($sortedProperties)) {
            foreach ($sortedProperties as $group) {
                if (!is_array($group)) {
                    continue;
                }

                $attributeList = $group['attributeList'] ?? [];
                if (!is_array($attributeList)) {
                    continue;
                }

                foreach ($attributeList as $attribute) {
                    if (!is_array($attribute)) {
                        continue;
                    }

                    $name = $this->normalizeSpecKey((string) ($attribute['attribute'] ?? ''));
                    $value = trim((string) ($attribute['value'] ?? ''));

                    if ($name !== '' && $value !== '') {
                        $pairs[$name] = $value;
                    }
                }
            }
        }

        return $pairs;
    }

    private function extractSourceProductId(?array $detailData, ?string $url): ?string
    {
        $detailProductId = data_get($detailData, 'globalData.product.productId');
        if (is_numeric($detailProductId) || (is_string($detailProductId) && trim($detailProductId) !== '')) {
            return (string) $detailProductId;
        }

        if (!is_string($url) || trim($url) === '') {
            return null;
        }

        if (preg_match('/(?:product-detail|product)\/[^\/_]+_([0-9]{6,})\.html/i', $url, $match) === 1) {
            return $match[1];
        }

        return null;
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
        $decoded = html_entity_decode($key, ENT_QUOTES | ENT_HTML5);
        $lettersOnly = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $decoded) ?: '';

        return strtolower(trim(preg_replace('/\s+/', ' ', $lettersOnly) ?: ''));
    }

    private function findSpecValue(array $pairs, array $candidateKeys): ?string
    {
        foreach ($candidateKeys as $candidateKey) {
            $normalized = $this->normalizeSpecKey($candidateKey);
            if (isset($pairs[$normalized])) {
                return $pairs[$normalized];
            }

            $value = $this->findSpecValueByPartialKey($pairs, $normalized);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function findSpecValueByPartialKey(array $pairs, string $candidateKey): ?string
    {
        if ($candidateKey === '') {
            return null;
        }

        foreach ($pairs as $key => $value) {
            if (!is_string($key) || !is_string($value) || trim($value) === '') {
                continue;
            }

            if (str_contains($key, $candidateKey) || str_contains($candidateKey, $key)) {
                return $value;
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

    private function parseWarrantyMonths(?string $value): ?int
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $text = strtolower(trim($value));

        if (preg_match('/([0-9]{1,2})\s*(year|years|yr|yrs)/i', $text, $match) === 1) {
            return ((int) $match[1]) * 12;
        }

        if (preg_match('/([0-9]{1,3})\s*(month|months|mo|mos)/i', $text, $match) === 1) {
            return (int) $match[1];
        }

        if (preg_match('/([0-9]{1,3})/', $text, $match) === 1) {
            $number = (int) $match[1];

            return $number <= 10 ? $number * 12 : $number;
        }

        return null;
    }

    private function splitFunctionList(array $values): array
    {
        $items = [];

        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $parts = preg_split('/[,;\n]+|\s+(?=\d+\.)/', $value) ?: [];
            foreach ($parts as $part) {
                $clean = trim(preg_replace('/^\d+\.\s*/', '', $part) ?? $part);
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

    private function normalizeColorHex(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if (preg_match('/^#[0-9A-F]{6}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
