<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AlibabaApifyLiveScraperService
{
    public function scrapeProductUrl(string $url): array
    {
        $token = (string) config('services.apify.token', '');
        $actorId = $this->normalizeActorId((string) config('services.apify.actor_id', 'apify/web-scraper'));
        $baseUrl = rtrim((string) config('services.apify.base_url', 'https://api.apify.com/v2'), '/');
        $timeoutSeconds = (int) config('services.apify.timeout', 180);

        if ($token === '') {
            throw new \RuntimeException('Apify token is missing. Configure APIFY_API_TOKEN in .env.');
        }

        $firstItem = $this->runAndGetFirstItem($baseUrl, $actorId, $token, $timeoutSeconds, $this->buildInput($url));

        if ($this->isClearlyBlocked($firstItem) && (bool) config('services.apify.retry_with_residential', true)) {
            $residentialInput = $this->buildInput($url);
            $residentialInput['proxyConfiguration'] = [
                'useApifyProxy' => true,
                'apifyProxyGroups' => ['RESIDENTIAL'],
            ];

            $countryCode = strtoupper(trim((string) config('services.apify.proxy_country', '')));
            if ($countryCode !== '') {
                $residentialInput['proxyConfiguration']['apifyProxyCountry'] = $countryCode;
            }

            $firstItem = $this->runAndGetFirstItem($baseUrl, $actorId, $token, $timeoutSeconds, $residentialInput);
        }

        if ($this->isClearlyBlocked($firstItem)) {
            throw new \RuntimeException(
                'Alibaba blocked crawler access (captcha/interception). Try APIFY_PROXY_COUNTRY, enable residential proxy, or import using manual JSON/Page Source fallback.'
            );
        }

        if ($this->isTooEmpty($firstItem)) {
            throw new \RuntimeException(
                'Crawler reached the page but extracted too little data. Try another Alibaba URL variant or use APIFY_INPUT_TEMPLATE_JSON with a stronger pageFunction.'
            );
        }

        return [
            'item' => $firstItem,
        ];
    }

    private function runAndGetFirstItem(string $baseUrl, string $actorId, string $token, int $timeoutSeconds, array $input): array
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout($timeoutSeconds)
            ->post("{$baseUrl}/acts/{$actorId}/run-sync-get-dataset-items", $input);

        if (!$response->successful()) {
            throw new \RuntimeException('Apify run failed. Please verify APIFY_API_TOKEN, actor access, and URL validity.');
        }

        $items = $response->json();
        if (!is_array($items) || $items === []) {
            throw new \RuntimeException('Apify completed but returned no product items for this URL.');
        }

        $firstItem = $items[0] ?? null;
        if (!is_array($firstItem)) {
            throw new \RuntimeException('Apify returned an unexpected dataset format.');
        }

        return $firstItem;
    }

    private function isClearlyBlocked(array $item): bool
    {
        $debugErrors = data_get($item, '#debug.errorMessages', []);
        $errorBlob = strtolower(is_array($debugErrors) ? implode(' ', $debugErrors) : (string) $debugErrors);

        $titleTag = strtolower((string) ($item['titleTag'] ?? $item['title'] ?? ''));
        $bodySample = strtolower((string) ($item['bodySample'] ?? $item['description'] ?? ''));

        $captchaSignals = [
            'captcha',
            'unusual traffic',
            'challenge.alibaba.com',
            'interception',
            'net::err_timed_out',
        ];

        foreach ($captchaSignals as $signal) {
            if (str_contains($titleTag, $signal) || str_contains($bodySample, $signal) || str_contains($errorBlob, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function isTooEmpty(array $item): bool
    {
        $title = trim((string) ($item['title'] ?? $item['pageTitle'] ?? ''));
        $images = (array) ($item['images'] ?? []);
        $specs = (array) ($item['specs'] ?? []);
        $variants = (array) ($item['variants'] ?? []);

        $qualitySignals = 0;
        if ($title !== '') {
            $qualitySignals++;
        }
        if (count($images) > 0) {
            $qualitySignals++;
        }
        if (count($specs) > 0) {
            $qualitySignals++;
        }
        if (count($variants) > 0) {
            $qualitySignals++;
        }

        return $qualitySignals < 2;
    }

    private function normalizeActorId(string $actorId): string
    {
        $trimmed = trim($actorId);
        if ($trimmed === '') {
            return 'apify~web-scraper';
        }

        return str_replace('/', '~', $trimmed);
    }

    private function buildInput(string $url): array
    {
        $input = $this->inputTemplateFromConfig() ?? [];

        $input['startUrls'] = [
            ['url' => $url],
        ];

        $input['runMode'] = 'PRODUCTION';
        $input['headless'] = true;
        $input['maxCrawlingDepth'] = 0;
        $input['maxRequestsPerCrawl'] = 1;
        $input['injectJQuery'] = true;
        $input['waitUntil'] = ['networkidle2'];
        $input['linkSelector'] = '';
        $input['globs'] = [];
        $input['pseudoUrls'] = [];
        $input['respectRobotsTxtFile'] = (bool) config('services.apify.respect_robots', false);
        $input['proxyConfiguration'] = [
            'useApifyProxy' => (bool) config('services.apify.use_proxy', true),
        ];

        $useTemplatePageFunction = (bool) config('services.apify.use_template_page_function', false);
        if ($useTemplatePageFunction) {
            $pageFunction = (string) ($input['pageFunction'] ?? '');
            if (trim($pageFunction) === '') {
                $input['pageFunction'] = $this->defaultPageFunction();
            } else {
                $input['pageFunction'] = $this->sanitizePageFunction($pageFunction);
            }
        } else {
            $input['pageFunction'] = $this->defaultPageFunction();
        }

        return $input;
    }

    private function inputTemplateFromConfig(): ?array
    {
        $json = trim((string) config('services.apify.input_template_json', ''));
        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function sanitizePageFunction(string $pageFunction): string
    {
        return preg_replace(
            '/await\s+context\.enqueueRequest\s*\(\s*\{[\s\S]*?\}\s*\)\s*;?/m',
            '',
            $pageFunction
        ) ?: $pageFunction;
    }

    private function defaultPageFunction(): string
    {
        return <<<'JS'
async function pageFunction(context) {
    const $ = context.jQuery;

    const extractJsonObjectAfterMarker = (text, marker) => {
        const markerIndex = text.indexOf(marker);
        if (markerIndex === -1) {
            return null;
        }

        const equalIndex = text.indexOf('=', markerIndex);
        if (equalIndex === -1) {
            return null;
        }

        const start = text.indexOf('{', equalIndex);
        if (start === -1) {
            return null;
        }

        let depth = 0;
        let inString = false;
        let escaped = false;
        let quote = '';

        for (let i = start; i < text.length; i++) {
            const ch = text[i];

            if (inString) {
                if (escaped) {
                    escaped = false;
                    continue;
                }

                if (ch === '\\') {
                    escaped = true;
                    continue;
                }

                if (ch === quote) {
                    inString = false;
                    quote = '';
                }

                continue;
            }

            if (ch === '"' || ch === "'") {
                inString = true;
                quote = ch;
                continue;
            }

            if (ch === '{') {
                depth++;
            } else if (ch === '}') {
                depth--;
                if (depth === 0) {
                    const raw = text.slice(start, i + 1);
                    try {
                        return JSON.parse(raw);
                    } catch (error) {
                        return null;
                    }
                }
            }
        }

        return null;
    };

    const normalizeUrl = (value) => {
        if (!value || typeof value !== 'string') {
            return null;
        }

        let output = value.trim();
        if (!output) {
            return null;
        }

        if (output.startsWith('//')) {
            output = `https:${output}`;
        }

        if (!/^https?:\/\//i.test(output)) {
            return null;
        }

        return output;
    };

    const scriptsText = Array.from(document.querySelectorAll('script'))
        .map((script) => script.textContent || '')
        .join('\n');

    const detailData = extractJsonObjectAfterMarker(scriptsText, 'window.detailData');
    const nextDataRaw = document.querySelector('#__NEXT_DATA__')?.textContent || '';
    let nextData = null;
    if (nextDataRaw) {
        try {
            nextData = JSON.parse(nextDataRaw);
        } catch (error) {
            nextData = null;
        }
    }

    const title = (
        $('meta[property="og:title"]').attr('content')
        || $('meta[name="twitter:title"]').attr('content')
        || detailData?.globalData?.product?.subject
        || detailData?.globalData?.product?.title
        || nextData?.props?.pageProps?.title
        || $('h1').first().text()
        || $('title').first().text()
        || ''
    ).trim();

    const description = (
        $('meta[property="og:description"]').attr('content')
        || $('meta[name="description"]').attr('content')
        || detailData?.globalData?.product?.description
        || nextData?.props?.pageProps?.description
        || $('p').first().text()
        || ''
    ).trim();

    const imageCandidates = [];

    const mediaItems = detailData?.globalData?.product?.mediaItems || [];
    if (Array.isArray(mediaItems)) {
        mediaItems.forEach((item) => {
            if (!item || item.type !== 'image') {
                return;
            }

            const candidates = [
                item?.imageUrl?.big,
                item?.imageUrl?.origin,
                item?.imageUrl?.normal,
                item?.image,
                item?.url,
            ];

            candidates.forEach((candidate) => {
                const normalized = normalizeUrl(candidate);
                if (normalized) {
                    imageCandidates.push(normalized);
                }
            });
        });
    }

    $('img').each((_, element) => {
        const src =
            $(element).attr('src')
            || $(element).attr('data-src')
            || $(element).attr('data-lazy')
            || $(element).attr('data-ks-lazyload');

        const normalized = normalizeUrl(src);
        if (normalized) {
            imageCandidates.push(normalized);
        }
    });

    const filteredImages = imageCandidates.filter((value) => {
        const lower = value.toLowerCase();
        return lower.includes('alicdn.com') || lower.includes('alibaba.com') || lower.includes('images') || lower.includes('product');
    });

    const uniqueImages = (filteredImages.length > 0 ? filteredImages : imageCandidates)
        .filter((value, index, array) => array.indexOf(value) === index)
        .slice(0, 20);

    const variants = [];
    const skuAttrs = detailData?.globalData?.product?.sku?.skuAttrs || detailData?.globalData?.product?.sku?.skuSummaryAttrs || [];
    if (Array.isArray(skuAttrs)) {
        skuAttrs.forEach((attr) => {
            const attrName = String(attr?.name || '').toLowerCase();
            if (!attrName.includes('color') && !attrName.includes('colour') && !attrName.includes('style')) {
                return;
            }

            const values = Array.isArray(attr?.values) ? attr.values : [];
            values.forEach((entry) => {
                const name = String(entry?.name || entry?.value || '').trim();
                if (name) {
                    variants.push({
                        name,
                        quantity: 0,
                        low_stock_threshold: 5,
                    });
                }
            });
        });
    }

    const bodyText = $('body').text() || '';
    const priceMatches = bodyText.match(/(?:US\s*\$|USD|\$)\s*([0-9]+(?:\.[0-9]{1,2})?)/gi) || [];
    const numbers = priceMatches
        .map((match) => {
            const extracted = match.match(/([0-9]+(?:\.[0-9]{1,2})?)/);
            return extracted ? Number(extracted[1]) : null;
        })
        .filter((value) => Number.isFinite(value));

    const minPrice = numbers.length ? Math.min(...numbers) : null;
    const maxPrice = numbers.length ? Math.max(...numbers) : null;

    const specs = {};

    const detailSpecs = detailData?.globalData?.product?.productFeature || detailData?.globalData?.product?.specs || [];
    if (Array.isArray(detailSpecs)) {
        detailSpecs.forEach((entry) => {
            const key = String(entry?.attrName || entry?.name || entry?.key || '').trim();
            const value = String(entry?.attrValue || entry?.value || entry?.text || '').trim();
            if (key && value) {
                specs[key.toLowerCase()] = value;
            }
        });
    }

    $('table tr').each((_, row) => {
        const key = $(row).find('th, td').eq(0).text().trim();
        const value = $(row).find('th, td').eq(1).text().trim();
        if (key && value) {
            specs[key.toLowerCase()] = value;
        }
    });

    return {
        url: context.request.url,
        productUrl: context.request.url,
        titleTag: document.title || null,
        bodySample: bodyText.slice(0, 400),
        title,
        description,
        price_min: minPrice,
        price_max: maxPrice,
        currency: numbers.length ? 'USD' : null,
        images: uniqueImages,
        variants,
        specs,
    };
}
JS;
    }
}
