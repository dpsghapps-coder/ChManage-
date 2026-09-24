<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A PCG presbytery. Records (church settings, sacraments) store its name as text, so renaming does not rewrite them. */
class Presbytery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['facts' => 'array'];
    }

    public function districts(): HasMany
    {
        return $this->hasMany(PresbyteryDistrict::class)->orderBy('name');
    }
}
