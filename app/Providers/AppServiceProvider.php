<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $availableLocales = ['en', 'ka'];
        $requestedLocale = request()->get('lang');

        if ($requestedLocale && in_array($requestedLocale, $availableLocales, true)) {
            session(['locale' => $requestedLocale]);
        }

        $locale = session('locale', config('app.locale'));
        app()->setLocale($locale);
    }
}
