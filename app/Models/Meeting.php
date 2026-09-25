<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A meeting of a committee, with its agenda, attendees, minutes and the decisions taken. */
class Meeting extends Model
{
    public const STATUSES = ['scheduled' => 'Scheduled', 'held' => 'Held', 'cancelled' => 'Cancelled'];

    public const MINUTES = ['draft' => 'Draft', 'confirmed' => 'Confirmed'];

    public const ATTENDANCE = ['present' => 'Present', 'apologies' => 'Apologies', 'absent' => 'Absent'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meeting_date' => 'date', 'minutes_confirmed_on' => 'date'];
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function chairperson(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'chairperson_member_id');
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'secretary_member_id');
    }

    public function confirmedAt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'minutes_confirmed_at_meeting_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class)->orderBy('name');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(MeetingDecision::class)->orderBy('sort_order')->orderBy('id');
    }

    /** "Finance Committee" or the title given to this particular meeting. */
    public function heading(): string
    {
        return $this->title ?: (string) $this->committee?->name;
    }

    public function chairpersonName(): ?string
    {
        return $this->chairperson?->full_name ?? $this->chairperson_name;
    }

    public function secretaryName(): ?string
    {
        return $this->secretary?->full_name ?? $this->secretary_name;
    }
}
