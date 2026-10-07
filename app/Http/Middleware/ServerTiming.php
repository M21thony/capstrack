<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * On a local machine only, says where a request's time went.
 *
 * The browser shows it under Network, the request, then Timing: how long
 * Laravel took to start, how long the database took and over how many
 * queries, how long the rest of PHP took, and whether OPcache and Xdebug are
 * on - the two PHP settings that most often make a local server slow.
 * Nothing is added anywhere but APP_ENV=local.
 */
class ServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local')) {
            return $next($request);
        }

        $startedAt = microtime(true);
        $queries = 0;
        $databaseMs = 0.0;
        $slowestMs = 0.0;

        DB::listen(function (QueryExecuted $query) use (&$queries, &$databaseMs, &$slowestMs): void {
            $queries++;
            $databaseMs += $query->time;
            $slowestMs = max($slowestMs, $query->time);
        });

        $response = $next($request);

        $requestStart = defined('LARAVEL_START') ? LARAVEL_START : $startedAt;
        $bootMs = ($startedAt - $requestStart) * 1000;
        $totalMs = (microtime(true) - $requestStart) * 1000;
        $phpMs = max(0, $totalMs - $bootMs - $databaseMs);

        $response->headers->set('Server-Timing', implode(', ', [
            sprintf('boot;dur=%.1f;desc="Laravel start-up"', $bootMs),
            sprintf('db;dur=%.1f;desc="%d queries, slowest %.0f ms"', $databaseMs, $queries, $slowestMs),
            sprintf('php;dur=%.1f;desc="PHP and views"', $phpMs),
            sprintf('total;dur=%.1f;desc="Whole request"', $totalMs),
            sprintf('opcache;desc="OPcache %s"', $this->opcacheIsOn() ? 'on' : 'off'),
            sprintf('xdebug;desc="Xdebug %s"', extension_loaded('xdebug') ? 'on' : 'off'),
        ]));

        return $response;
    }

    private function opcacheIsOn(): bool
    {
        if (! function_exists('opcache_get_status')) {
            return false;
        }

        $status = @opcache_get_status(false);

        return is_array($status) && ($status['opcache_enabled'] ?? false);
    }
}
