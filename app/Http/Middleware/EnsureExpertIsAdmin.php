<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureExpertIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $expert = Auth::guard('expert')->user();

        if (! $expert || ! $expert->is_admin) {
            abort(403);
        }

        return $next($request);
    }
}
