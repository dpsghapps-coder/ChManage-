<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The main (adult) register. Junior Youth and Children Service live in {@see YoungMember}.
 * A member can also sign in to the member portal (the `member` guard) — never to the staff side, which uses {@see User}.
 */
class Member extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $guarded = [];

    /** Generational groups: stored value => name. CS and JY only reach older under-18 records on this register. */
    public const GENERATIONAL_GROUPS = [
        'CS' => 'Children Service (CS)',
        'JY' => 'Junior Youth (JY)',
        'YPG' => "Young People's Guild (YPG)",
        'YAF' => "Young Adults' Fellowship (YAF)",
        "Men's Fellowship" => "Men's Fellowship",
        "Women's Fellowship" => "Women's Fellowship",
    ];

    /**
     * The generational group follows age and sex: 0–14 Children Service, 15–17 Junior Youth, 18–29 YPG, 30–39 YAF,
     * 40 and over Men's or Women's Fellowship. (New under-18s go on the Junior Youth / Children Service register, which
     * keeps its own age split.) Unknown without a date of birth, or for 40 and over without a sex.
     */
    public static function generationalGroupFor(mixed $dateOfBirth, ?string $sex): ?string
    {
        if (! filled($dateOfBirth)) {
            return null;
        }

        $age = Carbon::parse($dateOfBirth)->age;

        return match (true) {
            $age < 15 => 'CS',
            $age < 18 => 'JY',
            $age < 30 => 'YPG',
            $age < 40 => 'YAF',
            $sex === 'male' => "Men's Fellowship",
            $sex === 'female' => "Women's Fellowship",
            default => null,
        };
    }

    /**
     * Brings every member's stored generational group up to date (run daily, as members age into the next group).
     * Only rows whose group changes are written. Returns how many changed.
     */
    public static function syncGenerationalGroups(): int
    {
        $changed = 0;

        static::query()->select(['id', 'date_of_birth', 'sex', 'generational_group'])->chunkById(500, function ($members) use (&$changed) {
            foreach ($members as $member) {
                $group = static::generationalGroupFor($member->date_of_birth, $member->sex);

                if ($group !== $member->generational_group) {
                    // A group changing with age is not an edit of the record, so updated_at is kept as it was
                    // (set to itself: the column has ON UPDATE CURRENT_TIMESTAMP).
                    static::whereKey($member->id)->toBase()->update(['generational_group' => $group, 'updated_at' => DB::raw('updated_at')]);
                    $changed++;
                }
            }
        });

        return $changed;
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joined_on' => 'date',
            'marriage_date' => 'date',
            'is_communicant' => 'boolean',
            'is_child' => 'boolean',
            'is_verified' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** The register's full name, falling back to "Surname Firstname" for any record saved without one. */
    protected function fullName(): Attribute
    {
        return Attribute::get(function (?string $value, array $attributes) {
            if (trim((string) $value) !== '') {
                return $value;
            }

            return trim(implode(' ', array_filter([trim((string) ($attributes['last_name'] ?? '')), trim((string) ($attributes['first_name'] ?? ''))])));
        });
    }

    public function nextOfKin(): HasOne
    {
        return $this->hasOne(MemberNextOfKin::class, 'member_id');
    }

    public function spouse(): BelongsTo
    {
        return $this->belongsTo(self::class, 'spouse_member_id');
    }

    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    public function father(): BelongsTo
    {
        return $this->belongsTo(self::class, 'father_member_id');
    }

    public function mother(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mother_member_id');
    }

    public function serviceRecords(): HasMany
    {
        return $this->hasMany(MemberServiceRecord::class, 'member_id');
    }

    public function sacraments(): HasMany
    {
        return $this->hasMany(MemberSacrament::class, 'member_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(MemberGroup::class, 'member_group_memberships', 'member_id', 'member_group_id');
    }

    /** The Children Service / Junior Youth members who list this member as a guardian. */
    public function youngChildren(): HasMany
    {
        return $this->hasMany(YoungMemberGuardian::class, 'member_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'photo_id');
    }

    /** Path of the photo file on disk, or null when the member has none or the file has not been copied over. */
    public function photoPath(): ?string
    {
        $filename = $this->photo?->filename;

        if (! $filename) {
            return null;
        }

        $path = rtrim(config('church.member_photos'), '/\\').DIRECTORY_SEPARATOR.basename($filename);

        return is_file($path) ? $path : null;
    }

    public function photoUrl(): ?string
    {
        return $this->photoPath() ? route('members.photo', $this) : null;
    }

    /** Next PCG/ECM/2026/002737 style number for the main register. */
    public static function nextMemberNumber(): string
    {
        $last = (int) static::query()
            ->selectRaw("max(cast(substring_index(member_number, '/', -1) as unsigned)) as n")
            ->value('n');

        return sprintf('PCG/ECM/%d/%06d', now()->year, $last + 1);
    }

    /** Every number on file, without repeats: mobile first, then the other lines. @return list<string> */
    public function phoneNumbers(): array
    {
        return array_values(array_unique(array_filter([$this->mobile, $this->telephone, $this->office_phone])));
    }
}
