<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** The screen users land on after signing in with a temporary password. */
class ForcePasswordChangeController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_reset_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/force-password-change', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
            'must_reset_password' => false,
            'password_changed_at' => now(),
        ]);

        Audit::record('auth.password_changed', 'User replaced their temporary password', $request->user());

        return redirect()->route('dashboard');
    }
}
