<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// "Staff" = either Admin or CAPSA - for admin-area pages either role should
// be able to reach (dashboard, profile, analytics, etc.), as opposed to the
// narrower expert.admin/expert.capsa checks for pages specific to one job.
class EnsureExpertIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $expert = Auth::guard('expert')->user();

        if (! $expert || ! $expert->is_staff) {
            abort(403);
        }

        return $next($request);
    }
}
