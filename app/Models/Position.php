<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A job title (Treasurer, Secretary ...). Not the same thing as a Role, which controls what a user may do. */
class Position extends Model
{
    protected $fillable = ['name'];

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
