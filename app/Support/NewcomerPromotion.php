<?php

namespace App\Support;

use App\Models\MediaFile;
use App\Models\Member;
use App\Models\MemberNextOfKin;
use App\Models\MemberSacrament;
use App\Models\Newcomer;
use App\Models\YoungMember;
use App\Models\YoungMemberGuardian;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Makes a catechumen a member: from 18 they go on the main register, under 18 on Junior Youth or Children Service.
 * Everything the newcomer form collected is carried over; what it did not ask for is left for staff to fill in on
 * the member form, which opens straight after. The newcomer record stays as history, linked to the new member.
 */
class NewcomerPromotion
{
    /** Where the person would land, from their age today: ['kind' => 'adult'|'young', 'label' => ...]. */
    public static function target(Newcomer $newcomer): ?array
    {
        if (! $newcomer->date_of_birth) {
            return null;
        }

        if ($newcomer->date_of_birth->age >= YoungMember::REGISTER_UNDER) {
            return ['kind' => 'adult', 'label' => 'the adult members list'];
        }

        return ['kind' => 'young', 'label' => YoungMember::departmentFor($newcomer->date_of_birth) === 'CS' ? 'Children Service' : 'Junior Youth'];
    }

    /** What stops them being made a member now; empty when they can be. @return list<string> */
    public static function blockers(Newcomer $newcomer): array
    {
        $problems = [];

        if ($newcomer->stage !== 'catechumen') {
            $problems[] = 'Only a catechumen can be made a member.';
        }

        if ($newcomer->member_id || $newcomer->young_member_id) {
            $problems[] = 'They are already a member.';
        }

        if (! $newcomer->date_of_birth) {
            $problems[] = 'Add their date of birth first: it decides which members list they join.';
        } elseif (self::target($newcomer)['kind'] === 'adult' && ! $newcomer->sex) {
            $problems[] = 'Add their gender first: the adult register needs it.';
        }

        return $problems;
    }

    /** @return array{0: 'adult'|'young', 1: int} the kind and id of the new record */
    public static function promote(Newcomer $newcomer, string $joinedOn): array
    {
        return DB::transaction(function () use ($newcomer, $joinedOn) {
            $kind = self::target($newcomer)['kind'];
            $record = $kind === 'adult' ? self::adult($newcomer, $joinedOn) : self::young($newcomer, $joinedOn);

            $newcomer->update([
                'stage' => 'member',
                'member_id' => $kind === 'adult' ? $record->id : null,
                'young_member_id' => $kind === 'young' ? $record->id : null,
                'made_member_on' => $joinedOn,
            ]);
            $newcomer->log('stage', 'catechumen', 'member', 'Made a member');

            return [$kind, $record->id];
        });
    }

    private static function adult(Newcomer $n, string $joinedOn): Member
    {
        $first = NameFormatter::titleCase($n->first_name);
        $last = NameFormatter::titleCase($n->surname);
        $other = NameFormatter::titleCase($n->middle_name);

        $member = Member::create([
            'member_number' => Member::nextMemberNumber(),
            'status' => 'active',
            'title' => $n->title,
            'first_name' => $first,
            'last_name' => $last,
            // Registered as "Surname Firstname Othernames", like the rest of the register.
            'full_name' => collect([$last, $first, $other])->filter()->implode(' '),
            'sex' => $n->sex,
            'date_of_birth' => $n->date_of_birth,
            'marital_status' => $n->marital_status,
            'marriage_type' => $n->marital_status === 'married' ? $n->marriage_type : null,
            'joined_on' => $joinedOn,
            'previous_congregation' => $n->former_church,
            'generational_group' => Member::generationalGroupFor($n->date_of_birth->toDateString(), $n->sex),
            'is_communicant' => $n->is_confirmed,
            'mobile' => $n->mobile,
            'telephone' => $n->other_numbers,
            'email' => $n->email,
            'latitude' => $n->latitude,
            'longitude' => $n->longitude,
            'location_accuracy' => $n->location_accuracy,
            'photo_id' => self::copyAdultPhoto($n)?->id,
        ]);

        foreach (['baptism' => $n->is_baptized, 'confirmation' => $n->is_confirmed] as $kind => $held) {
            if ($held) {
                MemberSacrament::create(['member_id' => $member->id, 'kind' => $kind]);
            }
        }

        if ($n->emergency_number) {
            MemberNextOfKin::create(['member_id' => $member->id, 'emergency_contact_phone' => $n->emergency_number]);
        }

        return $member;
    }

    private static function young(Newcomer $n, string $joinedOn): YoungMember
    {
        $young = YoungMember::create([
            'number_year' => now()->year,
            'number_seq' => YoungMember::nextSequence(),
            'first_name' => NameFormatter::titleCase($n->first_name),
            'last_name' => NameFormatter::titleCase($n->surname),
            'other_names' => NameFormatter::titleCase($n->middle_name),
            'sex' => $n->sex,
            'date_of_birth' => $n->date_of_birth,
            'joined_on' => $joinedOn,
            'mobile' => $n->mobile,
            'telephone' => $n->other_numbers,
            'status' => 'active',
            'latitude' => $n->latitude,
            'longitude' => $n->longitude,
            'location_accuracy' => $n->location_accuracy,
            'photo_path' => self::copyPhoto($n, config('church.young_member_photos')),
        ]);

        if (filled($n->guardian_name)) {
            $said = trim((string) $n->guardian_relationship);
            $key = strtolower($said);
            $known = array_key_exists($key, YoungMember::RELATIONSHIPS);

            YoungMemberGuardian::create([
                'young_member_id' => $young->id,
                'relationship' => $known ? $key : 'other',
                'relationship_other' => $known || $said === '' ? null : $said,
                'name' => $n->guardian_name,
                'phone' => $n->guardian_phone,
                'is_primary' => true,
            ]);
        }

        return $young;
    }

    /** The photo file is copied, not moved, so the newcomer's own record still shows it. */
    private static function copyPhoto(Newcomer $n, string $folder): ?string
    {
        $from = $n->photoPath();

        if (! $from) {
            return null;
        }

        $name = Str::uuid().'.'.pathinfo($from, PATHINFO_EXTENSION);
        @mkdir($folder, 0775, true);

        return copy($from, rtrim($folder, '/\\').DIRECTORY_SEPARATOR.$name) ? $name : null;
    }

    private static function copyAdultPhoto(Newcomer $n): ?MediaFile
    {
        $name = self::copyPhoto($n, config('church.member_photos'));

        return $name ? MediaFile::create([
            'filename' => $name, 'mime_type' => mime_content_type($n->photoPath()) ?: 'image/jpeg', 'size_bytes' => filesize($n->photoPath()),
            'directory' => 'img/members/', 'created_at' => now(),
        ]) : null;
    }
}
