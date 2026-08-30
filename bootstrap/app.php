<?php

use App\Http\Middleware\CheckExpertApproval;
use App\Http\Middleware\EnsureExpertIsAdmin;
use App\Http\Middleware\EnsureExpertIsAuthenticated;
use App\Http\Middleware\EnsureExpertIsCapsa;
use App\Http\Middleware\EnsureExpertIsStaff;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\TrackExpertActivity;
use App\Http\Middleware\TrackStudentActivity;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'profile.complete' => EnsureProfileIsComplete::class,
            'expert.auth' => EnsureExpertIsAuthenticated::class,
            'expert.admin' => EnsureExpertIsAdmin::class,
            'expert.capsa' => EnsureExpertIsCapsa::class,
            'expert.staff' => EnsureExpertIsStaff::class,
            'expert.approved' => CheckExpertApproval::class,
            'no.cache' => PreventBackHistory::class,
            'track.expert' => TrackExpertActivity::class,
            'track.student' => TrackStudentActivity::class,
        ]);

        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
