<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The two ends of the idle sign-out in partials/idle-logout: "Stay signed
 * in" (or someone busy on a page that sends nothing) keeps the session, and
 * the countdown running out ends it.
 */
class IdleSessionController extends Controller
{
    /** Counts as activity by being an ordinary request; EndIdleSession does the rest. */
    public function keepAlive(): JsonResponse
    {
        if (! Auth::guard('web')->check() && ! Auth::guard('expert')->check()) {
            return response()->json(['status' => 'session_expired', 'redirect' => route('login')], 401);
        }

        // Online status on the expert lists reads this.
        if ($expert = Auth::guard('expert')->user()) {
            $expert->forceFill(['last_seen_at' => now()])->save();
        }

        return response()->json(['status' => 'active', 'idle_timeout' => (int) config('session.idle_timeout')]);
    }

    /** A GET, so it still works once the page's CSRF token has gone stale. */
    public function expired(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        Auth::guard('expert')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('session_expired', true);
    }
}
