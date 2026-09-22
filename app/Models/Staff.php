<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Staff extends Model
{
    protected $table = 'staff';

    public const STATUSES = ['active', 'on_leave', 'suspended', 'terminated'];

    protected $fillable = [
        'staff_number', 'title', 'full_name', 'sex', 'date_of_birth', 'telephone', 'email', 'address',
        'ssnit_number', 'department_id', 'position_id', 'location', 'member_id', 'status', 'joined_on',
        'left_on', 'emergency_contact_name', 'emergency_contact_phone', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            'joined_on' => 'date:Y-m-d',
            'left_on' => 'date:Y-m-d',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** The login account linked to this staff member, if any. */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(StaffTransfer::class)->orderByDesc('effective_on')->orderByDesc('id');
    }

    /** Next STF-0001 style number. */
    public static function nextStaffNumber(): string
    {
        $last = static::query()->where('staff_number', 'like', 'STF-%')->orderByDesc('staff_number')->value('staff_number');

        return sprintf('STF-%04d', $last ? ((int) substr($last, 4)) + 1 : 1);
    }
}
