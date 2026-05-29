<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    public function handle($request, \Closure $next, ...$guards)
    {
        if (config('app.env') === 'local') {
            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson() || $request->is('api/*') || $request->is('broadcasting/*')) {
            return null;
        }

        return route('admin.login');
    }
}
