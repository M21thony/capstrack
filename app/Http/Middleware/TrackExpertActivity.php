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
            $expert->last_seen_at = now();
            $expert->save();
        }

        return $next($request);
    }
}
