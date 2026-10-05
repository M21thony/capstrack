<?php

use App\Http\Middleware\CheckExpertApproval;
use App\Http\Middleware\EndIdleSession;
use App\Http\Middleware\EnsureExpertIsAdmin;
use App\Http\Middleware\EnsureExpertIsAuthenticated;
use App\Http\Middleware\EnsureExpertIsCapsa;
use App\Http\Middleware\EnsureExpertIsStaff;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackExpertActivity;
use App\Http\Middleware\TrackStudentActivity;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as LaravelPreventRequestForgery;
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

        // On every response: web pages, the API, files and the health check.
        $middleware->append(SecurityHeaders::class);

        // Ten idle minutes and anyone signed in, on any portal, is signed out.
        $middleware->web(append: [EndIdleSession::class], replace: [LaravelPreventRequestForgery::class => PreventRequestForgery::class]);
        $middleware->api(append: [EndIdleSession::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Anything under api/* answers in JSON whatever the caller asked for.
        // Everywhere else, honour what the caller actually asked for: a
        // fetch() sending Accept: application/json was being handed an HTML
        // error page, and calling .json() on that is where the student-facing
        // "Unexpected token '<'" came from. Ordinary form posts send no such
        // header, so they still redirect back with the errors in the session.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
