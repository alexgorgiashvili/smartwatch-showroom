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
        // Shared Services
        $this->app->singleton(\App\Services\Chatbot\ProductContextService::class);
        $this->app->singleton(\App\Services\Chatbot\PromptBuilderService::class);
        $this->app->singleton(\App\Services\Chatbot\ModelCompletionService::class);

        // Infrastructure Services
        $this->app->singleton(\App\Services\Chatbot\MultiLayerCacheService::class);
        $this->app->singleton(\App\Services\Chatbot\CircuitBreakerService::class);
        $this->app->singleton(\App\Services\Chatbot\ParallelExecutionService::class);
        $this->app->singleton(\App\Services\Chatbot\BifurcatedMemoryService::class);
        $this->app->singleton(\App\Services\Chatbot\ConditionalReflectionService::class);

        // Agents
        $this->app->singleton(\App\Services\Chatbot\Agents\VectorSqlReconciliationAgent::class);
        $this->app->singleton(\App\Services\Chatbot\Agents\InventoryAgent::class);
        $this->app->singleton(\App\Services\Chatbot\Agents\ComparisonAgent::class);
        $this->app->singleton(\App\Services\Chatbot\Agents\GeneralAgent::class);
        $this->app->singleton(\App\Services\Chatbot\Agents\SupervisorAgent::class);
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
