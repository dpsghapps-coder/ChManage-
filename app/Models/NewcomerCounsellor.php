<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A church member who counsels newcomers. Former counsellors are made inactive so old records keep their name. */
class NewcomerCounsellor extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function newcomers(): HasMany
    {
        return $this->hasMany(Newcomer::class, 'counsellor_id');
    }
}
