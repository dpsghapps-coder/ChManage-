<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A lesson of the newcomers' class, in teaching order. */
class NewcomerLesson extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
