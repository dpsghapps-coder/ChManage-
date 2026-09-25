<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who has to do what by when, as a result of a decision. Only pending, in progress, completed and cancelled are
 * stored: an action still pending or in progress after its deadline is shown as overdue.
 */
class MeetingAction extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    /** What is shown: the stored statuses plus overdue. */
    public const SHOWN = ['pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['deadline' => 'date', 'completed_on' => 'date'];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(MeetingDecision::class, 'decision_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'responsible_member_id');
    }

    public function responsibleName(): ?string
    {
        return $this->responsible?->full_name ?? $this->responsible_name;
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['pending', 'in_progress'], true) && $this->deadline !== null && $this->deadline->lt(today());
    }

    /** The status to show: overdue once it is late, otherwise the stored one. */
    public function shownStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    /** Actions late for their deadline, in SQL. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'in_progress'])->whereNotNull('deadline')->where('deadline', '<', today());
    }

    /** Actions still to be done: pending or in progress, late or not. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }
}
