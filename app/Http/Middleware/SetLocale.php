<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->is('admin', 'admin/*')
            ? 'ka'
            : $request->session()->get('locale', config('app.locale', 'ka'));
        $locale = in_array($locale, ['ka', 'en'], true) ? $locale : 'ka';

        app()->setLocale($locale);

        return $next($request);
    }
}
