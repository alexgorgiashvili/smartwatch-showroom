<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function sendToAdmins(string $title, string $body, string $url = '', array $data = []): void
    {
        try {
            $subscriptions = PushSubscription::whereHas('user', function ($query) {
                $query->where('is_admin', true);
            })->get();

            if ($subscriptions->isEmpty()) {
                Log::debug('No push subscriptions found for admins');
                return;
            }

            // Filter out subscriptions for users currently on inbox page
            $subscriptions = $subscriptions->filter(function ($subscription) {
                $sessionKey = 'user_' . $subscription->user_id . '_on_inbox_page';
                return !cache()->get($sessionKey, false);
            });

            if ($subscriptions->isEmpty()) {
                Log::debug('All admins are currently on inbox page, skipping push notifications');
                return;
            }

            $this->sendToSubscriptions($subscriptions, $title, $body, $url, $data);
        } catch (\Exception $e) {
            Log::warning('Failed to send push notifications to admins', [
                'error' => $e->getMessage(),
            ]);
            // Don't throw - push notifications are non-critical
        }
    }

    public function sendToUser(int $userId, string $title, string $body, string $url, array $data = []): bool
    {
        try {
            $subscriptions = PushSubscription::query()
                ->where('user_id', $userId)
                ->get();

            return $this->sendToSubscriptions($subscriptions, $title, $body, $url, $data);
        } catch (\Exception $e) {
            Log::warning('Failed to send push notification to user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function sendToSubscriptions($subscriptions, string $title, string $body, string $url, array $data = []): bool
    {
        try {
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
        } catch (\Exception $e) {
            Log::warning('WebPush error - continuing without push notifications', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function newWebPushClient(): ?object
    {
        $this->ensureOpenSslConfig();

        if (! function_exists('openssl_pkey_new')) {
            Log::warning('WebPush unavailable: OpenSSL extension is missing.');
            return null;
        }

        $probeOptions = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ];

        $openSslConfigPath = $this->resolveOpenSslConfigPath();
        if ($openSslConfigPath !== null) {
            $probeOptions['config'] = $openSslConfigPath;
        }

        $opensslProbe = openssl_pkey_new($probeOptions);

        if ($opensslProbe === false) {
            Log::warning('WebPush unavailable: EC key generation failed.', [
                'openssl_errors' => $this->consumeOpenSslErrors(),
                'openssl_conf' => getenv('OPENSSL_CONF') ?: null,
            ]);
            return null;
        }

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

    private function ensureOpenSslConfig(): void
    {
        $current = $this->resolveOpenSslConfigPath();
        if ($current !== null) {
            putenv('OPENSSL_CONF=' . $current);
            return;
        }

        $candidates = [];

        $iniPath = php_ini_loaded_file();
        if (is_string($iniPath) && $iniPath !== '') {
            $phpDir = dirname($iniPath);
            $candidates[] = $phpDir . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
            $candidates[] = dirname($phpDir) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
        }

        $defaultConfig = (string) ini_get('openssl.default_config');
        if ($defaultConfig !== '') {
            $candidates[] = $defaultConfig;
        }

        $candidates[] = 'C:\\Program Files\\Common Files\\SSL\\openssl.cnf';

        foreach (array_unique($candidates) as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                putenv('OPENSSL_CONF=' . $candidate);
                return;
            }
        }
    }

    private function resolveOpenSslConfigPath(): ?string
    {
        $current = (string) getenv('OPENSSL_CONF');
        if ($current !== '' && is_file($current)) {
            return $current;
        }

        $defaultConfig = (string) ini_get('openssl.default_config');
        if ($defaultConfig !== '' && is_file($defaultConfig)) {
            return $defaultConfig;
        }

        return null;
    }

    private function consumeOpenSslErrors(): array
    {
        $errors = [];

        while ($error = openssl_error_string()) {
            $errors[] = $error;
        }

        return $errors;
    }
}
