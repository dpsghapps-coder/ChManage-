<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresbyteryDistrict extends Model
{
    protected $guarded = [];

    public function presbytery(): BelongsTo
    {
        return $this->belongsTo(Presbytery::class);
    }
}
