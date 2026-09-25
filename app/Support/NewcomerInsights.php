<?php

namespace App\Support;

use App\Models\ChurchSetting;
use App\Models\Newcomer;
use App\Models\NewcomerCounsellor;
use App\Models\NewcomerLesson;
use App\Models\NewcomerLessonProgress;
use App\Models\NewcomerStageChange;

/** The numbers behind the Newcomers Overview and Dashboard pages. */
class NewcomerInsights
{
    /** The pipeline people who are still coming: not inactive. Members are history. */
    private static function working()
    {
        return Newcomer::query()->where('status', '!=', 'inactive')->where('stage', '!=', 'member');
    }

    /** @return array<string, mixed> */
    public static function overview(): array
    {
        $days = ChurchSetting::followUpDays();

        $counts = Newcomer::query()->where('status', '!=', 'inactive')->selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage');

        // Last sign of life: the latest visit or completed lesson, or the first visit.
        $quiet = Newcomer::query()->where('status', 'active')->where('stage', '!=', 'member')
            ->with('counsellor.member:id,full_name')
            ->withMax('visits as last_visit', 'visited_on')
            ->withMax('progress as last_lesson', 'completed_on')
            ->get()
            ->map(function (Newcomer $n) {
                $last = collect([$n->first_visit_on->toDateString(), $n->last_visit, $n->last_lesson])->filter()->map(fn ($d) => substr((string) $d, 0, 10))->max();

                return [...self::person($n), 'last_activity' => $last, 'days_quiet' => (int) today()->diffInDays($last, true)];
            })
            ->filter(fn ($p) => $p['days_quiet'] >= $days)
            ->sortByDesc('days_quiet')->values();

        $ready = Newcomer::query()->where('status', 'active')->where('stage', 'catechumen')
            ->with('counsellor.member:id,full_name')
            ->withCount([
                'progress as lessons_done' => fn ($q) => $q->where('status', 'completed'),
                'progress as lessons_total' => fn ($q) => $q->where('status', '!=', 'skipped'),
            ])->get()
            ->filter(fn (Newcomer $n) => $n->lessons_total > 0 && $n->lessons_done === $n->lessons_total)
            ->map(fn (Newcomer $n) => self::person($n))->values();

        $waiting = Newcomer::query()->where('status', 'active')->where('stage', 'visitor')->orderBy('first_visit_on')->get()
            ->map(fn (Newcomer $n) => self::person($n));

        return [
            'counts' => collect(array_keys(Newcomer::STAGES))->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)]),
            'on_hold' => Newcomer::where('status', 'on_hold')->count(),
            'inactive' => Newcomer::where('status', 'inactive')->count(),
            'follow_up' => ['days' => $days, 'total' => $quiet->count(), 'people' => $quiet->take(10)->values()],
            'ready' => ['total' => $ready->count(), 'people' => $ready->take(10)->values()],
            'waiting' => ['total' => $waiting->count(), 'people' => $waiting->take(10)->values()],
        ];
    }

    /** @return array<string, mixed> */
    public static function dashboard(): array
    {
        $total = Newcomer::count();

        // Registrations by month for the last twelve months, empty months included.
        $registered = Newcomer::query()->where('first_visit_on', '>=', today()->startOfMonth()->subMonths(11))
            ->selectRaw("date_format(first_visit_on, '%Y-%m') as ym, count(*) as n")->groupBy('ym')->pluck('n', 'ym');
        $months = collect(range(11, 0))->map(function ($ago) use ($registered) {
            $month = today()->startOfMonth()->subMonths($ago);

            return ['month' => $month->format('Y-m'), 'label' => $month->format('M'), 'year' => $month->format('Y'), 'count' => (int) ($registered[$month->format('Y-m')] ?? 0)];
        })->values();

        $sources = Newcomer::query()->selectRaw("coalesce(nullif(heard_via, ''), 'Not stated') as label, count(*) as n")->groupBy('label')->orderByDesc('n')->orderBy('label')->get()
            ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->n])->values();

        // How many of everyone who registered ever got as far as each stage.
        $reached = NewcomerStageChange::where('kind', 'stage')->selectRaw('to_value, count(distinct newcomer_id) as n')->groupBy('to_value')->pluck('n', 'to_value');
        $funnel = collect(Newcomer::STAGES)->map(fn ($label, $stage) => [
            'stage' => $stage, 'label' => $label,
            'count' => $stage === 'visitor' ? $total : (int) ($reached[$stage] ?? 0),
        ])->values()->map(fn ($row) => [...$row, 'percent' => $total ? (int) round($row['count'] / $total * 100) : 0]);

        $became = Newcomer::query()->whereNotNull('made_member_on')->get(['first_visit_on', 'made_member_on']);
        $days = $became->map(fn (Newcomer $n) => (int) $n->first_visit_on->diffInDays($n->made_member_on, true));

        // Each lesson: how many of the people taking it have completed it.
        $lessons = NewcomerLesson::orderBy('sort_order')->orderBy('id')->get()->map(function (NewcomerLesson $lesson) {
            $rows = NewcomerLessonProgress::where('lesson_id', $lesson->id)->where('status', '!=', 'skipped');

            return ['title' => $lesson->title, 'completed' => (clone $rows)->where('status', 'completed')->count(), 'total' => $rows->count()];
        })->filter(fn ($l) => $l['total'] > 0)->values();

        $catechumens = self::working()->where('stage', 'catechumen')->withCount([
            'progress as done' => fn ($q) => $q->where('status', 'completed'),
            'progress as counted' => fn ($q) => $q->where('status', '!=', 'skipped'),
        ])->get()->filter(fn ($n) => $n->counted > 0);

        $load = self::working()->whereNotNull('counsellor_id')->selectRaw('counsellor_id, stage, count(*) as n')->groupBy('counsellor_id', 'stage')->get()->groupBy('counsellor_id');
        $counsellors = NewcomerCounsellor::with('member:id,full_name')->get()->map(fn (NewcomerCounsellor $c) => [
            'name' => $c->member->full_name,
            'is_active' => $c->is_active,
            'newcomer' => (int) ($load[$c->id]?->firstWhere('stage', 'newcomer')?->n ?? 0),
            'catechumen' => (int) ($load[$c->id]?->firstWhere('stage', 'catechumen')?->n ?? 0),
        ])->map(fn ($c) => [...$c, 'total' => $c['newcomer'] + $c['catechumen']])->sortByDesc('total')->values();

        return [
            'totals' => [
                'registered' => $total,
                'working' => self::working()->count(),
                'members' => Newcomer::where('stage', 'member')->count(),
                'inactive' => Newcomer::where('status', 'inactive')->count(),
            ],
            'months' => $months,
            'sources' => $sources,
            'funnel' => $funnel,
            'time_to_member' => ['count' => $days->count(), 'average_days' => $days->isEmpty() ? null : (int) round($days->avg())],
            'lessons' => $lessons,
            'class' => ['catechumens' => $catechumens->count(), 'average_percent' => $catechumens->isEmpty() ? null : (int) round($catechumens->avg(fn ($n) => $n->done / $n->counted * 100))],
            'counsellors' => $counsellors,
            'inactive_reasons' => Newcomer::where('status', 'inactive')->selectRaw("coalesce(nullif(inactive_reason, ''), 'Not stated') as label, count(*) as n")->groupBy('label')->orderByDesc('n')->get()
                ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->n])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private static function person(Newcomer $n): array
    {
        return [
            'id' => $n->id,
            'name' => $n->fullName(),
            'stage' => $n->stage,
            'first_visit_on' => $n->first_visit_on->toDateString(),
            'counsellor' => $n->counsellor?->member?->full_name,
            'photo_url' => $n->photoUrl(),
        ];
    }
}
