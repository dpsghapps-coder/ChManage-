import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    EventBadges,
    EventsNav,
    eventDates,
    eventTimes,
    hostLabel,
    hostTone,
} from '@/components/events-nav';
import type { EventRow } from '@/components/events-nav';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/events';

type Filters = {
    q: string;
    when: 'upcoming' | 'past' | 'all';
    host: string | null;
    scope: string | null;
    visibility: string | null;
    venue: string | null;
};

type Props = {
    events: Paginated<EventRow>;
    filters: Filters;
    hosts: Record<string, string>;
    scopes: Record<string, string>;
    visibilities: Record<string, string>;
    venues: string[];
};

export default function EventsIndex({
    events,
    filters,
    hosts,
    scopes,
    visibilities,
    venues,
}: Props) {
    const { can } = usePermission();
    const [term, setTerm] = useState(filters.q);
    const first = useRef(true);

    const visit = (changes: Partial<Filters>) => {
        const next = { ...filters, q: term, ...changes };
        router.get(
            index().url,
            {
                q: next.q || undefined,
                when: next.when !== 'upcoming' ? next.when : undefined,
                host: next.host || undefined,
                scope: next.scope || undefined,
                visibility: next.visibility || undefined,
                venue: next.venue || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Search as they type, after a short pause.
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => visit({ q: term }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [term]);

    const select = (
        label: string,
        key: 'host' | 'scope' | 'visibility' | 'venue',
        anyLabel: string,
        options: [string, string][],
    ) => (
        <NativeSelect
            aria-label={label}
            className="w-auto"
            value={filters[key] ?? ''}
            onChange={(e) => visit({ [key]: e.target.value || null })}
        >
            <option value="">{anyLabel}</option>
            {options.map(([value, text]) => (
                <option key={value} value={value}>
                    {text}
                </option>
            ))}
        </NativeSelect>
    );

    return (
        <>
            <Head title="Events" />

            <div className="max-w-6xl space-y-5 p-4">
                <PageHeader
                    title="Events"
                    description="Everything the church holds: services, meetings, programmes and outings."
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

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-56 flex-1 sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Search title, purpose or venue"
                            className="pl-9"
                            aria-label="Search"
                        />
                    </div>
                    <NativeSelect
                        aria-label="When"
                        className="w-auto"
                        value={filters.when}
                        onChange={(e) =>
                            visit({ when: e.target.value as Filters['when'] })
                        }
                    >
                        <option value="upcoming">Upcoming</option>
                        <option value="past">Past</option>
                        <option value="all">All</option>
                    </NativeSelect>
                    {select('Host', 'host', 'Any host', Object.entries(hosts))}
                    {select(
                        'Internal or external',
                        'scope',
                        'Internal or external',
                        Object.entries(scopes),
                    )}
                    {select(
                        'Public or private',
                        'visibility',
                        'Public or private',
                        Object.entries(visibilities),
                    )}
                    {select(
                        'Venue',
                        'venue',
                        'Any venue',
                        venues.map((v) => [v, v]),
                    )}
                </div>

                {events.data.length === 0 ? (
                    <p className="rounded-lg border py-12 text-center text-sm text-muted-foreground">
                        No events match.{' '}
                        {can('events.manage') && 'Add one to begin.'}
                    </p>
                ) : (
                    <>
                        {/* Phone: cards. */}
                        <ul className="space-y-2 md:hidden">
                            {events.data.map((e) => (
                                <li key={e.id}>
                                    <Link
                                        href={show(e.id)}
                                        className="block space-y-1.5 rounded-lg border p-3 hover:border-primary/60"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <span
                                                className={cn(
                                                    'font-medium',
                                                    e.status === 'cancelled' &&
                                                        'line-through opacity-60',
                                                )}
                                            >
                                                {e.title}
                                            </span>
                                            <span
                                                className={cn(
                                                    'shrink-0 rounded px-2 py-0.5 text-xs',
                                                    hostTone[e.host_type],
                                                )}
                                            >
                                                {e.host_type === 'church'
                                                    ? hostLabel.church
                                                    : e.host}
                                            </span>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {[
                                                eventDates(e),
                                                eventTimes(e),
                                                e.venue,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                        <EventBadges event={e} />
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        {/* Desktop: table. */}
                        <div className="hidden rounded-lg border md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Event</TableHead>
                                        <TableHead>Host</TableHead>
                                        <TableHead>Venue</TableHead>
                                        <TableHead>Organizer</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {events.data.map((e) => (
                                        <TableRow key={e.id}>
                                            <TableCell className="whitespace-nowrap">
                                                {eventDates(e)}
                                                <span className="block text-xs text-muted-foreground">
                                                    {eventTimes(e)}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={show(e.id)}
                                                    className={cn(
                                                        'font-medium hover:underline',
                                                        e.status ===
                                                            'cancelled' &&
                                                            'line-through opacity-60',
                                                    )}
                                                >
                                                    {e.title}
                                                </Link>
                                                <EventBadges event={e} />
                                            </TableCell>
                                            <TableCell>
                                                <span
                                                    className={cn(
                                                        'rounded px-2 py-0.5 text-xs',
                                                        hostTone[e.host_type],
                                                    )}
                                                >
                                                    {e.host}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {e.venue ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {e.organizer ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination page={events} />
                    </>
                )}
            </div>
        </>
    );
}

EventsIndex.layout = {
    breadcrumbs: [{ title: 'Events', href: index() }],
};
