<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** While there are no user accounts, the sign-in page sends the visitor to first-time setup instead. */
class RedirectIfNotSetUp
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->routeIs('login') && User::count() === 0) {
            return redirect()->route('setup.show');
        }

        return $next($request);
    }
}
