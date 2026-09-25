<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A place events are held: a chapel, the compound, the manse ... */
class EventVenue extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
