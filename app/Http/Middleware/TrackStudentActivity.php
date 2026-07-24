<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackStudentActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $student = Auth::user();
            $student->last_seen_at = now();
            $student->save();
        }

        return $next($request);
    }
}
