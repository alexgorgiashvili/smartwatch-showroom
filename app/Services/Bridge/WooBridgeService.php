<?php

namespace App\Services\Bridge;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WooBridgeService
{
    private ?array $statusCache = null;

    public function configured(): bool
    {
        return filled(config('services.bridge.base_url'))
            && filled(config('services.bridge.username'))
            && filled(config('services.bridge.app_password'));
    }

    public function status(): array
    {
        if ($this->statusCache !== null) {
            return $this->statusCache;
        }

        return $this->statusCache = $this->refreshStatus();
    }

    public function refreshStatus(): array
    {
        if ($this->statusCache !== null && ($this->statusCache['refreshed'] ?? false)) {
            return $this->statusCache;
        }

        $status = [
            'configured' => false,
            'reachable' => false,
            'authenticated' => false,
            'product_count' => 0,
            'base_url' => config('services.bridge.base_url'),
            'error' => null,
            'refreshed' => true,
        ];

        if (! $this->configured()) {
            $status['error'] = 'Bridge credentials are missing.';

            return $this->cacheStatus($status);
        }

        $baseUrl = rtrim((string) config('services.bridge.base_url'), '/');

        try {
            $authResponse = $this->baseRequest()
                ->baseUrl($baseUrl)
                ->acceptJson()
                ->asJson()
                ->withBasicAuth(
                    (string) config('services.bridge.username'),
                    (string) config('services.bridge.app_password')
                )
                ->timeout(10)
                ->get('/wp-json/wp/v2/users/me');

            $reachable = true;
            $authenticated = $authResponse->successful();
            $error = null;

            if (! $authenticated) {
                $status = $authResponse->status();
                $error = in_array($status, [401, 403], true)
                    ? 'Bridge authentication failed. WordPress rejected the provided credentials.'
                    : 'Bridge API returned HTTP ' . $status . '.';
            }

            $productCount = 0;

            if ($authenticated) {
                try {
                    $productsResponse = $this->client()
                        ->get('/wp-json/wc/v3/products', [
                            'per_page' => 1,
                        ]);

                    $productCount = (int) ($productsResponse->header('X-WP-Total') ?? count($productsResponse->json() ?: []));
                } catch (\Throwable $e) {
                    $error = $this->formatBridgeError($e);
                }
            }

            $status = [
                'configured' => true,
                'reachable' => $reachable,
                'authenticated' => $authenticated,
                'product_count' => $productCount,
                'base_url' => $baseUrl,
                'error' => $error,
                'refreshed' => true,
            ];
        } catch (\Throwable $e) {
            $status = [
                'configured' => true,
                'reachable' => false,
                'authenticated' => false,
                'product_count' => 0,
                'base_url' => $baseUrl,
                'error' => $this->formatBridgeError($e),
                'refreshed' => true,
            ];
        }

        return $this->cacheStatus($status);
    }

    public function cachedStatus(): ?array
    {
        if ($this->statusCache !== null && ! ($this->statusCache['refreshed'] ?? false)) {
            return $this->statusCache;
        }

        $status = Cache::get($this->statusCacheKey());

        if (is_array($status)) {
            return $this->statusCache = $status;
        }

        return null;
    }

    public function listProducts(?int $limit = null): array
    {
        return $this->refreshProducts($limit);
    }

    public function refreshProducts(?int $limit = null): array
    {
        $response = $this->client()->get('/wp-json/wc/v3/products', [
            'per_page' => $limit ?: (int) config('services.bridge.product_limit', 20),
            'orderby' => 'date',
            'order' => 'desc',
        ]);

        $response->throw();

        $products = $response->json() ?: [];

        Cache::put($this->productsCacheKey($limit), $products, now()->addMinutes(5));

        return $products;
    }

    public function cachedProducts(?int $limit = null): array
    {
        $products = Cache::get($this->productsCacheKey($limit));

        return is_array($products) ? $products : [];
    }

    public function getProduct(int $productId): array
    {
        $response = $this->client()->get("/wp-json/wc/v3/products/{$productId}");
        $response->throw();

        return $response->json() ?: [];
    }

    public function getProductVariations(int $productId): array
    {
        $response = $this->client()->get("/wp-json/wc/v3/products/{$productId}/variations", [
            'per_page' => 100,
        ]);
        $response->throw();

        return $response->json() ?: [];
    }

    public function createOrder(array $payload): array
    {
        $response = $this->client()->post('/wp-json/wc/v3/orders', $payload);
        $response->throw();

        return $response->json() ?: [];
    }

    public function getOrder(int $orderId): array
    {
        $response = $this->client()->get("/wp-json/wc/v3/orders/{$orderId}");
        $response->throw();

        return $response->json() ?: [];
    }

    private function client(): PendingRequest
    {
        return $this->baseRequest()
            ->baseUrl(rtrim((string) config('services.bridge.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth(
                (string) config('services.bridge.username'),
                (string) config('services.bridge.app_password')
            )
            ->timeout(20);
    }

    private function baseRequest(): PendingRequest
    {
        $request = Http::withOptions([
            'verify' => $this->shouldVerifySsl(),
        ]);

        $caBundle = config('services.bridge.ca_bundle');

        if (filled($caBundle)) {
            $request = $request->withOptions([
                'verify' => $caBundle,
            ]);
        }

        return $request;
    }

    private function shouldVerifySsl(): bool
    {
        $configured = config('services.bridge.verify_ssl');

        if ($configured === null) {
            return ! app()->environment('local');
        }

        return filter_var($configured, FILTER_VALIDATE_BOOL);
    }

    private function formatBridgeError(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains(strtolower($message), 'curl error 60')) {
            if (! $this->shouldVerifySsl()) {
                return 'Bridge SSL certificate validation failed, but SSL verification is disabled for this environment. Re-check the bridge URL or remote certificate chain.';
            }

            return 'Bridge SSL certificate could not be verified (cURL error 60). On local environments set BRIDGE_SSL_VERIFY=false, or configure BRIDGE_CA_BUNDLE with a trusted CA bundle.';
        }

        return $message;
    }

    private function statusCacheKey(): string
    {
        return 'bridge.status.' . md5((string) config('services.bridge.base_url'));
    }

    private function cacheStatus(array $status): array
    {
        $this->statusCache = $status;
        Cache::put($this->statusCacheKey(), $status, now()->addMinutes(2));

        return $status;
    }

    private function productsCacheKey(?int $limit = null): string
    {
        return 'bridge.products.' . md5((string) config('services.bridge.base_url')) . '.' . (int) ($limit ?: (int) config('services.bridge.product_limit', 20));
    }
}
