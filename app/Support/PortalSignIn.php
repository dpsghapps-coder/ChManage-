<?php

namespace App\Support;

use App\Models\ChurchSetting;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Who a phone number and date of birth belong to, for the member portal. Numbers are recorded in mixed forms
 * (0244123456, 024 412 3456, +233244123456), so both sides are cut down to the local 10-digit form before they are
 * compared. Only active adult members can sign in.
 */
class PortalSignIn
{
    /** The local form of a Ghanaian number (0244123456), or null when it is not one. */
    public static function local(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 3);
        } elseif (strlen($digits) === 9) {
            $digits = '0'.$digits;
        }

        return preg_match('/^0[0-9]{9}$/', $digits) ? $digits : null;
    }

    /**
     * The members this number and date of birth match: usually one, more than one when relatives share both
     * (which the caller refuses rather than guessing), none when nothing matches.
     *
     * @return Collection<int, Member>
     */
    public static function matches(string $phone, string $dateOfBirth): Collection
    {
        $local = self::local($phone);

        try {
            $dob = Carbon::parse($dateOfBirth)->toDateString();
        } catch (Throwable) {
            $dob = null;
        }

        if (! $local || ! $dob) {
            return collect();
        }

        $column = ChurchSetting::portalPhoneField();
        $last9 = substr($local, 1);

        return Member::query()
            ->where('status', 'active')
            ->whereDate('date_of_birth', $dob)
            ->whereRaw("right(regexp_replace({$column}, '[^0-9]', ''), 9) = ?", [$last9])
            ->get()
            ->filter(fn (Member $m) => self::local($m->{$column}) === $local && $m->date_of_birth->age >= 18)
            ->values();
    }
}
