<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A member who is no longer active loses the portal on their very next request. */
class EnsurePortalMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = Auth::guard('member')->user();

        if ($member && $member->status !== 'active') {
            Auth::guard('member')->logout();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login');
        }

        return $next($request);
    }
}
