<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyMetaWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip verification for GET requests (used for initial webhook setup verification)
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            Log::warning('Meta Webhook missing X-Hub-Signature-256 header', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Missing signature'], 403);
        }

        // Extract the actual hash (format is "sha256=hash")
        $signatureHash = str_replace('sha256=', '', $signature);
        
        $appSecret = config('services.facebook.app_secret');
        
        if (empty($appSecret)) {
            Log::error('Meta App Secret not configured in environment');
            return response()->json(['error' => 'Server configuration error'], 500);
        }

        $payload = $request->getContent();
        $expectedHash = hash_hmac('sha256', $payload, $appSecret);

        if (!hash_equals($expectedHash, $signatureHash)) {
            Log::warning('Meta Webhook signature mismatch', [
                'ip' => $request->ip(),
                'provided' => $signatureHash,
                'expected' => $expectedHash
            ]);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        return $next($request);
    }
}
