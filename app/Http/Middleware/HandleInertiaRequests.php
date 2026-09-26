<?php

namespace App\Http\Middleware;

use App\Models\MemberRequest;
use App\Support\CommitteeTerms;
use App\Support\RequestAccess;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->authUser($request),
                // "*" = administrator (everything). The UI uses this only to show/hide things;
                // the server enforces every permission on its own.
                'permissions' => $this->permissions($request),
            ],
            // Small counts shown on menu items, for those who may act on them.
            'badges' => $this->badges($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** @return array<string, int> */
    private function badges(Request $request): array
    {
        $user = $request->user('web');

        if (! $user) {
            return [];
        }

        $badges = [];

        if ($user->hasAnyPermission(['committees.manage'])) {
            $badges['committees'] = CommitteeTerms::endingSoon()->count();
        }

        // Present (even when 0) only for people who handle some member requests, which also shows them the menu item.
        $handled = RequestAccess::types($user);

        if ($handled !== []) {
            $badges['requests'] = MemberRequest::whereIn('type', $handled)->whereIn('status', MemberRequest::OPEN)->count();
        }

        return $badges;
    }

    /** @return array<string, mixed>|null */
    private function authUser(Request $request): ?array
    {
        $user = $request->user('web');

        if (! $user) {
            return null;
        }

        $user->loadMissing('role:id,name,slug', 'staff:id,staff_number,full_name');

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'must_reset_password' => $user->must_reset_password,
            'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'role' => $user->role ? ['name' => $user->role->name, 'slug' => $user->role->slug] : null,
            'staff' => $user->staff ? ['id' => $user->staff->id, 'staff_number' => $user->staff->staff_number, 'full_name' => $user->staff->full_name] : null,
        ];
    }

    /** @return list<string> */
    private function permissions(Request $request): array
    {
        $user = $request->user('web');

        if (! $user) {
            return [];
        }

        return $user->isAdmin() ? ['*'] : $user->permissionNames();
    }
}
