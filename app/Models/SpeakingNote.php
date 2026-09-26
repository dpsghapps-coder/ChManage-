<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notes of speaking to a member before communion. The text is encrypted in the database, and reading it needs the
 * Speaking permission (every read is written to the audit log).
 */
class SpeakingNote extends Model
{
    public const OUTCOMES = ['cleared' => 'Cleared for communion', 'follow_up' => 'Needs follow-up', 'deferred' => 'Deferred'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['spoken_on' => 'date', 'notes' => 'encrypted'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CommunionService::class, 'communion_service_id');
    }

    public function spokenBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'spoken_by_member_id');
    }

    public function spokenByName(): ?string
    {
        return $this->spokenBy?->full_name ?? $this->spoken_by_name;
    }
}
