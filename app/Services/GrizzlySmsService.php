<?php

namespace App\Services;

use App\Models\SmsActivation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GrizzlySmsService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.grizzly_sms.key', '');
        $this->baseUrl = config('services.grizzly_sms.base_url', 'https://api.grizzlysms.com/stubs/handler_api.php');
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    // -------------------------------------------------------------------------
    // Balance
    // -------------------------------------------------------------------------

    public function getBalance(): array
    {
        $response = $this->call(['action' => 'getBalance']);

        if (! $response['success']) {
            return $response;
        }

        $body = $response['body'];

        if (str_starts_with($body, 'ACCESS_BALANCE:')) {
            $balance = (float) str_replace('ACCESS_BALANCE:', '', $body);

            return ['success' => true, 'balance' => $balance];
        }

        return ['success' => false, 'error' => $body];
    }

    // -------------------------------------------------------------------------
    // Countries & Services (cached)
    // -------------------------------------------------------------------------

    public function getCountries(): array
    {
        return Cache::remember('grizzly_sms:countries', now()->addHour(), function () {
            $response = $this->call(['action' => 'getCountries']);

            if (! $response['success']) {
                return [];
            }

            $data = json_decode($response['body'], true);

            if (! is_array($data)) {
                return [];
            }

            // API returns keyed object: {"1":{"id":1,"eng":"Ukraine",...}, "10":{...}}
            return collect($data)
                ->map(fn (array $item) => [
                    'id' => $item['id'],
                    'name' => $item['eng'] ?? $item['rus'] ?? (string) $item['id'],
                ])
                ->sortBy('name')
                ->values()
                ->all();
        });
    }

    /**
     * Get available services for a specific country from getPrices.
     * Returns [{code, name, cost, count},...] sorted by name.
     */
    public function getServicesForCountry(string $country): array
    {
        return Cache::remember("grizzly_sms:services:{$country}", now()->addMinutes(10), function () use ($country) {
            $result = $this->getPrices($country);

            if (! $result['success']) {
                return [];
            }

            $prices = $result['prices'];

            $countryData = $this->extractCountryPriceData($prices, $country);

            if (empty($countryData)) {
                return [];
            }

            $nameMap = self::serviceNameMap();

            return collect($countryData)
                ->map(function (mixed $info, string $code) use ($nameMap): ?array {
                    if (! is_array($info)) {
                        return null;
                    }

                    return [
                        'code' => $code,
                        'name' => $nameMap[$code] ?? strtoupper($code),
                        'cost' => $this->extractNumericValue($info, ['cost', 'price', 'cost_with_fee', 'costWithFee', 'activationCost']),
                        'count' => (int) $this->extractNumericValue($info, ['count', 'quantity', 'available', 'stock', 'inStock']),
                    ];
                })
                ->filter(fn (?array $item) => is_array($item) && $item['count'] > 0)
                ->sortBy([
                    fn ($a, $b) => ($b['count'] ?? 0) - ($a['count'] ?? 0),
                    'name'
                ])
                ->values()
                ->all();
        });
    }

    /**
     * Common service code → human-readable name mapping.
     */
    public static function serviceNameMap(): array
    {
        return [
            'tg' => 'Telegram',
            'wa' => 'WhatsApp',
            'ig' => 'Instagram + Threads',
            'go' => 'Google / Gmail',
            'fb' => 'Facebook',
            'tw' => 'Twitter / X',
            'vi' => 'Viber',
            'ot' => 'AnyOther',
            'dr' => 'OpenAI / ChatGPT',
            'cl' => 'Claude',
            'ai' => 'Claude / AI',
            'nau' => 'Claude',
            'am' => 'Amazon',
            'ds' => 'Discord',
            'tk' => 'TikTok',
            'ms' => 'Microsoft / Outlook',
            'mm' => 'Microsoft',
            'me' => 'Line Messenger',
            'oi' => 'Tinder',
            'da' => 'AnyOther',
            'av' => 'Avito',
            'ma' => 'Mail.ru',
            'mb' => 'Yahoo',
            'ew' => 'Nike',
            'lf' => 'TikTok / Douyin',
            'tn' => 'Netflix',
            'nf' => 'Netflix',
            'dl' => 'Deliveroo',
            'dz' => 'Uber',
            'yw' => 'Bumble',
            'sn' => 'Signal',
            'pp' => 'PayPal',
            'hw' => 'Huawei',
            'wx' => 'WeChat',
            'ki' => 'Viber',
            'ap' => 'Apple',
            'vm' => 'Vinted',
            'fu' => 'Snapchat',
            'kt' => 'KakaoTalk',
            'ya' => 'Yandex',
            'wo' => 'Wolt',
            'se' => 'Shein',
            'vk' => 'VKontakte',
            'ok' => 'Odnoklassniki',
            'bd' => 'Badoo',
            'wb' => 'Weibo',
            'qm' => 'QQ Mail',
            'mg' => 'Mail.ru',
            'gm' => 'Gmail',
            'yh' => 'Yahoo',
            'hm' => 'Hotmail / Outlook',
            'pm' => 'Pinterest',
            'sc' => 'Snapchat',
            'tb' => 'Tumblr',
            'rd' => 'Reddit',
            'ln' => 'LinkedIn',
            'sk' => 'Skype',
            'ic' => 'ICQ',
            'az' => 'Amazon',
            'eb' => 'eBay',
            'et' => 'Etsy',
            'cr' => 'Coinbase',
            'bl' => 'Binance',
            'st' => 'Steam',
            'ps' => 'PlayStation Network',
            'xp' => 'Xbox Live',
            'sp' => 'Spotify',
            'ad' => 'Apple Music',
            'yt' => 'YouTube',
            'nm' => 'Netflix',
            'hl' => 'Hulu',
            'dp' => 'Disney+',
            'hp' => 'HBO Max',
            'tv' => 'Twitch',
            'dc' => 'Discord',
            'tl' => 'Telegram',
            'ws' => 'WhatsApp',
            'fc' => 'Facebook',
            'igp' => 'Instagram',
            'tt' => 'TikTok',
            'twi' => 'Twitter / X',
            'lnk' => 'LinkedIn',
            'rdt' => 'Reddit',
            'pt' => 'Pinterest',
            'tbm' => 'Tumblr',
            'scs' => 'Snapchat',
            'vbr' => 'Viber',
            'lne' => 'Line',
            'tgr' => 'Telegram',
            'wts' => 'WhatsApp',
            'igl' => 'Instagram',
            'fbk' => 'Facebook',
            'twt' => 'Twitter / X',
            'lni' => 'LinkedIn',
            'rdi' => 'Reddit',
            'pti' => 'Pinterest',
            'tbl' => 'Tumblr',
            'snp' => 'Snapchat',
            'vbi' => 'Viber',
            'lin' => 'Line',
        ];
    }

    // -------------------------------------------------------------------------
    // Prices
    // -------------------------------------------------------------------------

    public function getPrices(string $country, string $service = ''): array
    {
        $params = ['action' => 'getPrices', 'country' => $country];

        if (filled($service)) {
            $params['service'] = $service;
        }

        $response = $this->call($params);

        if (! $response['success']) {
            return ['success' => false, 'error' => $response['error'] ?? 'Unknown error'];
        }

        $body = $response['body'];

        if (in_array($body, ['BAD_KEY', 'BAD_ACTION', 'BAD_SERVICE'])) {
            return ['success' => false, 'error' => $this->translateError($body)];
        }

        $data = json_decode($body, true);

        if (! is_array($data)) {
            return ['success' => false, 'error' => $body];
        }

        return ['success' => true, 'prices' => $data];
    }

    // -------------------------------------------------------------------------
    // Buy Number
    // -------------------------------------------------------------------------

    public function buyNumber(string $service, string $country, ?float $maxPrice = null, ?float $fallbackCost = null): array
    {
        $params = [
            'action' => 'getNumberV2',
            'service' => $service,
            'country' => $country,
        ];

        if ($maxPrice !== null) {
            $params['maxPrice'] = $maxPrice;
        }

        $response = $this->call($params);

        if (! $response['success']) {
            return $response;
        }

        $body = $response['body'];

        // Error strings
        if (in_array($body, ['NO_NUMBERS', 'NO_BALANCE', 'BAD_KEY', 'BAD_ACTION', 'BAD_SERVICE'])) {
            return ['success' => false, 'error' => $this->translateError($body)];
        }

        if (str_contains($body, 'prohibited') || str_contains($body, 'UNAVAILABLE')) {
            return ['success' => false, 'error' => $body];
        }

        $data = json_decode($body, true);

        if (! is_array($data) || empty($data['activationId'])) {
            // Fallback: try ACCESS_NUMBER:id:phone format
            if (str_starts_with($body, 'ACCESS_NUMBER:')) {
                $parts = explode(':', $body);

                return $this->createActivationFromParts($parts[1] ?? '', $parts[2] ?? '', $service, $country, $fallbackCost);
            }

            return ['success' => false, 'error' => 'Unexpected response: ' . $body];
        }

        $activationCost = $this->extractNumericValue($data, ['activationCost', 'cost', 'price'], $fallbackCost ?? 0);
        $currency = is_numeric($data['currency'] ?? null) ? (int) $data['currency'] : null;

        // Create local record
        $activation = SmsActivation::create([
            'activation_id' => (string) $data['activationId'],
            'phone_number' => $data['phoneNumber'] ?? '',
            'service' => $service,
            'country' => $country,
            'cost' => $activationCost,
            'currency' => $currency,
            'status' => 'pending',
        ]);

        // Resolve human-readable names
        $this->resolveNames($activation);

        return ['success' => true, 'activation' => $activation];
    }

    // -------------------------------------------------------------------------
    // Status Management
    // -------------------------------------------------------------------------

    public function checkStatus(string $activationId): array
    {
        $response = $this->call([
            'action' => 'getStatusV2',
            'id' => $activationId,
        ]);

        if (! $response['success']) {
            return $response;
        }

        $body = $response['body'];

        // Plain-text statuses
        if (in_array($body, ['STATUS_WAIT_CODE', 'STATUS_WAIT_RESEND', 'STATUS_CANCEL'])) {
            $localStatus = match ($body) {
                'STATUS_CANCEL' => 'cancelled',
                default => 'pending',
            };

            $activation = SmsActivation::where('activation_id', $activationId)->first();
            if ($activation && $activation->status !== $localStatus) {
                $activation->update(['status' => $localStatus]);
            }

            return ['success' => true, 'api_status' => $body, 'code' => null];
        }

        // STATUS_OK:code format
        if (str_starts_with($body, 'STATUS_OK:')) {
            $code = str_replace('STATUS_OK:', '', $body);

            $activation = SmsActivation::where('activation_id', $activationId)->first();
            if ($activation) {
                $activation->update([
                    'status' => 'code_received',
                    'sms_code' => $code,
                    'sms_received_at' => now(),
                ]);
            }

            return ['success' => true, 'api_status' => 'STATUS_OK', 'code' => $code];
        }

        // JSON response from getStatusV2
        $data = json_decode($body, true);

        if (is_array($data) && isset($data['sms'])) {
            $code = $data['sms']['code'] ?? null;
            $text = $data['sms']['text'] ?? null;
            $dateTime = $data['sms']['dateTime'] ?? null;

            $activation = SmsActivation::where('activation_id', $activationId)->first();
            if ($activation && $code) {
                $activation->update([
                    'status' => 'code_received',
                    'sms_code' => $code,
                    'sms_text' => $text,
                    'sms_received_at' => $dateTime ? \Carbon\Carbon::parse($dateTime) : now(),
                ]);
            }

            return ['success' => true, 'api_status' => 'STATUS_OK', 'code' => $code, 'text' => $text];
        }

        return ['success' => true, 'api_status' => $body, 'code' => null];
    }

    public function setStatus(string $activationId, int $status): array
    {
        $response = $this->call([
            'action' => 'setStatus',
            'id' => $activationId,
            'status' => $status,
        ]);

        if (! $response['success']) {
            return $response;
        }

        $body = $response['body'];

        // Map API response to local status
        $localStatus = match ($body) {
            'ACCESS_READY' => 'ready',
            'ACCESS_RETRY_GET' => 'pending',
            'ACCESS_ACTIVATION' => 'completed',
            'ACCESS_CANCEL' => 'cancelled',
            default => null,
        };

        if ($localStatus) {
            SmsActivation::where('activation_id', $activationId)
                ->update(['status' => $localStatus]);
        }

        $isError = in_array($body, ['ERROR_SQL', 'NO_ACTIVATION', 'BAD_STATUS', 'BAD_KEY', 'BAD_ACTION']);

        return [
            'success' => ! $isError,
            'response' => $body,
            'error' => $isError ? $this->translateError($body) : null,
        ];
    }

    public function cancelActivation(string $activationId): array
    {
        return $this->setStatus($activationId, 8);
    }

    public function completeActivation(string $activationId): array
    {
        return $this->setStatus($activationId, 6);
    }

    public function requestAnotherSms(string $activationId): array
    {
        return $this->setStatus($activationId, 3);
    }

    public function markReady(string $activationId): array
    {
        return $this->setStatus($activationId, 1);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function call(array $params): array
    {
        $params['api_key'] = $this->apiKey;

        try {
            $response = Http::timeout(15)
                ->withoutVerifying()
                ->get($this->baseUrl, $params);

            if ($response->failed()) {
                Log::error('GrizzlySMS API HTTP error', [
                    'status' => $response->status(),
                    'action' => $params['action'] ?? 'unknown',
                ]);

                return ['success' => false, 'error' => 'HTTP error: ' . $response->status()];
            }

            return ['success' => true, 'body' => trim($response->body())];
        } catch (\Throwable $e) {
            Log::error('GrizzlySMS API exception', [
                'action' => $params['action'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    private function extractCountryPriceData(array $prices, string $country): array
    {
        if (isset($prices[$country]) && is_array($prices[$country])) {
            return $prices[$country];
        }

        if ($this->looksLikeServiceMap($prices)) {
            return $prices;
        }

        $first = reset($prices);

        if (is_array($first) && $this->looksLikeServiceMap($first)) {
            return $first;
        }

        return [];
    }

    private function looksLikeServiceMap(array $data): bool
    {
        if ($data === []) {
            return false;
        }

        foreach ($data as $value) {
            if (! is_array($value)) {
                return false;
            }

            if (
                array_key_exists('cost', $value)
                || array_key_exists('price', $value)
                || array_key_exists('cost_with_fee', $value)
                || array_key_exists('count', $value)
                || array_key_exists('available', $value)
                || array_key_exists('stock', $value)
            ) {
                return true;
            }
        }

        return false;
    }

    private function extractNumericValue(array $data, array $keys, float $default = 0): float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if (is_string($value)) {
                $value = str_replace(',', '.', trim($value));
            }

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return $default;
    }

    private function createActivationFromParts(string $id, string $phone, string $service, string $country, ?float $cost = null): array
    {
        if (blank($id)) {
            return ['success' => false, 'error' => 'Invalid activation response'];
        }

        $activation = SmsActivation::create([
            'activation_id' => $id,
            'phone_number' => $phone,
            'service' => $service,
            'country' => $country,
            'cost' => $cost ?? 0,
            'status' => 'pending',
        ]);

        $this->resolveNames($activation);

        return ['success' => true, 'activation' => $activation];
    }

    private function resolveNames(SmsActivation $activation): void
    {
        try {
            $nameMap = self::serviceNameMap();
            $countries = $this->getCountries();

            $serviceName = $nameMap[$activation->service] ?? null;
            $countryName = collect($countries)->firstWhere('id', (int) $activation->country)['name'] ?? null;

            if ($serviceName || $countryName) {
                $activation->update(array_filter([
                    'service_name' => $serviceName,
                    'country_name' => $countryName,
                ]));
            }
        } catch (\Throwable $e) {
            // Non-critical, skip
        }
    }

    private function translateError(string $error): string
    {
        return match ($error) {
            'BAD_KEY' => 'Invalid API key. Check your Grizzly SMS settings.',
            'NO_NUMBERS' => 'No numbers available for this service/country. Try another.',
            'NO_BALANCE' => 'Insufficient balance. Top up your Grizzly SMS account.',
            'BAD_ACTION' => 'Invalid API action.',
            'BAD_SERVICE' => 'Invalid service code.',
            'BAD_STATUS' => 'Invalid status value.',
            'NO_ACTIVATION' => 'Activation not found.',
            'ERROR_SQL' => 'Grizzly SMS server error. Try again later.',
            default => $error,
        };
    }
}
