<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A member's baptism or confirmation: at most one of each kind. */
class MemberSacrament extends Model
{
    public const KINDS = ['baptism' => 'Baptism', 'confirmation' => 'Confirmation'];

    protected $table = 'member_sacraments';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sacrament_date' => 'date'];
    }
}
