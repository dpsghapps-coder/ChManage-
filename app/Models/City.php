<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A city whose neighbourhoods the member form can suggest for Residence (chosen by the City in Church Settings). */
class City extends Model
{
    protected $guarded = [];

    public function neighbourhoods(): HasMany
    {
        return $this->hasMany(Neighbourhood::class)->orderBy('name');
    }
}
