<?php

namespace App\Support;

use App\Models\Member;
use App\Models\MemberSacrament;
use App\Models\MemberServiceRecord;
use App\Models\YoungMember;

/**
 * Everything the app shows about an adult member, in one place: the details page, the View modal, the edit form and the
 * printed profile report all read from here, so they can never disagree.
 */
class MemberProfile
{
    /** The member's own fields. @return array<string, mixed> */
    public static function adult(Member $m): array
    {
        $m->loadMissing(['photo', 'spouse', 'father', 'mother']);
        $linked = fn (?Member $other) => $other ? ['id' => $other->id, 'member_number' => $other->member_number, 'full_name' => $other->full_name] : null;

        return [
            'id' => $m->id,
            'member_number' => $m->member_number,
            'title' => $m->title,
            'first_name' => $m->first_name,
            'last_name' => $m->last_name,
            'other_names' => self::otherNames($m),
            'full_name' => $m->full_name,
            'sex' => $m->sex,
            'date_of_birth' => $m->date_of_birth?->toDateString(),
            'age' => $m->date_of_birth?->age,
            'place_of_birth' => $m->place_of_birth,
            'hometown' => $m->hometown,
            // Contact
            'mobile' => $m->mobile,
            'telephone' => $m->telephone,
            'email' => $m->email,
            'facebook_id' => $m->facebook_id,
            'instagram_id' => $m->instagram_id,
            'twitter_id' => $m->twitter_id,
            'tiktok_id' => $m->tiktok_id,
            'residence' => $m->residence,
            'latitude' => $m->latitude,
            'longitude' => $m->longitude,
            'location_accuracy' => $m->location_accuracy,
            // Marital
            'marital_status' => $m->marital_status,
            'marriage_type' => $m->marriage_type,
            'maiden_name' => $m->maiden_name,
            'marriage_date' => $m->marriage_date?->toDateString(),
            'marriage_church' => $m->marriage_church,
            'spouse_name' => $m->spouse?->full_name ?? $m->spouse_name,
            'spouse_member' => $m->spouse ? ['id' => $m->spouse->id, 'member_number' => $m->spouse->member_number, 'full_name' => $m->spouse->full_name] : null,
            // Family
            'father_name' => $m->father?->full_name ?? $m->father_name,
            'father_member' => $linked($m->father),
            'mother_name' => $m->mother?->full_name ?? $m->mother_name,
            'mother_member' => $linked($m->mother),
            // Church
            'joined_on' => $m->joined_on?->toDateString(),
            'generational_group' => $m->generational_group,
            // Sacraments
            'is_communicant' => $m->is_communicant,
            'non_communicant' => $m->is_communicant === false,
            'non_communicant_reason' => $m->non_communicant_reason,
            'status' => $m->status,
            'photo_url' => $m->photoUrl(),
        ];
    }

    /**
     * The records that hang off a member: next of kin, sacraments, groups, service and children. Children are not stored on the
     * member: they are the Children Service / Junior Youth members who list this member as a guardian.
     *
     * @return array<string, mixed>
     */
    public static function related(Member $m): array
    {
        $kin = $m->nextOfKin;
        $kin?->loadMissing(['relatedMember', 'emergencyContactMember']);
        $sacraments = $m->sacraments()->get()->keyBy('kind');
        $groups = $m->groups()->orderBy('name')->get(['member_groups.id', 'member_groups.name', 'member_groups.short_name']);
        $linkedMember = fn (?Member $linked) => $linked ? ['id' => $linked->id, 'member_number' => $linked->member_number, 'full_name' => $linked->full_name] : null;

        return [
            'next_of_kin' => [
                'name' => $kin?->name ?? '',
                'phone' => $kin?->phone ?? '',
                'residential_address' => $kin?->residential_address ?? '',
                'postal_address' => $kin?->postal_address ?? '',
                'member_id' => $kin?->related_member_id,
                'member' => $linkedMember($kin?->relatedMember),
            ],
            'emergency_contact' => [
                'name' => $kin?->emergency_contact_name ?? '',
                'phone' => $kin?->emergency_contact_phone ?? '',
                'relationship' => $kin?->emergency_contact_relationship ?? '',
                'member_id' => $kin?->emergency_contact_member_id,
                'member' => $linkedMember($kin?->emergencyContactMember),
            ],
            'sacraments' => collect(MemberSacrament::KINDS)->map(function ($label, $kind) use ($sacraments) {
                $s = $sacraments->get($kind);

                return [
                    'date' => $s?->sacrament_date?->toDateString() ?? '',
                    'presbytery' => $s?->presbytery ?? '',
                    'district' => $s?->district ?? '',
                    'place' => $s?->place ?? '',
                    'minister' => $s?->minister ?? '',
                ];
            })->all(),
            'group_ids' => $groups->pluck('id')->all(),
            'groups' => $groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'short_name' => $g->short_name])->values()->all(),
            'service_records' => $m->serviceRecords()->orderByDesc('started_on')->orderByDesc('id')->get()->map(fn (MemberServiceRecord $r) => [
                'type' => $r->type,
                'type_label' => MemberServiceRecord::TYPES[$r->type] ?? ucfirst($r->type),
                'name' => $r->name,
                'position' => $r->position ?? '',
                'started_on' => $r->started_on?->toDateString() ?? '',
                'ended_on' => $r->ended_on?->toDateString() ?? '',
            ])->values()->all(),
            // Adult members who name this member as their father or mother.
            'adult_children' => Member::query()
                ->where(fn ($q) => $q->where('father_member_id', $m->id)->orWhere('mother_member_id', $m->id))
                ->where('status', '!=', 'deleted')
                ->with('photo')
                ->orderBy('date_of_birth')
                ->get()
                ->map(fn (Member $child) => [
                    'id' => $child->id,
                    'name' => $child->full_name,
                    'member_number' => $child->member_number,
                    'relationship' => match (true) {
                        $child->sex === 'male' => 'Son',
                        $child->sex === 'female' => 'Daughter',
                        default => 'Child',
                    },
                    'photo_url' => $child->photoUrl(),
                ])->values()->all(),
            'children' => YoungMember::query()
                ->whereHas('guardians', fn ($q) => $q->where('member_id', $m->id))
                ->where('status', '!=', 'deleted')
                ->with(['member.photo', 'guardians' => fn ($q) => $q->where('member_id', $m->id)])
                ->orderBy('date_of_birth')
                ->get()
                ->map(fn (YoungMember $y) => [
                    'id' => $y->id,
                    'name' => trim("{$y->first_name} {$y->last_name}"),
                    'member_number' => $y->member_number,
                    'class' => $y->ageClass(),
                    'status' => $y->status,
                    'relationship' => $y->guardians->first()?->relationshipLabel() ?? '',
                    'photo_url' => $y->photoUrl(),
                ])->values()->all(),
        ];
    }

    /** The words of the full name that are neither the first name nor the surname. */
    public static function otherNames(Member $m): string
    {
        $known = collect(preg_split('/\s+/', mb_strtolower(trim("{$m->last_name} {$m->first_name}"))))->filter();

        return collect(preg_split('/\s+/', trim((string) $m->full_name)))
            ->filter()
            ->reject(fn ($word) => $known->contains(mb_strtolower($word)))
            ->implode(' ');
    }
}
