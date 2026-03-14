<?php

namespace App\Providers;

use App\Services\VectorizationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(VectorizationService::class, function ($app) {
            return new VectorizationService;
        });
    }
}
