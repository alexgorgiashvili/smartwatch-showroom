<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Audit Trait
 *
 * Provides audit logging functionality for tracking admin actions
 * Logs all admin operations with user, timestamp, IP address, and method details
 */
trait AuditTrait
{
    /**
     * Log an admin audit action
     *
     * @param string $action The action being performed (e.g., 'inbox.message.send')
     * @param array $parameters The action parameters (sanitized, no sensitive data)
     * @param string|null $description Optional description of the action
     * @param int|null $statusCode HTTP status code if available
     * @return bool
     */
    protected function audit(
        string $action,
        array $parameters = [],
        ?string $description = null,
        ?int $statusCode = null
    ): bool {
        try {
            $user = Auth::user();

            if (!$user) {
                Log::warning('Audit log attempted without authenticated user', [
                    'action' => $action,
                ]);
                return false;
            }

            // Sanitize parameters - remove sensitive data
            $sanitizedParams = $this->sanitizeParameters($parameters);

            // Get request information
            $request = request();
            $ipAddress = $this->getClientIpAddress($request);
            $userAgent = $request->header('User-Agent', '');

            // Log to database
            DB::table('admin_audit_logs')->insert([
                'user_id' => $user->id,
                'action' => $action,
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'parameters' => json_encode($sanitizedParams),
                'description' => $description,
                'status_code' => $statusCode ?? 200,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Audit log recorded', [
                'user_id' => $user->id,
                'action' => $action,
                'endpoint' => $request->path(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error recording audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sanitize parameters to remove sensitive data
     * Removes passwords, tokens, API keys, credit cards, etc.
     *
     * @param array $parameters
     * @return array
     */
    protected function sanitizeParameters(array $parameters): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'api_key',
            'api_secret',
            'token',
            'secret',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
            'stripe_token',
            'auth_token',
            'access_token',
            'refresh_token',
            'private_key',
        ];

        $sanitized = [];

        foreach ($parameters as $key => $value) {
            $keyLower = strtolower($key);

            // Check if this key is sensitive
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($keyLower, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeParameters($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Get client IP address from request
     * Handles proxied requests (X-Forwarded-For, X-Real-IP, etc.)
     *
     * @param \Illuminate\Http\Request $request
     * @return string
     */
    protected function getClientIpAddress($request): string
    {
        // Check for IP passed from proxy
        if (!empty($request->server('HTTP_CLIENT_IP'))) {
            $ip = $request->server('HTTP_CLIENT_IP');
        } elseif (!empty($request->server('HTTP_X_FORWARDED_FOR'))) {
            // Handle multiple IPs in X-Forwarded-For (take the first one)
            $ips = explode(',', $request->server('HTTP_X_FORWARDED_FOR'));
            $ip = trim($ips[0]);
        } elseif (!empty($request->server('HTTP_X_REAL_IP'))) {
            $ip = $request->server('HTTP_X_REAL_IP');
        } else {
            // Default to remote address
            $ip = $request->ip();
        }

        // Validate IP address
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return $request->ip() ?? '0.0.0.0';
    }

    /**
     * Log failed security event (failed auth, rate limit, etc.)
     *
     * @param string $eventType The type of security event
     * @param array $details Event details
     * @return bool
     */
    protected function auditSecurityEvent(
        string $eventType,
        array $details = []
    ): bool {
        try {
            $request = request();
            $ipAddress = $this->getClientIpAddress($request);

            DB::table('admin_audit_logs')->insert([
                'user_id' => Auth::id(),
                'action' => 'security:' . $eventType,
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'ip_address' => $ipAddress,
                'user_agent' => $request->header('User-Agent', ''),
                'parameters' => json_encode($details),
                'description' => 'Security event detected',
                'status_code' => 401,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::warning('Security event logged', [
                'event_type' => $eventType,
                'ip_address' => $ipAddress,
                'details' => $details,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error recording security audit log', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
