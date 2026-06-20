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

        if ($expert->is_admin) {
            return $next($request);
        }

        if (! $expert->approved_status) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 'pending_approval',
                    'message' => 'Your account is pending approval.',
                ], 403);
            }

            return view('auth.pending-approval');
        }

        return $next($request);
    }
}
