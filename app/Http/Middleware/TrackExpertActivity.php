<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackExpertActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('expert')->check()) {
            $expert = Auth::guard('expert')->user();

            // CapstoneExpert::isOnline only needs last_seen_at accurate to
            // within 5 minutes, so skip the write unless it's actually stale
            // — otherwise every single page navigation costs a DB round trip
            // for no visible benefit.
            if (! $expert->last_seen_at || $expert->last_seen_at->lt(now()->subMinute())) {
                $expert->last_seen_at = now();
                $expert->save();
            }
        }

        return $next($request);
    }
}
