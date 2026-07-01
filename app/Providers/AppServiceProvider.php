<?php

namespace App\Providers;

use App\Models\Notification;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OllamaSSHClient::class,
            fn () => new OllamaSSHClient
        );

        $this->app->singleton(SimilarityService::class,
            fn ($app) => new SimilarityService(
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

        View::composer('student.*', function ($view) {
            if (auth()->check()) {
                $studentId = auth()->id();
                $unreadNotifications = Notification::where('student_id', $studentId)
                    ->where('is_read', false)
                    ->latest('created_at')
                    ->take(10)
                    ->get();
                $unreadCount = Notification::where('student_id', $studentId)
                    ->where('is_read', false)
                    ->count();
                $view->with(compact('unreadNotifications', 'unreadCount'));
            }
        });
    }
}
