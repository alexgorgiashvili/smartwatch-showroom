<?php

namespace App\Providers;

use App\Models\ContactSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        if (!app()->runningInConsole()) {
            session(['locale' => 'ka']);
            app()->setLocale('ka');
        }

        View::composer('*', function ($view) {
            $settings = ContactSetting::DEFAULTS;

            if (Schema::hasTable('contact_settings')) {
                $settings = ContactSetting::allKeyed();
            }

            $view->with('contactSettings', $settings);
        });
    }
}
