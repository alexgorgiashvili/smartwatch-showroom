<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
            'expirationTime' => ['nullable'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $endpointHash = hash('sha256', $data['endpoint']);

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint_hash' => $endpointHash],
            [
                'user_id' => Auth::id(),
                'endpoint' => $data['endpoint'],
                'endpoint_hash' => $endpointHash,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $request->string('contentEncoding')->toString() ?: 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'id' => $subscription->id,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
        ]);

        $endpointHash = hash('sha256', $data['endpoint']);

        PushSubscription::query()
            ->where('user_id', Auth::id())
            ->where('endpoint_hash', $endpointHash)
            ->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function test(PushNotificationService $pushNotificationService): JsonResponse
    {
        $sent = $pushNotificationService->sendToUser(
            (int) Auth::id(),
            'Inbox Test',
            'Push notifications are configured correctly.',
            route('admin.inbox.index'),
            ['type' => 'test']
        );

        return response()->json([
            'success' => $sent,
        ]);
    }
}
