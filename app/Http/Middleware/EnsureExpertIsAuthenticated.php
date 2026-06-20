<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureExpertIsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('expert')->check()) {
            return redirect('/login');
        }

        return $next($request);
    }
}
