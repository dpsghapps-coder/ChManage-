<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * A login account. It has exactly one role (a bundle of permissions) and may be linked to one staff member.
 * Transferring the staff member never changes the account or its role.
 *
 * @property int $id
 * @property string $username
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $name Derived: "First Last", falling back to the username
 * @property string|null $email
 * @property string|null $password NULL until the user is given a password
 * @property int|null $role_id
 * @property int|null $staff_id
 * @property bool $is_active
 * @property bool $must_reset_password
 * @property Carbon|null $last_login_at
 */
#[Fillable([
    'username', 'first_name', 'last_name', 'email', 'password', 'role_id', 'staff_id', 'is_active',
    'must_reset_password', 'password_changed_at', 'photo_id',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** Permission names, resolved once per request instance. @var list<string>|null */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_reset_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::get(function () {
            $full = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

            return $full !== '' ? $full : $this->username;
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * The church member this account belongs to, through its staff record; null when it is not linked to one.
     * Read from the database rather than the `staff` relation, which the shared page props load with only a few
     * columns (so `member_id` is not on it).
     */
    public function linkedMemberId(): ?int
    {
        return $this->staff_id ? Staff::whereKey($this->staff_id)->value('member_id') : null;
    }

    public function isAdmin(): bool
    {
        return $this->role?->slug === Role::ADMIN;
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        if (! $this->role) {
            return $this->permissionCache = [];
        }

        return $this->permissionCache = $this->role->permissions()->pluck('name')->all();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isAdmin() || in_array($permission, $this->permissionNames(), true);
    }

    /** @param  list<string>  $permissions */
    public function hasAnyPermission(array $permissions): bool
    {
        return $this->isAdmin() || array_intersect($permissions, $this->permissionNames()) !== [];
    }

    /** Forget cached permissions after the role or its permissions changed mid-request. */
    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('role');
    }
}
