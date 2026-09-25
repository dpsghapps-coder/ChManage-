<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Something the meeting decided, or a formal resolution it passed. The actions that follow hang off it. */
class MeetingDecision extends Model
{
    public const KINDS = ['decision' => 'Decision', 'resolution' => 'Resolution'];

    protected $guarded = [];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(MeetingAction::class, 'decision_id')->orderByRaw('deadline is null')->orderBy('deadline')->orderBy('id');
    }
}
