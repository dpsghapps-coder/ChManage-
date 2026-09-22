<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YoungMemberGuardian extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function youngMember(): BelongsTo
    {
        return $this->belongsTo(YoungMember::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** A linked member's current name, otherwise the name recorded at registration. */
    public function displayName(): string
    {
        return $this->member?->full_name ?? $this->name;
    }

    /** Every number to reach the guardian on: a linked member's current numbers, else the one recorded. @return list<string> */
    public function displayPhones(): array
    {
        $numbers = $this->member?->phoneNumbers() ?? [];

        return $numbers ?: array_values(array_filter([$this->phone]));
    }

    public function relationshipLabel(): string
    {
        if ($this->relationship === 'other' && $this->relationship_other) {
            return $this->relationship_other;
        }

        return YoungMember::RELATIONSHIPS[$this->relationship] ?? ucfirst($this->relationship);
    }

    /** For the lists and profile pages. Load `member.photo` first. @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'relationship' => $this->relationshipLabel(),
            'name' => $this->displayName(),
            'phones' => $this->displayPhones(),
            'photo_url' => $this->member?->photoUrl(),
            'member_number' => $this->member?->member_number,
            'is_primary' => $this->is_primary,
        ];
    }
}
