<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A church committee, offered as the name of a committee service record. */
class Committee extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** Every term of everyone who has served on it. */
    public function members(): HasMany
    {
        return $this->hasMany(CommitteeMember::class)->orderBy('started_on');
    }
}
