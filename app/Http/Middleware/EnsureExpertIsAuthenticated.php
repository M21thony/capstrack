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
            $sessionKey = Auth::guard('expert')->getName();
            $storedExpertId = $request->session()->get($sessionKey);

            if ($storedExpertId) {
                $removedData = cache()->get('expert_removed_'.$storedExpertId);

                if ($removedData) {
                    cache()->forget('expert_removed_'.$storedExpertId);

                    return redirect('/login?removed_expert=1&name='.urlencode($removedData['name']).'&reason='.urlencode($removedData['reason']));
                }
            }

            return redirect('/login');
        }

        return $next($request);
    }
}
