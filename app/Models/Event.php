<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something the church holds on a date: a service, a meeting, a programme, a parade ... */
class Event extends Model
{
    public const HOSTS = ['church' => 'Church', 'group' => 'Group', 'committee' => 'Committee'];

    public const SCOPES = ['internal' => 'Internal', 'external' => 'External'];

    public const VISIBILITIES = ['public' => 'Public', 'private' => 'Private'];

    public const STATUSES = ['scheduled' => 'Scheduled', 'cancelled' => 'Cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_all_day' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MemberGroup::class, 'member_group_id');
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'organizer_member_id');
    }

    /** Who hosts it, in words: "Church", "Church Choir" or "Committee on Finance". */
    public function hostName(): string
    {
        return match ($this->host_type) {
            'group' => $this->group?->name ?? 'Group',
            'committee' => $this->committee?->name ?? 'Committee',
            default => 'Church',
        };
    }

    public function organizerName(): ?string
    {
        return $this->organizer?->full_name ?? $this->organizer_name;
    }

    /** Events that fall on any day between the two dates, a multi-day event counting on each of its days. */
    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->where('starts_on', '<=', $to)->where('ends_on', '>=', $from);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '\\%_').'%';

        return $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('purpose', 'like', $like)->orWhere('venue', 'like', $like));
    }
}
