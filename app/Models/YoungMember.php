<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Children Service (ages 0-11) and Junior Youth (ages 12-17), registered with the simplified form. */
class YoungMember extends Model
{
    public const CHILDREN_UNDER = 12;

    /** New registrations must be younger than this; from 18 a person belongs on the main register. */
    public const REGISTER_UNDER = 18;

    /** Department codes used in membership numbers. */
    public const DEPARTMENTS = ['CS' => 'Children Service', 'JY' => 'Junior Youth'];

    /** Kinds of guardian a child can have. */
    public const RELATIONSHIPS = [
        'mother' => 'Mother',
        'father' => 'Father',
        'aunt' => 'Aunt',
        'uncle' => 'Uncle',
        'grandmother' => 'Grandmother',
        'grandfather' => 'Grandfather',
        'sibling' => 'Sibling',
        'other' => 'Other',
    ];

    /**
     * Classes by age: [first age, last age, name]. Children Service stops at 11 and Junior Youth starts at 12,
     * so age 12 is always Junior Class.
     */
    private const CLASSES = [
        [0, 5, 'CS Class 1'],
        [6, 8, 'CS Class 2'],
        [9, 11, 'CS Class 3'],
        [12, 13, 'JY Junior Class'],
        [14, 15, 'JY Intermediate Class'],
        [16, 17, 'JY Senior Class'],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joined_on' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** The main-register record this child was copied from, if any. */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** The photo taken at registration, else the photo of the main-register record this was copied from. */
    public function photoPath(): ?string
    {
        if ($this->photo_path) {
            $own = rtrim(config('church.young_member_photos'), '/\\').DIRECTORY_SEPARATOR.basename($this->photo_path);

            if (is_file($own)) {
                return $own;
            }
        }

        return $this->member?->photoPath();
    }

    public function photoUrl(): ?string
    {
        return $this->photoPath() ? route('members.young.photo', $this) : null;
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(YoungMemberGuardian::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function fullName(): string
    {
        return collect([$this->last_name, $this->first_name, $this->other_names])->filter()->implode(' ');
    }

    /** The class this child belongs to, from their age today. */
    public function ageClass(): ?string
    {
        if (! $this->date_of_birth) {
            return null;
        }

        $age = $this->date_of_birth->age;

        foreach (self::CLASSES as [$from, $to, $name]) {
            if ($age >= $from && $age <= $to) {
                return "{$name} (Ages {$from}–{$to})";
            }
        }

        return null;
    }

    /** CS for Children Service, JY for Junior Youth, from the date of birth. */
    public static function departmentFor(mixed $dateOfBirth): string
    {
        return substr((string) $dateOfBirth, 0, 10) > today()->subYears(self::CHILDREN_UNDER)->toDateString() ? 'CS' : 'JY';
    }

    /**
     * PCG/ECM/2026/CS/000001. Only the year and sequence are stored: the department (CS under 12, JY from 12) follows
     * the child's age, so the number switches to JY by itself while the rest stays the same.
     */
    protected function memberNumber(): Attribute
    {
        return Attribute::get(fn () => sprintf('PCG/ECM/%d/%s/%06d', $this->number_year, self::departmentFor($this->date_of_birth), $this->number_seq));
    }

    /** The same number as SQL, for searching. The cut-off date is worked out here, so nothing user-supplied goes in. */
    public static function memberNumberSql(): string
    {
        $cutoff = today()->subYears(self::CHILDREN_UNDER)->toDateString();

        return "concat('PCG/ECM/', number_year, '/', if(date_of_birth > '{$cutoff}', 'CS', 'JY'), '/', lpad(number_seq, 6, '0'))";
    }

    /** The next number in the one running sequence shared by Children Service and Junior Youth. */
    public static function nextSequence(): int
    {
        return (int) static::query()->max('number_seq') + 1;
    }
}
