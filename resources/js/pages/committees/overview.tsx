import { Head, Link } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { termTiming } from '@/lib/committee-terms';
import { cn } from '@/lib/utils';
import { index as manageList } from '@/routes/admin/committees';
import { overview, show } from '@/routes/committees';
import { show as showMember } from '@/routes/members';

type Committee = {
    id: number;
    name: string;
    term_years: number | null;
    max_terms: number | null;
    members: number;
    ending: number;
};

type Ending = {
    id: number;
    committee_id: number;
    committee: string;
    member_id: number | null;
    name: string;
    position: string | null;
    ends_on: string;
    days_left: number;
};

type Props = {
    committees: Committee[];
    ending: Ending[];
    endingTotal: number;
    warnDays: number;
};

const day = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

const rules = (c: Committee) =>
    c.term_years || c.max_terms
        ? [
              c.term_years ? `${c.term_years}-year terms` : null,
              c.max_terms ? `at most ${c.max_terms}` : null,
          ]
              .filter(Boolean)
              .join(', ')
        : 'No term rules set';

export default function CommitteesOverview({
    committees,
    ending,
    endingTotal,
    warnDays,
}: Props) {
    const { can } = usePermission();

    return (
        <>
            <Head title="Committees" />

            <div className="max-w-6xl space-y-6 p-4">
                <PageHeader
                    title="Committees"
                    description="Who serves on each committee, and for how long."
                    actions={
                        can('settings.manage') && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={manageList()}>
                                    <Settings2 /> Manage the list of committees
                                </Link>
                            </Button>
                        )
                    }
                />

                <section
                    aria-label="Terms ending soon"
                    className={cn(
                        'space-y-3 rounded-lg border p-5',
                        endingTotal > 0 && 'border-amber-500/50 bg-amber-500/5',
                    )}
                >
                    <div>
                        <h2 className="flex items-center justify-between font-medium">
                            Terms ending soon
                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs tabular-nums">
                                {endingTotal}
                            </span>
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Terms ending within {warnDays} days, or ended in the
                            last month, that have not been renewed.
                        </p>
                    </div>
                    {ending.length === 0 ? (
                        <p className="py-4 text-center text-sm text-muted-foreground">
                            No terms are about to end.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {ending.map((e) => (
                                <li
                                    key={e.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                                >
                                    <span>
                                        {e.member_id ? (
                                            <Link
                                                href={showMember(e.member_id)}
                                                className="font-medium hover:underline"
                                            >
                                                {e.name}
                                            </Link>
                                        ) : (
                                            <span className="font-medium">
                                                {e.name}
                                            </span>
                                        )}
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {e.position ?? 'Member'} ·{' '}
                                        </span>
                                        <Link
                                            href={show(e.committee_id)}
                                            className="underline"
                                        >
                                            {e.committee}
                                        </Link>
                                    </span>
                                    <span
                                        className={cn(
                                            'text-xs font-medium',
                                            e.days_left < 0
                                                ? 'text-red-600'
                                                : 'text-amber-600',
                                        )}
                                    >
                                        {termTiming(e.days_left)} (
                                        {day(e.ends_on)})
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {endingTotal > ending.length && (
                        <p className="text-xs text-muted-foreground">
                            And {endingTotal - ending.length} more.
                        </p>
                    )}
                </section>

                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {committees.map((c) => (
                        <li key={c.id}>
                            <Link
                                href={show(c.id)}
                                className="block h-full space-y-1.5 rounded-lg border p-4 transition-colors hover:border-primary/60"
                            >
                                <p className="font-medium">{c.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    <span className="tabular-nums">
                                        {c.members}
                                    </span>{' '}
                                    serving now
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {rules(c)}
                                </p>
                                {c.ending > 0 && (
                                    <p className="text-xs font-medium text-amber-600">
                                        {c.ending} term
                                        {c.ending === 1 ? '' : 's'} ending
                                    </p>
                                )}
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

CommitteesOverview.layout = {
    breadcrumbs: [{ title: 'Committees', href: overview() }],
};
