<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BogPayService
{
    public function getToken(): string
    {
        $response = Http::timeout(config('bog.timeout'))
            ->withBasicAuth(config('bog.client_id'), config('bog.secret_key'))
            ->asForm()
            ->post(config('bog.oauth_url'), [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch BOG access token.');
        }

        $token = $response->json('access_token');

        if (! $token) {
            throw new RuntimeException('BOG access token is missing in response.');
        }

        return $token;
    }

    public function create(Order $order, string $externalOrderId): array
    {
        $this->ensurePublicUrlsAreValid();

        $order->loadMissing(['items', 'adjustments']);
        $token = $this->getToken();

        $basket = $order->items->map(function ($item) {
            return [
                'product_id' => (string) $item->product_variant_id,
                'description' => $item->product_name . ' - ' . $item->variant_name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ];
        });

        $positiveAdjustments = $order->adjustments
            ->filter(fn ($adjustment): bool => (float) $adjustment->amount > 0);

        foreach ($positiveAdjustments as $adjustment) {
            $basket->push([
                'product_id' => 'adjustment-' . $adjustment->id,
                'description' => $adjustment->title,
                'quantity' => 1,
                'unit_price' => (float) $adjustment->amount,
            ]);
        }

        $discountAmount = abs((float) $order->adjustments
            ->filter(fn ($adjustment): bool => (float) $adjustment->amount < 0)
            ->sum('amount'));

        $basket = $this->applyDiscountToBasket($basket->values()->all(), $discountAmount);

        $payload = [
            'callback_url' => config('bog.callback_url'),
            'external_order_id' => $externalOrderId,
            'capture' => 'automatic',
            'language' => config('bog.language'),
            'purchase_units' => [
                'currency' => config('bog.currency'),
                'total_amount' => (float) $order->total_amount,
                'basket' => $basket,
            ],
            'redirect_urls' => [
                'success' => config('bog.success_url') . '?order=' . urlencode($order->order_number),
                'fail' => config('bog.fail_url') . '?order=' . urlencode($order->order_number),
            ],
        ];

        $response = Http::timeout(config('bog.timeout'))
            ->withToken($token)
            ->acceptJson()
            ->post(rtrim(config('bog.base_url'), '/') . '/payments/v1/ecommerce/orders', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Failed to create BOG order. HTTP %s. Response: %s',
                $response->status(),
                $response->body()
            ));
        }

        $bogOrderId = $response->json('id');
        $redirectUrl = $response->json('_links.redirect.href');

        if (! $bogOrderId || ! $redirectUrl) {
            throw new RuntimeException('Invalid BOG order response.');
        }

        return [
            'id' => $bogOrderId,
            'redirect_url' => $redirectUrl,
            'raw' => $response->json(),
        ];
    }

    public function getPaymentDetails(string $orderId): array
    {
        $token = $this->getToken();

        $response = Http::timeout(config('bog.timeout'))
            ->withToken($token)
            ->acceptJson()
            ->get(rtrim(config('bog.base_url'), '/') . '/payments/v1/receipt/' . rawurlencode($orderId));

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Failed to fetch BOG payment details. HTTP %s. Response: %s',
                $response->status(),
                $response->body()
            ));
        }

        $details = $response->json();
        $resolvedOrderId = $details['order_id'] ?? $details['id'] ?? null;

        if (! $resolvedOrderId) {
            throw new RuntimeException('Invalid BOG payment details response.');
        }

        return $details;
    }

    private function applyDiscountToBasket(array $basket, float $discountAmount): array
    {
        if ($discountAmount <= 0 || $basket === []) {
            return $basket;
        }

        $gross = collect($basket)->sum(fn (array $line): float => (float) $line['unit_price'] * (int) $line['quantity']);
        if ($gross <= 0) {
            return $basket;
        }

        $discountAmount = min($discountAmount, $gross);
        $discountCents = (int) round($discountAmount * 100);
        $remainingDiscountCents = $discountCents;
        $grossCents = max(1, (int) round($gross * 100));
        $lastIndex = count($basket) - 1;

        foreach ($basket as $index => $line) {
            $quantity = max(1, (int) $line['quantity']);
            $lineTotalCents = (int) round((float) $line['unit_price'] * $quantity * 100);
            $shareCents = $index === $lastIndex
                ? $remainingDiscountCents
                : (int) floor($discountCents * ($lineTotalCents / $grossCents));

            $shareCents = max(0, min($shareCents, max(0, $lineTotalCents - $quantity)));
            $remainingDiscountCents -= $shareCents;

            $netLineCents = max($quantity, $lineTotalCents - $shareCents);
            $basket[$index]['unit_price'] = round(($netLineCents / $quantity) / 100, 2);
        }

        return $basket;
    }

    private function ensurePublicUrlsAreValid(): void
    {
        $callbackUrl = (string) config('bog.callback_url');
        $successUrl = (string) config('bog.success_url');
        $failUrl = (string) config('bog.fail_url');

        foreach ([$callbackUrl, $successUrl, $failUrl] as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            $scheme = parse_url($url, PHP_URL_SCHEME);

            if (! $host || ! $scheme) {
                throw new RuntimeException('Invalid BOG redirect/callback URL configuration.');
            }

            $isLocalHost = in_array(strtolower((string) $host), ['localhost', '127.0.0.1', '::1'], true);
            if ($isLocalHost || strtolower((string) $scheme) !== 'https') {
                throw new RuntimeException('BOG requires public HTTPS callback/redirect URLs. Set BOG_PUBLIC_URL, e.g. https://mytechnic.ge');
            }
        }
    }
}
