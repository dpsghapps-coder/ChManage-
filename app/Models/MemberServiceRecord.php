<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A committee, executive or leadership post a member has held (or still holds). */
class MemberServiceRecord extends Model
{
    /** Stored type => label. "leadership" holds Session posts (Presbyter, Session Clerk, ...). */
    public const TYPES = ['committee' => 'Committee', 'executive' => 'Executive', 'leadership' => 'Session'];

    protected $table = 'member_service_records';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_on' => 'date', 'ended_on' => 'date', 'is_sample' => 'boolean'];
    }
}
