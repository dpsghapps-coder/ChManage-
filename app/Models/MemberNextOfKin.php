<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A member's next of kin and emergency contact: one record per member. */
class MemberNextOfKin extends Model
{
    protected $table = 'member_next_of_kin';

    protected $primaryKey = 'member_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /** The next of kin's own member record, when they are also a member. */
    public function relatedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'related_member_id');
    }

    /** The emergency contact's own member record, when they are also a member. */
    public function emergencyContactMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'emergency_contact_member_id');
    }
}
