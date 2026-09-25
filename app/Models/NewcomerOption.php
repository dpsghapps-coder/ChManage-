<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One choice on an editable list of the newcomer form (title, service, how they heard of us ...). */
class NewcomerOption extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** The lists, by kind, with the wording shown in Settings. */
    public const KINDS = [
        'title' => 'Title',
        'current_status' => 'Current status',
        'purpose' => 'Purpose of the visit',
        'service' => 'Service',
        'source' => 'How they heard of the congregation',
        'former_church' => 'Former church',
    ];

    /** @return array<string, list<string>> every list's names, in order */
    public static function lists(): array
    {
        $all = static::orderBy('sort_order')->orderBy('name')->get()->groupBy('kind');

        return collect(array_keys(self::KINDS))->mapWithKeys(fn ($kind) => [$kind => $all->get($kind, collect())->pluck('name')->values()->all()])->all();
    }
}
