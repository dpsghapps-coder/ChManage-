<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One communion service: its date, and who received communion at it. */
class CommunionService extends Model
{
    public const STATUSES = ['scheduled' => 'Scheduled', 'held' => 'Held', 'cancelled' => 'Cancelled'];

    /** Received, did not, or was away for a good reason. */
    public const ATTENDANCE = ['present' => 'Received', 'absent' => 'Absent', 'excused' => 'Excused'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['held_on' => 'date'];
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(CommunionAttendee::class);
    }

    public function speakingNotes(): HasMany
    {
        return $this->hasMany(SpeakingNote::class);
    }
}
