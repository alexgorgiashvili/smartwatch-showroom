<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    /**
     * Handle an incoming request by adding security headers
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // X-Content-Type-Options: nosniff
        // Prevents browsers from MIME-sniffing and forces them to respect the Content-Type header
        $response->header('X-Content-Type-Options', 'nosniff');

        // X-Frame-Options: DENY
        // Prevents the page from being displayed in a frame, combating clickjacking attacks
        // Use SAMEORIGIN if you need to allow framing from same origin
        $response->header('X-Frame-Options', 'DENY');

        // Content-Security-Policy
        // Restricts script sources and prevents XSS attacks
        $csp = $this->getContentSecurityPolicy();
        $response->header('Content-Security-Policy', $csp);

        // X-XSS-Protection: 1; mode=block
        // Enables XSS filtering in older browsers
        $response->header('X-XSS-Protection', '1; mode=block');

        // Strict-Transport-Security: max-age=31536000; includeSubDomains
        // Forces HTTPS for all future requests
        // Only set in production
        if (config('app.env') === 'production') {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        } elseif (config('app.env') === 'staging') {
            $response->header('Strict-Transport-Security', 'max-age=604800; includeSubDomains');
        }

        // Referrer-Policy
        // Controls how much referrer information is sent
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy (formerly Feature-Policy)
        // Controls which browser features and APIs can be used
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }

    /**
     * Get the Content Security Policy header value
     * Restricts sources for scripts, styles, and other resources
     */
    protected function getContentSecurityPolicy(): string
    {
        $policies = [
            // Default policy for all resource types not explicitly specified
            "default-src 'self'",

            // Script policy
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",

            // Style policy
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",

            // Image policy
            "img-src 'self' data: https:",

            // Font policy
            "font-src 'self' data: https://fonts.gstatic.com",

            // Connect policy (AJAX, WebSocket, etc.)
            "connect-src 'self' https: ws: wss:",

            // Frame/iframe policy
            "frame-src 'self'",

            // Object/embed policy
            "object-src 'none'",

            // Media policy (audio/video)
            "media-src 'self'",

            // Manifest policy
            "manifest-src 'self'",

            // Base URI restriction
            "base-uri 'self'",

            // Form action restriction (where forms can submit)
            "form-action 'self'",

            // Frame ancestors (who can frame this page)
            "frame-ancestors 'none'",

            // Upgrade insecure requests
            "upgrade-insecure-requests",

            // Block subresources over HTTP in HTTPS contexts
            "block-all-mixed-content",
        ];

        return implode('; ', $policies);
    }
}
