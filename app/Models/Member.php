<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** The main (adult) register. Junior Youth and Children Service live in {@see YoungMember}. */
class Member extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joined_on' => 'date',
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
