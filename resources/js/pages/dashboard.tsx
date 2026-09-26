import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { ActionBadge, deadlineText } from '@/components/meetings-nav';
import type { ActionRow } from '@/components/meetings-nav';
import { StageBadge } from '@/components/newcomers-nav';
import { termTiming } from '@/lib/committee-terms';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as actionsIndex } from '@/routes/actions';
import {
    overview as committeesOverview,
    show as showCommittee,
} from '@/routes/committees';
import { calendar, show as showEvent } from '@/routes/events';
import { index as meetingsIndex, show as showMeeting } from '@/routes/meetings';
import { index as requestsIndex, show as showRequest } from '@/routes/requests';
import {
    overview as newcomersOverview,
    show as showNewcomer,
} from '@/routes/newcomers';

type EventItem = {
    id: number;
    title: string;
    host: string;
    venue: string | null;
    starts_on: string;
    ends_on: string;
    is_all_day: boolean;
    starts_at: string | null;
};

type MeetingItem = {
    id: number;
    heading: string;
    meeting_date: string;
    starts_at: string | null;
    venue: string | null;
};

type ActionItem = ActionRow & { meeting_id: number; committee: string };

type Actions = { overdue: number; open: number; list: ActionItem[] };

type Cards = {
    requests?: {
        total: number;
        list: {
            id: number;
            type_label: string;
            member: string;
            status: string;
            made_on: string;
        }[];
    };
    events?: EventItem[];
    meetings?: MeetingItem[];
    actions?: Actions;
    my_actions?: Actions;
    terms?: {
        total: number;
        people: {
            id: number;
            committee_id: number;
            committee: string;
            name: string;
            position: string | null;
            ends_on: string;
            days_left: number;
        }[];
    };
    newcomers?: {
        follow_up: {
            days: number;
            total: number;
            people: {
                id: number;
                name: string;
                stage: string;
                days_quiet: number;
            }[];
        };
        waiting: number;
        ready: number;
    };
};

const shortDate = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });

const eventWhen = (e: EventItem) =>
    [
        e.starts_on === e.ends_on
            ? shortDate(e.starts_on)
            : `${shortDate(e.starts_on)} – ${shortDate(e.ends_on)}`,
        e.is_all_day ? 'All day' : e.starts_at,
    ]
        .filter(Boolean)
        .join(' · ');

export default function Dashboard({
    cards,
    actionsDays,
}: {
    cards: Cards;
    actionsDays: number;
}) {
    const { auth } = usePage().props;
    const hour = new Date().getHours();
    const greeting =
        hour < 12
            ? 'Good morning'
            : hour < 17
              ? 'Good afternoon'
              : 'Good evening';
    const shown = Object.keys(cards).length;

    return (
        <>
            <Head title="Dashboard" />

            <div className="max-w-6xl space-y-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {greeting}
                        {auth.user?.first_name
                            ? `, ${auth.user.first_name}`
                            : ''}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {new Date().toLocaleDateString('en-GB', {
                            weekday: 'long',
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                        })}
                    </p>
                </div>

                {shown === 0 && (
                    <p className="rounded-lg border py-12 text-center text-sm text-muted-foreground">
                        Nothing to show here yet. Your role gives you no
                        dashboard information; use the menu to open the parts of
                        the system you work in.
                    </p>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    {cards.requests && (
                        <Card
                            title="Member requests"
                            count={cards.requests.total}
                            tone={cards.requests.total > 0 ? 'warn' : undefined}
                            note="Waiting for you, oldest first."
                            href={requestsIndex()}
                            more="Open the inbox"
                        >
                            {cards.requests.list.length === 0 ? (
                                <Empty>Nothing is waiting.</Empty>
                            ) : (
                                <ul className="divide-y">
                                    {cards.requests.list.map((r) => (
                                        <li key={r.id}>
                                            <Link
                                                href={showRequest(r.id)}
                                                className="flex items-center justify-between gap-2 py-2 hover:bg-accent/50"
                                            >
                                                <span>
                                                    <span className="block text-sm font-medium">
                                                        {r.member}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {r.type_label} ·{' '}
                                                        {r.made_on}
                                                    </span>
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {r.status === 'in_review'
                                                        ? 'In review'
                                                        : 'New'}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    )}

                    {cards.my_actions && (
                        <Card
                            title="My actions"
                            count={cards.my_actions.open}
                            tone={
                                cards.my_actions.overdue > 0
                                    ? 'alert'
                                    : undefined
                            }
                            note={
                                cards.my_actions.overdue > 0
                                    ? `${cards.my_actions.overdue} overdue`
                                    : 'None overdue'
                            }
                            href={actionsIndex()}
                            more="All actions"
                        >
                            <ActionList
                                actions={cards.my_actions.list}
                                empty="You have no open actions."
                            />
                        </Card>
                    )}

                    {cards.actions && (
                        <Card
                            title="Actions needing attention"
                            count={cards.actions.overdue}
                            tone={
                                cards.actions.overdue > 0 ? 'alert' : undefined
                            }
                            note={`Overdue, or due within ${actionsDays} days. ${cards.actions.open} open in all.`}
                            href={actionsIndex({ query: { view: 'overdue' } })}
                            more="Overdue actions"
                        >
                            <ActionList
                                actions={cards.actions.list}
                                empty="Nothing is overdue or due soon."
                                showPerson
                            />
                        </Card>
                    )}

                    {cards.events && (
                        <Card
                            title="Upcoming events"
                            count={cards.events.length}
                            href={calendar()}
                            more="Open the calendar"
                        >
                            {cards.events.length === 0 ? (
                                <Empty>No events are coming up.</Empty>
                            ) : (
                                <ul className="divide-y">
                                    {cards.events.map((e) => (
                                        <li key={e.id}>
                                            <Link
                                                href={showEvent(e.id)}
                                                className="block py-2 hover:bg-accent/50"
                                            >
                                                <span className="block text-sm font-medium">
                                                    {e.title}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {[
                                                        eventWhen(e),
                                                        e.venue,
                                                        e.host,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    )}

                    {cards.meetings && (
                        <Card
                            title="Upcoming meetings"
                            count={cards.meetings.length}
                            href={meetingsIndex({
                                query: { when: 'upcoming' },
                            })}
                            more="All meetings"
                        >
                            {cards.meetings.length === 0 ? (
                                <Empty>No meetings are scheduled.</Empty>
                            ) : (
                                <ul className="divide-y">
                                    {cards.meetings.map((m) => (
                                        <li key={m.id}>
                                            <Link
                                                href={showMeeting(m.id)}
                                                className="block py-2 hover:bg-accent/50"
                                            >
                                                <span className="block text-sm font-medium">
                                                    {m.heading}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {[
                                                        shortDate(
                                                            m.meeting_date,
                                                        ),
                                                        m.starts_at,
                                                        m.venue,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    )}

                    {cards.terms && (
                        <Card
                            title="Committee terms ending"
                            count={cards.terms.total}
                            tone={cards.terms.total > 0 ? 'warn' : undefined}
                            note="Ending soon, or ended in the last month and not renewed."
                            href={committeesOverview()}
                            more="Committees"
                        >
                            {cards.terms.people.length === 0 ? (
                                <Empty>No terms are about to end.</Empty>
                            ) : (
                                <ul className="divide-y">
                                    {cards.terms.people.map((t) => (
                                        <li
                                            key={t.id}
                                            className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                                        >
                                            <span>
                                                <span className="font-medium">
                                                    {t.name}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    ·{' '}
                                                </span>
                                                <Link
                                                    href={showCommittee(
                                                        t.committee_id,
                                                    )}
                                                    className="text-muted-foreground underline"
                                                >
                                                    {t.committee}
                                                </Link>
                                            </span>
                                            <span
                                                className={cn(
                                                    'text-xs font-medium',
                                                    t.days_left < 0
                                                        ? 'text-red-600'
                                                        : 'text-amber-600',
                                                )}
                                            >
                                                {termTiming(t.days_left)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    )}

                    {cards.newcomers && (
                        <Card
                            title="Newcomers"
                            count={cards.newcomers.follow_up.total}
                            tone={
                                cards.newcomers.follow_up.total > 0
                                    ? 'warn'
                                    : undefined
                            }
                            note={`Quiet for ${cards.newcomers.follow_up.days} days or more. ${cards.newcomers.waiting} waiting for a counsellor, ${cards.newcomers.ready} ready to become members.`}
                            href={newcomersOverview()}
                            more="Newcomers overview"
                        >
                            {cards.newcomers.follow_up.people.length === 0 ? (
                                <Empty>No one has gone quiet.</Empty>
                            ) : (
                                <ul className="divide-y">
                                    {cards.newcomers.follow_up.people.map(
                                        (p) => (
                                            <li key={p.id}>
                                                <Link
                                                    href={showNewcomer(p.id)}
                                                    className="flex items-center justify-between gap-2 py-2 hover:bg-accent/50"
                                                >
                                                    <span>
                                                        <span className="block text-sm font-medium">
                                                            {p.name}
                                                        </span>
                                                        <span className="block text-xs text-muted-foreground">
                                                            Quiet for{' '}
                                                            {p.days_quiet} days
                                                        </span>
                                                    </span>
                                                    <StageBadge
                                                        stage={p.stage}
                                                    />
                                                </Link>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

function Card({
    title,
    count,
    note,
    tone,
    href,
    more,
    children,
}: {
    title: string;
    count: number;
    note?: string;
    tone?: 'alert' | 'warn';
    href: ReturnType<typeof dashboard>;
    more: string;
    children: ReactNode;
}) {
    return (
        <section
            className={cn(
                'flex flex-col gap-3 rounded-lg border p-5',
                tone === 'alert' && 'border-red-500/50 bg-red-500/5',
                tone === 'warn' && 'border-amber-500/50 bg-amber-500/5',
            )}
        >
            <div>
                <h2 className="flex items-center justify-between font-medium">
                    {title}
                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs tabular-nums">
                        {count}
                    </span>
                </h2>
                {note && (
                    <p className="text-xs text-muted-foreground">{note}</p>
                )}
            </div>
            <div className="flex-1">{children}</div>
            <Link
                href={href}
                className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                {more} <ArrowRight className="size-3.5" />
            </Link>
        </section>
    );
}

function Empty({ children }: { children: ReactNode }) {
    return (
        <p className="py-4 text-center text-sm text-muted-foreground">
            {children}
        </p>
    );
}

function ActionList({
    actions,
    empty,
    showPerson,
}: {
    actions: ActionItem[];
    empty: string;
    showPerson?: boolean;
}) {
    if (actions.length === 0) {
        return <Empty>{empty}</Empty>;
    }

    return (
        <ul className="divide-y">
            {actions.map((a) => (
                <li key={a.id}>
                    <Link
                        href={showMeeting(a.meeting_id)}
                        className="block space-y-0.5 py-2 hover:bg-accent/50"
                    >
                        <span className="flex items-start justify-between gap-2">
                            <span className="text-sm font-medium">
                                {a.description}
                            </span>
                            <ActionBadge status={a.shown} />
                        </span>
                        <span
                            className={cn(
                                'block text-xs',
                                a.shown === 'overdue'
                                    ? 'font-medium text-red-600'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {[
                                showPerson
                                    ? (a.responsible ?? 'No one assigned')
                                    : a.committee,
                                deadlineText(a),
                            ].join(' · ')}
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
