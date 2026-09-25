<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person on the way to membership: Visitor → Newcomer → Catechumen → Member. `stage` says where they are;
 * `status` says whether they are still coming (active, on hold or inactive).
 */
class Newcomer extends Model
{
    public const STAGES = ['visitor' => 'Visitor', 'newcomer' => 'Newcomer', 'catechumen' => 'Catechumen', 'member' => 'Member'];

    public const STATUSES = ['active' => 'Active', 'on_hold' => 'On hold', 'inactive' => 'Inactive'];

    public const SEXES = ['male', 'female'];

    public const MARITAL_STATUSES = ['single', 'married', 'divorced', 'widowed'];

    public const MARRIAGE_TYPES = ['ordinance', 'customary'];

    public const BACKGROUNDS = ['christian' => 'Christian', 'non_christian' => 'Non-Christian', 'other' => 'Other'];

    public const INACTIVE_REASONS = ['Moved away', 'Lost contact', 'Joined another church', 'Not interested', 'Other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'first_visit_on' => 'date',
            'date_of_birth' => 'date',
            'made_member_on' => 'date',
            'is_baptized' => 'boolean',
            'is_confirmed' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** Path of the photo taken at registration, or null when there is none or the file is missing. */
    public function photoPath(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        $path = rtrim(config('church.newcomer_photos'), '/\\').DIRECTORY_SEPARATOR.basename($this->photo_path);

        return is_file($path) ? $path : null;
    }

    public function photoUrl(): ?string
    {
        return $this->photoPath() ? route('newcomers.photo', $this) : null;
    }

    public function counsellor(): BelongsTo
    {
        return $this->belongsTo(NewcomerCounsellor::class, 'counsellor_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function youngMember(): BelongsTo
    {
        return $this->belongsTo(YoungMember::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(NewcomerLessonProgress::class, 'newcomer_id');
    }

    /** Gives them a "not started" row for every lesson in use that they do not have yet, so a lesson added later reaches everyone in the class. */
    public function syncLessons(): void
    {
        $have = $this->progress()->pluck('lesson_id');

        NewcomerLesson::where('is_active', true)->whereNotIn('id', $have)->pluck('id')
            ->each(fn ($id) => NewcomerLessonProgress::create(['newcomer_id' => $this->id, 'lesson_id' => $id]));
    }

    public function visits(): HasMany
    {
        return $this->hasMany(NewcomerVisit::class)->orderByDesc('visited_on')->orderByDesc('id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(NewcomerStageChange::class, 'newcomer_id')->orderByDesc('changed_on')->orderByDesc('id');
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->surname])));
    }

    /** Under 18, so the form asks for a parent or guardian. */
    public function isMinor(): bool
    {
        return $this->date_of_birth !== null && $this->date_of_birth->age < 18;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '\\%_').'%';

        return $query->where(fn ($q) => $q->where('surname', 'like', $like)->orWhere('first_name', 'like', $like)
            ->orWhere('middle_name', 'like', $like)->orWhere('mobile', 'like', $like)->orWhere('whatsapp', 'like', $like));
    }

    /** Records a move between stages, or a change of status. */
    public function log(string $kind, ?string $from, string $to, ?string $note = null): void
    {
        NewcomerStageChange::create([
            'newcomer_id' => $this->id, 'kind' => $kind, 'from_value' => $from, 'to_value' => $to,
            'changed_on' => today(), 'note' => $note, 'changed_by' => auth()->id(),
        ]);
    }
}
