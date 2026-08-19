<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Temporarily disabled per the panel's request to drop the forced
        // personal-info step for new student sign-ins - Google login
        // already supplies name, and there's no roster to auto-fill
        // student number/course/section from yet. The personal-info page
        // itself is untouched and still reachable at any time; this just
        // stops gating every other student route behind completing it
        // first. Re-enable by restoring the redirect below once there's a
        // real way to determine student number from the institutional
        // email (see student.personal-info.edit).
        return $next($request);
    }
}
