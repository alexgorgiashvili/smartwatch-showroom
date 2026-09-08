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
            : ($request->is('en', 'en/*')
                ? 'en'
                : $request->session()->get('locale', config('app.locale', 'ka')));
        $locale = in_array($locale, ['ka', 'en'], true) ? $locale : 'ka';

        if ($request->is('en', 'en/*')) {
            $request->session()->put('locale', 'en');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
