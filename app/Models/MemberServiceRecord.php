<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A committee, executive or leadership post a member has held (or still holds). */
class MemberServiceRecord extends Model
{
    public const TYPES = ['committee' => 'Committee', 'executive' => 'Executive', 'leadership' => 'Leadership'];

    protected $table = 'member_service_records';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_on' => 'date', 'ended_on' => 'date'];
    }
}
