<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Repeat" on a new event or meeting: it is created once for each date, weekly, fortnightly or monthly up to an end
 * date. Each one is an ordinary record afterwards, so any single date can be changed, cancelled or deleted alone.
 */
class Recurrence
{
    public const REPEATS = ['none' => 'Does not repeat', 'weekly' => 'Every week', 'fortnightly' => 'Every two weeks', 'monthly' => 'Every month'];

    /** Most dates one save may create, so a mistyped end date cannot flood the calendar. */
    public const MAX = 60;

    /**
     * The dates to create from the form: just the start when it does not repeat.
     *
     * @return list<string>
     */
    public static function datesFrom(Request $request, string $startField): array
    {
        $repeat = (string) ($request->input('repeat') ?: 'none');

        $data = $request->validate([
            'repeat' => ['nullable', Rule::in(array_keys(self::REPEATS))],
            'repeat_until' => [Rule::requiredIf($repeat !== 'none'), 'nullable', 'date', 'after:'.$startField],
        ], ['repeat_until.required' => 'Say when it stops repeating.', 'repeat_until.after' => 'It has to stop after it starts.']);

        $start = (string) $request->input($startField);

        return $repeat === 'none' ? [$start] : self::dates($start, $repeat, (string) $data['repeat_until']);
    }

    /** @return list<string> */
    public static function dates(string $start, string $repeat, string $until): array
    {
        $first = Carbon::parse($start)->startOfDay();
        $last = Carbon::parse($until)->startOfDay();
        $dates = [$first->toDateString()];

        for ($n = 1; ; $n++) {
            $next = match ($repeat) {
                'weekly' => $first->copy()->addWeeks($n),
                'fortnightly' => $first->copy()->addWeeks(2 * $n),
                default => $first->copy()->addMonthsNoOverflow($n),
            };

            if ($next->gt($last)) {
                return $dates;
            }

            if (count($dates) >= self::MAX) {
                throw ValidationException::withMessages(['repeat_until' => 'That would make more than '.self::MAX.' dates. Choose an earlier end date.']);
            }

            $dates[] = $next->toDateString();
        }
    }
}
