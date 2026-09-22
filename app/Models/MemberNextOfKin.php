<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A member's next of kin: one record per member. */
class MemberNextOfKin extends Model
{
    protected $table = 'member_next_of_kin';

    protected $primaryKey = 'member_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
