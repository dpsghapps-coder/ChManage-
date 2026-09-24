<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A position offered for a service record of one type (executive, session or committee). */
class ServicePosition extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** Positions by service-record type, in their set order. @return array<string, list<string>> */
    public static function byType(): array
    {
        $grouped = static::orderBy('sort_order')->orderBy('name')->get()->groupBy('type');

        return collect(MemberServiceRecord::TYPES)->mapWithKeys(fn ($label, $type) => [
            $type => $grouped->get($type, collect())->pluck('name')->values()->all(),
        ])->all();
    }
}
