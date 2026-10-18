<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a student or an expert out after config('session.idle_timeout')
 * minutes in which they did nothing - thirty days by default, in every portal.
 *
 * "Did something" means a request they caused. A page's own background
 * checks send X-Idle-Passive and are not counted, or an open tab polling the
 * bell would keep a walked-away session alive for ever. The page itself
 * reports clicks, typing and scrolling (partials/idle-logout), so reading a
 * long page is not idleness.
 */
class EndIdleSession
{
    public const SESSION_KEY = 'idle.last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        $minutes = (int) config('session.idle_timeout');

        if ($minutes <= 0 || ! $request->hasSession() || ! $this->signedIn()) {
            return $next($request);
        }

        $session = $request->session();
        $last = (int) $session->get(self::SESSION_KEY, 0);

        if ($last > 0 && now()->getTimestamp() - $last >= $minutes * 60) {
            return $this->expire($request);
        }

        if ($last === 0 || ! $request->headers->has('X-Idle-Passive')) {
            $session->put(self::SESSION_KEY, now()->getTimestamp());
        }

        return $next($request);
    }

    /** The idle limit as a person says it: "30 days", "2 hours", "10 minutes". */
    public static function limitForHumans(?int $minutes = null): string
    {
        $minutes ??= (int) config('session.idle_timeout');

        [$amount, $unit] = match (true) {
            $minutes >= 1440 && $minutes % 1440 === 0 => [intdiv($minutes, 1440), 'day'],
            $minutes >= 60 && $minutes % 60 === 0 => [intdiv($minutes, 60), 'hour'],
            default => [$minutes, 'minute'],
        };

        return $amount.' '.Str::plural($unit, $amount);
    }

    private function signedIn(): bool
    {
        return Auth::guard('web')->check() || Auth::guard('expert')->check();
    }

    private function expire(Request $request): Response
    {
        Auth::guard('web')->logout();
        Auth::guard('expert')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'session_expired',
                'message' => 'Your session expired after '.self::limitForHumans().' of inactivity. Please sign in again.',
                'redirect' => route('login'),
            ], 401);
        }

        return redirect()->route('login')->with('session_expired', true);
    }
}
