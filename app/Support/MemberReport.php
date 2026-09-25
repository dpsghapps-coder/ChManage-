<?php

namespace App\Support;

use App\Models\ChurchSetting;
use App\Models\Member;
use App\Models\YoungMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The content of the printed Member Profile Report: the church heading and the member's details grouped into blocks.
 * A block is either a list of label / value rows or a table. Rows with nothing to show are left out, and so are empty blocks.
 */
class MemberReport
{
    /** @return array<string, mixed> */
    public static function adult(Member $member): array
    {
        $m = MemberProfile::adult($member);
        $r = MemberProfile::related($member);
        $sacrament = fn (string $kind) => self::sacrament($r['sacraments'][$kind]);

        return self::report(
            name: trim(($m['title'] ? "{$m['title']} " : '').$m['full_name']),
            number: $m['member_number'],
            status: $m['status'],
            photo: $member->photoPath(),
            blocks: [
                self::rows('Basic Information', [
                    'Member ID' => $m['member_number'],
                    'Title' => $m['title'],
                    'First Name' => $m['first_name'],
                    'Surname' => $m['last_name'],
                    'Other Names' => $m['other_names'],
                    'Sex' => self::label($m['sex']),
                    'Date of Birth' => self::dateWithAge($m['date_of_birth'], $m['age']),
                    'Place of Birth' => $m['place_of_birth'],
                    'Home Town' => $m['hometown'],
                    'Occupation / Profession' => $m['occupation'],
                ]),
                self::rows('Contact', [
                    'Primary Mobile' => $m['mobile'],
                    'Secondary Mobile' => $m['telephone'],
                    'Email' => $m['email'],
                    'Facebook' => $m['facebook_id'],
                    'Instagram' => $m['instagram_id'],
                    'Twitter / X' => $m['twitter_id'],
                    'TikTok' => $m['tiktok_id'],
                    'Residence' => $m['residence'],
                    'Home Location' => $m['latitude'] !== null && $m['longitude'] !== null ? "{$m['latitude']}, {$m['longitude']}" : null,
                    'Emergency Contact' => self::withMemberNo($r['emergency_contact']['name'], $r['emergency_contact']['member']),
                    'Emergency Contact Phone' => $r['emergency_contact']['phone'],
                    'Emergency Contact Relationship' => $r['emergency_contact']['relationship'],
                ]),
                self::rows('Family', [
                    "Father's Name" => $m['father_name'].($m['father_member'] ? " ({$m['father_member']['member_number']})" : ''),
                    "Mother's Name" => $m['mother_name'].($m['mother_member'] ? " ({$m['mother_member']['member_number']})" : ''),
                    'Marital Status' => self::label($m['marital_status']),
                    // Marriage details are printed for married members only.
                    ...($m['marital_status'] !== 'married' ? [] : [
                        'Marriage Type' => self::label($m['marriage_type']),
                        'Date of Marriage' => self::date($m['marriage_date']),
                        'Church of Marriage' => $m['marriage_church'],
                        'Maiden Name' => $m['maiden_name'],
                        'Spouse' => $m['spouse_name'].($m['spouse_member'] ? " ({$m['spouse_member']['member_number']})" : ''),
                    ]),
                    'Next of Kin' => self::withMemberNo($r['next_of_kin']['name'], $r['next_of_kin']['member']),
                    'Next of Kin Relationship' => $r['next_of_kin']['relationship'],
                    'Next of Kin Phone' => $r['next_of_kin']['phone'],
                    'Next of Kin Residential Address' => $r['next_of_kin']['residential_address'],
                    'Next of Kin Postal Address' => $r['next_of_kin']['postal_address'],
                ]),
                self::rows('Church', [
                    'Date Joined' => self::date($m['joined_on']),
                    'Previous Congregation' => $m['previous_congregation'],
                    'Generational Group' => $m['generational_group'],
                    'Service Groups' => collect($r['groups'])->pluck('name')->implode(', '),
                ]),
                self::table('Service History', ['Name', 'Type', 'Position', 'Period'], collect($r['service_records'])->map(fn ($s) => [
                    $s['name'], $s['type_label'], $s['position'],
                    self::date($s['started_on']).' – '.($s['ended_on'] ? self::date($s['ended_on']) : 'Present'),
                ])->all()),
                self::rows('Sacraments', [
                    'Baptism' => $sacrament('baptism'),
                    'Confirmation' => $sacrament('confirmation'),
                    'Communicant' => $m['is_communicant'] === null ? null : ($m['non_communicant'] ? 'No (non-communicant)' : 'Yes'),
                    'Reason' => $m['non_communicant'] ? $m['non_communicant_reason'] : null,
                ]),
                self::table('Children', ['Name', 'Member No.', 'Class', 'Relationship'], collect($r['children'])->map(fn ($c) => [
                    $c['name'], $c['member_number'], preg_replace('/ \(.*\)$/', '', (string) $c['class']), $c['relationship'],
                ])->all()),
            ],
        );
    }

    /** @return array<string, mixed> */
    public static function young(YoungMember $child): array
    {
        $child->loadMissing(['guardians.member', 'member.photo']);
        $name = trim("{$child->first_name} {$child->last_name}");

        return self::report(
            name: $name,
            number: $child->member_number,
            status: $child->status,
            photo: $child->photoPath(),
            blocks: [
                self::rows('Basic Information', [
                    'Member No.' => $child->member_number,
                    'First Name' => $child->first_name,
                    'Surname' => $child->last_name,
                    'Other Names' => $child->other_names,
                    'Sex' => self::label($child->sex),
                    'Date of Birth' => self::dateWithAge($child->date_of_birth?->toDateString(), $child->date_of_birth?->age),
                    'Class' => $child->ageClass(),
                    'Date Joined' => self::date($child->joined_on?->toDateString()),
                ]),
                self::rows('Contact', [
                    'Contact' => $child->mobile,
                    'Contact 2' => $child->telephone,
                    'Home Location' => $child->latitude !== null && $child->longitude !== null ? "{$child->latitude}, {$child->longitude}" : null,
                ]),
                self::table('Guardians', ['Name', 'Relationship', 'Member No.', 'Phone'], $child->guardians->map(fn ($g) => [
                    $g->displayName().($g->is_primary ? ' (primary)' : ''),
                    $g->relationshipLabel(),
                    $g->member?->member_number,
                    implode(' / ', $g->displayPhones()),
                ])->all()),
            ],
        );
    }

    /** @param  list<array<string, mixed>|null>  $blocks  @return array<string, mixed> */
    private static function report(string $name, string $number, string $status, ?string $photo, array $blocks): array
    {
        $stored = ChurchSetting::values(array_values(ChurchSetting::IDENTITY));

        return [
            'church' => [
                'name' => $stored['church_name'] ?? '',
                'congregation' => $stored['congregation_name'] ?? '',
                'presbytery' => $stored['presbytery_name'] ?? '',
                'district' => $stored['district_name'] ?? '',
            ],
            'logo' => self::logoData(),
            'name' => $name,
            'number' => $number,
            'status' => self::label($status),
            'photo' => self::photoData($photo),
            'initials' => self::initials($name),
            'generated' => now()->format('d F Y'),
            'blocks' => array_values(array_filter($blocks)),
        ];
    }

    /** A block of label / value rows, or null when there is nothing to show. */
    private static function rows(string $title, array $rows): ?array
    {
        $rows = array_filter($rows, fn ($value) => filled(is_string($value) ? trim($value) : $value));

        return $rows ? ['type' => 'rows', 'title' => $title, 'rows' => self::pack($rows)] : null;
    }

    /**
     * Lay label/value pairs two to a line to save paper; a value long enough to wrap gets the line to itself.
     *
     * @param  array<string, string>  $rows
     * @return list<list<array{0: string, 1: string}>>
     */
    private static function pack(array $rows): array
    {
        $lines = [];
        $pending = null;

        foreach ($rows as $label => $value) {
            $pair = [$label, (string) $value];

            if (mb_strlen($pair[1]) > 26) {
                if ($pending) {
                    $lines[] = [$pending];
                    $pending = null;
                }
                $lines[] = [$pair];

                continue;
            }

            if ($pending) {
                $lines[] = [$pending, $pair];
                $pending = null;
            } else {
                $pending = $pair;
            }
        }

        if ($pending) {
            $lines[] = [$pending];
        }

        return $lines;
    }

    /** Initials for the photo placeholder shown when a member has no photo on file. */
    private static function initials(string $name): string
    {
        $letters = collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2);

        return $letters->implode('') ?: '?';
    }

    /** @param  list<string>  $headers  @param  list<list<?string>>  $rows */
    private static function table(string $title, array $headers, array $rows): ?array
    {
        return $rows ? ['type' => 'table', 'title' => $title, 'headers' => $headers, 'rows' => $rows] : null;
    }

    private static function sacrament(array $s): ?string
    {
        // "Ebenezer Congregation, Osu District, Ga Presbytery"
        $church = collect([
            $s['place'] ?: null,
            ($s['district'] ?? '') !== '' ? Presbyteries::districtTitle($s['district']) : null,
            ($s['presbytery'] ?? '') !== '' ? Presbyteries::presbyteryTitle($s['presbytery']) : null,
        ])->filter()->implode(', ');

        if (! $s['date'] && ! $church && ! $s['minister']) {
            return null;
        }

        return trim(self::date($s['date']).($church ? " at {$church}" : '').($s['minister'] ? " ({$s['minister']})" : ''));
    }

    private static function date(?string $date): ?string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : null;
    }

    private static function dateWithAge(?string $date, ?int $age): ?string
    {
        return $date ? self::date($date).($age !== null ? " ({$age} years)" : '') : null;
    }

    private static function label(?string $value): ?string
    {
        return $value ? ucfirst(str_replace('_', ' ', $value)) : null;
    }

    /** A name, with the linked member's number appended when they are also a member. */
    private static function withMemberNo(?string $name, ?array $member): ?string
    {
        return $member ? trim(($name ?: '').' ('.$member['member_number'].')') : $name;
    }

    /** The photo as an inline image, so the PDF never has to reach for a file path. */
    private static function photoData(?string $path): ?string
    {
        if (! $path || ! is_file($path) || filesize($path) > 2_000_000) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/jpeg';

        return $mime && str_starts_with($mime, 'image/') ? "data:{$mime};base64,".base64_encode((string) file_get_contents($path)) : null;
    }

    /** The church crest, inlined once and cached — it never changes between reports. */
    private static function logoData(): ?string
    {
        return Cache::rememberForever('reports.church-logo', function () {
            $path = resource_path('images/church-crest.png');

            return is_file($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
        });
    }
}
