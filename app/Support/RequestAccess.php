<?php

namespace App\Support;

use App\Models\ChurchSetting;
use App\Models\User;

/**
 * Who handles which member request. Each type can be given to a role in Church Settings; a type with no role set is
 * handled by administrators only. People need the "requests.manage" permission for their role to receive a type.
 * On top of that, staff whose position is "Administrator" always receive requests to change details.
 */
class RequestAccess
{
    public const ADMIN_POSITION = 'Administrator';

    private static function key(string $type): string
    {
        return 'request_handler_'.$type;
    }

    /** Type => role id (or null when unset). @return array<string, ?int> */
    public static function handlers(): array
    {
        $stored = ChurchSetting::values(array_map(self::key(...), RequestTypes::keys()));

        return collect(RequestTypes::keys())->mapWithKeys(fn ($type) => [
            $type => filled($stored[self::key($type)] ?? null) ? (int) $stored[self::key($type)] : null,
        ])->all();
    }

    /** @param  array<string, mixed>  $handlers  type => role id or null */
    public static function save(array $handlers): void
    {
        foreach (RequestTypes::keys() as $type) {
            ChurchSetting::put(self::key($type), filled($handlers[$type] ?? null) ? (string) (int) $handlers[$type] : null);
        }
    }

    /** The request types this person handles. @return list<string> */
    public static function types(?User $user): array
    {
        if (! $user || ! $user->is_active) {
            return [];
        }

        if ($user->isAdmin()) {
            return RequestTypes::keys();
        }

        $types = [];

        if ($user->hasPermission('requests.manage')) {
            foreach (self::handlers() as $type => $roleId) {
                if ($roleId !== null && $roleId === $user->role_id) {
                    $types[] = $type;
                }
            }
        }

        if (self::isAdministratorPosition($user)) {
            $types[] = RequestTypes::CHANGE_DETAILS;
        }

        return array_values(array_unique($types));
    }

    public static function handles(?User $user, string $type): bool
    {
        return in_array($type, self::types($user), true);
    }

    private static function isAdministratorPosition(User $user): bool
    {
        return $user->staff_id !== null
            && $user->staff()->whereHas('position', fn ($p) => $p->whereRaw('lower(name) = ?', [mb_strtolower(self::ADMIN_POSITION)]))->exists();
    }
}
