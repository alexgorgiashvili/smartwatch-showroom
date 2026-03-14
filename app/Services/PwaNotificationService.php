<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class PwaNotificationService
{
    public function sendConversationNotification(
        User $user,
        string $title,
        string $body,
        int $conversationId,
        array $additionalData = []
    ): void {
        try {
            $subscriptions = $user->pushSubscriptions ?? [];

            if (empty($subscriptions)) {
                return;
            }

            $payload = [
                'title' => $title,
                'body' => $body,
                'icon' => asset('images/notification-icon.png'),
                'badge' => asset('images/notification-badge.png'),
                'data' => array_merge([
                    'url' => url('/admin/inbox?conversation=' . $conversationId),
                    'conversation_id' => $conversationId,
                    'timestamp' => now()->toIso8601String(),
                ], $additionalData),
                'actions' => [
                    [
                        'action' => 'open',
                        'title' => 'Open',
                    ],
                    [
                        'action' => 'close',
                        'title' => 'Dismiss',
                    ],
                ],
                'tag' => 'conversation-' . $conversationId,
                'renotify' => true,
                'requireInteraction' => false,
            ];

            foreach ($subscriptions as $subscription) {
                $this->sendToSubscription($subscription, $payload);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send PWA notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendToSubscription(array $subscription, array $payload): void
    {
        // This would integrate with a web push service like WebPush library
        // For now, this is a placeholder for the actual implementation
        Log::info('PWA notification queued', [
            'endpoint' => $subscription['endpoint'] ?? 'unknown',
            'payload' => $payload,
        ]);
    }

    public function sendBulkNotification(
        array $userIds,
        string $title,
        string $body,
        string $url,
        array $additionalData = []
    ): void {
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            
            if (!$user) {
                continue;
            }

            $this->sendGenericNotification($user, $title, $body, $url, $additionalData);
        }
    }

    public function sendGenericNotification(
        User $user,
        string $title,
        string $body,
        string $url,
        array $additionalData = []
    ): void {
        try {
            $subscriptions = $user->pushSubscriptions ?? [];

            if (empty($subscriptions)) {
                return;
            }

            $payload = [
                'title' => $title,
                'body' => $body,
                'icon' => asset('images/notification-icon.png'),
                'badge' => asset('images/notification-badge.png'),
                'data' => array_merge([
                    'url' => $url,
                    'timestamp' => now()->toIso8601String(),
                ], $additionalData),
                'actions' => [
                    [
                        'action' => 'open',
                        'title' => 'Open',
                    ],
                ],
            ];

            foreach ($subscriptions as $subscription) {
                $this->sendToSubscription($subscription, $payload);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send generic PWA notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
