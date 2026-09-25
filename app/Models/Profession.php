<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An occupation or profession (the table predates the app), grouped by category on the member form. */
class Profession extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** How a profession with no category is shown. */
    public const UNCATEGORISED = 'Uncategorised';

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /** For a grouped dropdown: the categories A–Z with "Uncategorised" last. @return list<array{category: string, options: list<array{id: int, name: string}>}> */
    public static function grouped(): array
    {
        return static::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'category'])
            ->groupBy(fn (Profession $p) => $p->category ?: self::UNCATEGORISED)
            ->sortKeysUsing(fn ($a, $b) => ($a === self::UNCATEGORISED) <=> ($b === self::UNCATEGORISED) ?: strnatcasecmp($a, $b))
            ->map(fn ($items, $category) => [
                'category' => $category,
                'options' => $items->map(fn (Profession $p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            ])->values()->all();
    }
}
