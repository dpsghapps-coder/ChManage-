<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\PortalSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Member sign-in: phone number and date of birth, on the `member` guard (a session of its own). A date of birth is easy
 * to guess, so failures lock the number and the address out for a while; a code sent by WhatsApp or SMS will later be
 * added as a second step after {@see PortalSignIn::matches()}.
 */
class PortalAuthController extends Controller
{
    private const PER_NUMBER = 5;

    private const PER_ADDRESS = 20;

    private const MINUTES = 15;

    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('member')->check()) {
            return to_route('portal.home');
        }

        return Inertia::render('portal/login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'date_of_birth' => ['required', 'date', 'before:today'],
        ]);

        $number = 'portal-login:'.(PortalSignIn::local($data['phone']) ?? sha1($data['phone'])).'|'.$request->ip();
        $address = 'portal-login-ip:'.$request->ip();

        foreach ([$number => self::PER_NUMBER, $address => self::PER_ADDRESS] as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

                throw ValidationException::withMessages(['phone' => "Too many tries. Please wait {$minutes} minute(s) and try again."]);
            }
        }

        $matches = PortalSignIn::matches($data['phone'], $data['date_of_birth']);

        if ($matches->count() > 1) {
            throw ValidationException::withMessages(['phone' => 'This number is shared by more than one member. Please contact the church office.']);
        }

        if ($matches->isEmpty()) {
            foreach ([$number, $address] as $key) {
                RateLimiter::hit($key, self::MINUTES * 60);
            }

            throw ValidationException::withMessages(['phone' => "We couldn't match those details. Check the number and date of birth and try again."]);
        }

        RateLimiter::clear($number);

        $member = $matches->first();
        Auth::guard('member')->login($member);
        $request->session()->regenerate();

        Audit::record('portal.signed_in', "{$member->full_name} signed in to the member portal", $member, [], null);

        return to_route('portal.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('member')->logout();
        $request->session()->regenerateToken();

        return to_route('portal.login');
    }
}
