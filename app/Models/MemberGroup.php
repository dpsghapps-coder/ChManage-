<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A church group or society a member can belong to (choir, brigade ...). */
class MemberGroup extends Model
{
    protected $table = 'member_groups';

    public $timestamps = false;

    protected $guarded = [];
}
