<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: `->middleware('permission:members.view')` or several, `permission:a.view,b.view`, of which
 * any one is enough. Administrators pass everything.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless($user, 401);
        abort_unless($user->role, 403, 'No role is assigned to your account.');
        abort_unless($user->hasAnyPermission($permissions), 403, 'You do not have permission to do that.');

        return $next($request);
    }
}
