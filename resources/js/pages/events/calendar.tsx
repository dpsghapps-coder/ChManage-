import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import {
    EventBadges,
    EventsNav,
    eventTimes,
    hostLabel,
    hostTone,
} from '@/components/events-nav';
import type { EventRow } from '@/components/events-nav';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { calendar, create } from '@/routes/events';

type Props = {
    events: (EventRow & { kind: 'event' | 'meeting'; href: string })[];
    month: string;
    label: string;
    previous: string;
    next: string;
    thisMonth: string;
    from: string;
    to: string;
    today: string;
    hosts: Record<string, string>;
};

const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** Every date from `from` to `to`, as ISO strings. */
const datesBetween = (from: string, to: string) => {
    const dates: string[] = [];

    for (
        let d = new Date(`${from}T00:00:00`);
        d <= new Date(`${to}T00:00:00`);
        d.setDate(d.getDate() + 1)
    ) {
        dates.push(
            `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`,
        );
    }

    return dates;
};

export default function EventsCalendar({
    events,
    month,
    label,
    previous,
    next,
    thisMonth,
    from,
    to,
    today,
}: Props) {
    const { can } = usePermission();
    const days = datesBetween(from, to);
    const onDay = (date: string) =>
        events.filter((e) => e.starts_on <= date && e.ends_on >= date);
    const inMonth = (date: string) => date.startsWith(month);
    const agenda = days.filter((d) => inMonth(d) && onDay(d).length > 0);

    return (
        <>
            <Head title="Calendar" />

            <div className="max-w-6xl space-y-5 p-4">
                <PageHeader
                    title="Calendar"
                    description="Services, meetings and programmes, by the day."
                    actions={
                        can('events.manage') && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> Add Event
                                </Link>
                            </Button>
                        )
                    }
                />

                <EventsNav />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            asChild
                            aria-label="Previous month"
                        >
                            <Link
                                href={calendar({ query: { month: previous } })}
                                preserveScroll
                            >
                                <ChevronLeft />
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            asChild
                            aria-label="Next month"
                        >
                            <Link
                                href={calendar({ query: { month: next } })}
                                preserveScroll
                            >
                                <ChevronRight />
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={month === thisMonth}
                        >
                            <Link href={calendar()} preserveScroll>
                                Today
                            </Link>
                        </Button>
                    </div>
                    <h2 className="text-xl font-semibold tracking-tight">
                        {label}
                    </h2>
                    <ul
                        className="flex flex-wrap gap-2 text-xs"
                        aria-label="Key"
                    >
                        {(
                            Object.keys(hostLabel) as EventRow['host_type'][]
                        ).map((key) => (
                            <li
                                key={key}
                                className={cn(
                                    'rounded px-2 py-0.5',
                                    hostTone[key],
                                )}
                            >
                                {hostLabel[key]}
                            </li>
                        ))}
                        <li className="rounded border border-dashed px-2 py-0.5">
                            External
                        </li>
                    </ul>
                </div>

                {/* Desktop: the month as a grid of days. */}
                <div className="hidden overflow-hidden rounded-lg border md:block">
                    <div className="grid grid-cols-7 border-b bg-muted/40 text-xs font-medium text-muted-foreground">
                        {weekdays.map((d) => (
                            <div key={d} className="px-2 py-1.5">
                                {d}
                            </div>
                        ))}
                    </div>
                    <div className="grid grid-cols-7">
                        {days.map((date) => {
                            const list = onDay(date);

                            return (
                                <div
                                    key={date}
                                    className={cn(
                                        'min-h-28 space-y-1 border-r border-b p-1.5 [&:nth-child(7n)]:border-r-0',
                                        !inMonth(date) &&
                                            'bg-muted/30 text-muted-foreground',
                                    )}
                                >
                                    <div className="flex items-center justify-between">
                                        <span
                                            className={cn(
                                                'flex size-6 items-center justify-center rounded-full text-xs tabular-nums',
                                                date === today &&
                                                    'bg-primary font-semibold text-primary-foreground',
                                            )}
                                        >
                                            {Number(date.slice(8))}
                                        </span>
                                        {can('events.manage') && (
                                            <Link
                                                href={create({
                                                    query: { date },
                                                })}
                                                aria-label={`Add an event on ${date}`}
                                                className="rounded p-0.5 text-muted-foreground opacity-0 hover:bg-accent focus:opacity-100 [div:hover>&]:opacity-100"
                                            >
                                                <Plus className="size-3.5" />
                                            </Link>
                                        )}
                                    </div>
                                    {list.map((e) => (
                                        <Link
                                            key={`${e.kind}-${e.id}`}
                                            href={e.href}
                                            title={`${e.title} · ${e.host}${e.venue ? ` · ${e.venue}` : ''}`}
                                            className={cn(
                                                'block truncate rounded px-1.5 py-0.5 text-xs',
                                                hostTone[e.host_type],
                                                e.scope === 'external' &&
                                                    'border border-dashed border-current',
                                                e.status === 'cancelled' &&
                                                    'line-through opacity-60',
                                            )}
                                        >
                                            {e.starts_at &&
                                            !e.is_all_day &&
                                            e.starts_on === date
                                                ? `${e.starts_at} `
                                                : ''}
                                            {e.title}
                                        </Link>
                                    ))}
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Phone: only the days that have something on. */}
                <div className="space-y-4 md:hidden">
                    {agenda.length === 0 ? (
                        <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                            Nothing on in {label}.
                        </p>
                    ) : (
                        agenda.map((date) => (
                            <section key={date} className="space-y-2">
                                <h3
                                    className={cn(
                                        'text-sm font-medium',
                                        date === today && 'text-primary',
                                    )}
                                >
                                    {new Date(
                                        `${date}T00:00:00`,
                                    ).toLocaleDateString('en-GB', {
                                        weekday: 'long',
                                        day: 'numeric',
                                        month: 'long',
                                    })}
                                    {date === today && ' · Today'}
                                </h3>
                                <ul className="space-y-2">
                                    {onDay(date).map((e) => (
                                        <li key={`${e.kind}-${e.id}`}>
                                            <Link
                                                href={e.href}
                                                className={cn(
                                                    'block space-y-1 rounded-lg border border-l-4 p-3',
                                                    hostTone[e.host_type],
                                                    e.status === 'cancelled' &&
                                                        'opacity-60',
                                                )}
                                            >
                                                <p
                                                    className={cn(
                                                        'font-medium',
                                                        e.status ===
                                                            'cancelled' &&
                                                            'line-through',
                                                    )}
                                                >
                                                    {e.title}
                                                </p>
                                                <p className="text-xs opacity-80">
                                                    {[
                                                        eventTimes(e),
                                                        e.venue,
                                                        e.host,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                                <EventBadges event={e} />
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))
                    )}
                </div>
            </div>
        </>
    );
}

EventsCalendar.layout = {
    breadcrumbs: [{ title: 'Calendar', href: calendar() }],
};
