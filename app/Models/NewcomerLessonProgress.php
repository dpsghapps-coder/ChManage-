<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person's standing in one lesson of the class. */
class NewcomerLessonProgress extends Model
{
    protected $table = 'newcomer_lesson_progress';

    public const STATUSES = ['not_started' => 'Not started', 'in_progress' => 'In progress', 'completed' => 'Completed', 'skipped' => 'Skipped'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['completed_on' => 'date'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(NewcomerLesson::class, 'lesson_id');
    }
}
