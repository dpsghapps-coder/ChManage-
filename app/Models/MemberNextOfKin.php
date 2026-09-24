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

    /** Relationships that make someone family, for the family tree (Friend and typed-in ones do not). */
    public const FAMILY_RELATIONSHIPS = ['Mother', 'Father', 'Aunt', 'Uncle', 'Grandmother', 'Grandfather', 'Sibling', 'Brother', 'Sister'];

    /**
     * The choices for the next of kin's and the emergency contact's relationship: the guardian types, Brother and
     * Sister, and Friend, with Other last (the form then asks for the relationship in words). The label itself is
     * stored, so any other text is kept as typed.
     *
     * @return list<string>
     */
    public static function relationships(): array
    {
        $guardians = collect(YoungMember::RELATIONSHIPS)->except('other')->values();

        return [...$guardians, 'Brother', 'Sister', 'Friend', YoungMember::RELATIONSHIPS['other']];
    }

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
