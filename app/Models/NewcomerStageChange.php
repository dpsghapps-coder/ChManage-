<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a newcomer's history: a move between stages (`kind` stage) or a change of status (`kind` status). */
class NewcomerStageChange extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['changed_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
