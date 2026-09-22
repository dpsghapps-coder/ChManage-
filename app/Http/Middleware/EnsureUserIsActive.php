<?php

namespace App\Http\Middleware;

use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** An account deactivated while its owner is signed in loses access on the very next request. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Audit::record('auth.blocked', "Deactivated account {$user->username} was signed out", $user, [], $user->id);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'This account has been deactivated. Contact an administrator.']);
        }

        return $next($request);
    }
}
