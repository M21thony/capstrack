<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a student or an expert out after config('session.idle_timeout')
 * minutes in which they did nothing - in every portal.
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
                'message' => 'Your session expired after '.config('session.idle_timeout').' minutes of inactivity. Please sign in again.',
                'redirect' => route('login'),
            ], 401);
        }

        return redirect()->route('login')->with('session_expired', true);
    }
}
