import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    MeetingStatusBadge,
    MeetingsNav,
    shortDate,
} from '@/components/meetings-nav';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
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
import { create, index, show } from '@/routes/meetings';

type Row = {
    id: number;
    heading: string;
    committee: string;
    meeting_date: string;
    starts_at: string | null;
    venue: string | null;
    status: string;
    chairperson: string | null;
    minutes_status: 'draft' | 'confirmed';
    has_minutes: boolean;
    present: number;
    decisions: number;
};

type Filters = {
    q: string;
    when: 'upcoming' | 'past' | 'all';
    committee: number | null;
    status: string | null;
};

type Props = {
    meetings: Paginated<Row>;
    filters: Filters;
    committees: { id: number; name: string }[];
    statuses: Record<string, string>;
};

function MinutesBadge({ row }: { row: Row }) {
    if (!row.has_minutes) {
        return <span className="text-muted-foreground">No minutes</span>;
    }

    return row.minutes_status === 'confirmed' ? (
        <Badge className="bg-emerald-600 text-white hover:bg-emerald-600">
            Minutes confirmed
        </Badge>
    ) : (
        <Badge variant="secondary">Minutes draft</Badge>
    );
}

export default function MeetingsIndex({
    meetings,
    filters,
    committees,
    statuses,
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
                when: next.when !== 'all' ? next.when : undefined,
                committee: next.committee || undefined,
                status: next.status || undefined,
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

    return (
        <>
            <Head title="Meetings" />

            <div className="max-w-6xl space-y-5 p-4">
                <PageHeader
                    title="Meetings"
                    description="Committee meetings, their minutes, and the decisions taken."
                    actions={
                        can('meetings.manage') && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> Add Meeting
                                </Link>
                            </Button>
                        )
                    }
                />

                <MeetingsNav />

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-56 flex-1 sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Search committee or title"
                            className="pl-9"
                            aria-label="Search"
                        />
                    </div>
                    <NativeSelect
                        aria-label="Committee"
                        className="w-auto max-w-64"
                        value={filters.committee ?? ''}
                        onChange={(e) =>
                            visit({
                                committee: e.target.value
                                    ? Number(e.target.value)
                                    : null,
                            })
                        }
                    >
                        <option value="">Any committee</option>
                        {committees.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        aria-label="When"
                        className="w-auto"
                        value={filters.when}
                        onChange={(e) =>
                            visit({ when: e.target.value as Filters['when'] })
                        }
                    >
                        <option value="all">All dates</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="past">Past</option>
                    </NativeSelect>
                    <NativeSelect
                        aria-label="Status"
                        className="w-auto"
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            visit({ status: e.target.value || null })
                        }
                    >
                        <option value="">Any status</option>
                        {Object.entries(statuses).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </NativeSelect>
                </div>

                {meetings.data.length === 0 ? (
                    <p className="rounded-lg border py-12 text-center text-sm text-muted-foreground">
                        No meetings match.{' '}
                        {can('meetings.manage') && 'Add one to begin.'}
                    </p>
                ) : (
                    <>
                        {/* Phone: cards. */}
                        <ul className="space-y-2 md:hidden">
                            {meetings.data.map((m) => (
                                <li key={m.id}>
                                    <Link
                                        href={show(m.id)}
                                        className="block space-y-1.5 rounded-lg border p-3 hover:border-primary/60"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="font-medium">
                                                {m.heading}
                                            </span>
                                            <MeetingStatusBadge
                                                status={m.status}
                                            />
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {[
                                                shortDate(m.meeting_date),
                                                m.starts_at,
                                                m.venue,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                        <p className="text-xs">
                                            <MinutesBadge row={m} />
                                            <span className="ml-2 text-muted-foreground">
                                                {m.present} present ·{' '}
                                                {m.decisions} decisions
                                            </span>
                                        </p>
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
                                        <TableHead>Meeting</TableHead>
                                        <TableHead>Venue</TableHead>
                                        <TableHead>Chairperson</TableHead>
                                        <TableHead>Present</TableHead>
                                        <TableHead>Decisions</TableHead>
                                        <TableHead>Minutes</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {meetings.data.map((m) => (
                                        <TableRow key={m.id}>
                                            <TableCell className="whitespace-nowrap">
                                                {shortDate(m.meeting_date)}
                                                <span className="block text-xs text-muted-foreground">
                                                    {m.starts_at}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={show(m.id)}
                                                    className="font-medium hover:underline"
                                                >
                                                    {m.heading}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {m.venue ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {m.chairperson ?? '—'}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {m.present}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {m.decisions}
                                            </TableCell>
                                            <TableCell>
                                                <MinutesBadge row={m} />
                                            </TableCell>
                                            <TableCell>
                                                <MeetingStatusBadge
                                                    status={m.status}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination page={meetings} />
                    </>
                )}
            </div>
        </>
    );
}

MeetingsIndex.layout = {
    breadcrumbs: [{ title: 'Meetings', href: index() }],
};
