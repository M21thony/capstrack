<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OllamaSSHClient::class,
            fn() => new OllamaSSHClient()
        );

        $this->app->singleton(SimilarityService::class,
            fn($app) => new SimilarityService(
                $app->make(OllamaSSHClient::class)
            )
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Schema::defaultStringLength(191);
    }
}
