<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** One term of one person on one committee. */
class CommitteeMember extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_on' => 'date', 'ends_on' => 'date', 'is_sample' => 'boolean'];
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** The term that renewed this one, if any. */
    public function successor(): HasOne
    {
        return $this->hasOne(self::class, 'renewed_from_id');
    }

    /** Serving now: the term has no end, or has not ended yet. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', today()));
    }

    /** Whole days left in the term (negative once it has ended); null when it has no end. */
    public function daysLeft(): ?int
    {
        return $this->ends_on ? (int) today()->diffInDays($this->ends_on, false) : null;
    }
}
