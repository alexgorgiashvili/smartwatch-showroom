<?php

namespace App\Services;

use App\Models\PushSubscription;

class PushNotificationService
{
    public function sendToAdmins(string $title, string $body, string $url, array $data = []): bool
    {
        $subscriptions = PushSubscription::query()
            ->whereHas('user', fn ($query) => $query->where('is_admin', true))
            ->get();

        return $this->sendToSubscriptions($subscriptions, $title, $body, $url, $data);
    }

    public function sendToUser(int $userId, string $title, string $body, string $url, array $data = []): bool
    {
        $subscriptions = PushSubscription::query()
            ->where('user_id', $userId)
            ->get();

        return $this->sendToSubscriptions($subscriptions, $title, $body, $url, $data);
    }

    private function sendToSubscriptions($subscriptions, string $title, string $body, string $url, array $data = []): bool
    {
        $webPush = $this->newWebPushClient();

        if (! $webPush || $subscriptions->isEmpty()) {
            return false;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);

        if (! $payload) {
            return false;
        }

        foreach ($subscriptions as $subscriptionRow) {
            $subscriptionClass = '\\Minishlink\\WebPush\\Subscription';
            $subscription = $subscriptionClass::create([
                'endpoint' => $subscriptionRow->endpoint,
                'publicKey' => $subscriptionRow->public_key,
                'authToken' => $subscriptionRow->auth_token,
                'contentEncoding' => $subscriptionRow->content_encoding ?: 'aes128gcm',
            ]);

            $webPush->queueNotification($subscription, $payload, ['TTL' => 300]);
        }

        $sentAny = false;

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            $endpointHash = hash('sha256', $endpoint);

            if ($report->isSuccess()) {
                $sentAny = true;
                PushSubscription::query()->where('endpoint_hash', $endpointHash)->update([
                    'last_used_at' => now(),
                ]);
                continue;
            }

            $reason = $report->getReason();
            if (str_contains($reason, '410') || str_contains($reason, '404')) {
                PushSubscription::query()->where('endpoint_hash', $endpointHash)->delete();
            }
        }

        return $sentAny;
    }

    private function newWebPushClient(): ?object
    {
        $webPushClass = '\\Minishlink\\WebPush\\WebPush';

        if (! class_exists($webPushClass)) {
            return null;
        }

        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');

        if (! $publicKey || ! $privateKey) {
            return null;
        }

        return new $webPushClass([
            'VAPID' => [
                'subject' => config('services.webpush.subject', 'mailto:admin@localhost'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);
    }
}
