<?php

namespace App\Support;

use App\Models\ChurchSetting;
use App\Models\Committee;
use App\Models\CommitteeMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** The rules and warnings around committee terms. */
class CommitteeTerms
{
    /** How long after a term ends it is still reported as needing a decision (renew, or let it go). */
    public const RECENTLY_ENDED_DAYS = 30;

    /**
     * Terms due to end within the warning period, or ended recently, that have not been renewed.
     * Ordered by the date they end (or ended), soonest first.
     *
     * @return Collection<int, CommitteeMember>
     */
    public static function endingSoon(?int $committeeId = null)
    {
        $days = ChurchSetting::termWarningDays();

        return CommitteeMember::query()->with(['committee:id,name', 'member:id,member_number,full_name'])
            ->when($committeeId, fn ($q) => $q->where('committee_id', $committeeId))
            ->whereNotNull('ends_on')
            ->whereBetween('ends_on', [today()->subDays(self::RECENTLY_ENDED_DAYS), today()->addDays($days)])
            ->whereDoesntHave('successor')
            ->orderBy('ends_on')->orderBy('id')
            ->get();
    }

    /** How many terms the person has held on that committee, counting this one. Only members can be counted. */
    public static function termNumber(CommitteeMember $membership): int
    {
        if (! $membership->member_id) {
            return 1;
        }

        return CommitteeMember::where('committee_id', $membership->committee_id)->where('member_id', $membership->member_id)
            ->where(fn ($q) => $q->where('started_on', '<', $membership->started_on)->orWhere(fn ($q) => $q->where('started_on', $membership->started_on)->where('id', '<=', $membership->id)))
            ->count();
    }

    /** Past the committee's maximum number of terms? Always false when the committee sets no maximum. */
    public static function overLimit(Committee $committee, CommitteeMember $membership): bool
    {
        return $committee->max_terms !== null && self::termNumber($membership) > $committee->max_terms;
    }

    /** The end of a term that starts on `$start`, from the committee's term length; null when it sets none. */
    public static function endFor(Committee $committee, string $start): ?string
    {
        return $committee->term_years
            ? Carbon::parse($start)->addYears($committee->term_years)->toDateString()
            : null;
    }
}
