<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckExpertApproval
{
    public function handle(Request $request, Closure $next): Response
    {
        $expert = Auth::guard('expert')->user();

        if (! $expert) {
            return redirect('/login');
        }

        if ($expert->is_staff) {
            return $next($request);
        }

        if (! $expert->approved_status) {
            return redirect('/login/rejected?email='.urlencode($expert->email));
        }

        return $next($request);
    }
}
