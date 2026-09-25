import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    ActionBadge,
    MeetingsNav,
    deadlineText,
    shortDate,
} from '@/components/meetings-nav';
import type { ActionRow } from '@/components/meetings-nav';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/actions';
import { show } from '@/routes/meetings';

type Row = ActionRow & {
    decision_text: string;
    meeting: { id: number; date: string; committee: string };
};

type Filters = { view: string; committee: number | null; q: string };

type Props = {
    actions: Paginated<Row>;
    counts: Record<string, number>;
    filters: Filters;
    committees: { id: number; name: string }[];
    statuses: Record<string, string>;
    shown: Record<string, string>;
};

// The order the views are offered in.
const views = [
    'open',
    'overdue',
    'pending',
    'in_progress',
    'completed',
    'cancelled',
    'all',
];
const viewLabel: Record<string, string> = { open: 'Open', all: 'All' };

export default function Actions({
    actions,
    counts,
    filters,
    committees,
    statuses,
    shown,
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
                view: next.view !== 'open' ? next.view : undefined,
                committee: next.committee || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

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
            <Head title="Actions" />

            <div className="max-w-6xl space-y-5 p-4">
                <PageHeader
                    title="Action Management"
                    description="Decision → action → person responsible → deadline → status, across every committee."
                />
                <MeetingsNav />

                <div
                    className="flex flex-wrap gap-2"
                    role="group"
                    aria-label="Status"
                >
                    {views.map((key) => (
                        <button
                            key={key}
                            type="button"
                            aria-pressed={filters.view === key}
                            onClick={() => visit({ view: key })}
                            className={cn(
                                'rounded-full border px-3 py-1 text-sm transition-colors',
                                filters.view === key
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'hover:bg-accent',
                                key === 'overdue' &&
                                    counts.overdue > 0 &&
                                    filters.view !== key &&
                                    'border-red-500/60 text-red-600',
                            )}
                        >
                            {viewLabel[key] ?? shown[key] ?? statuses[key]}{' '}
                            <span className="ml-1 tabular-nums opacity-80">
                                {counts[key] ?? 0}
                            </span>
                        </button>
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-56 flex-1 sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Search the action or the person"
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
                </div>

                {actions.data.length === 0 ? (
                    <p className="rounded-lg border py-12 text-center text-sm text-muted-foreground">
                        {filters.view === 'overdue'
                            ? 'Nothing is overdue.'
                            : 'No actions match.'}
                    </p>
                ) : (
                    <>
                        <ul className="divide-y rounded-lg border">
                            {actions.data.map((a) => (
                                <li key={a.id} className="space-y-2 p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <p className="min-w-0 flex-1 text-sm font-medium">
                                            {a.description}
                                        </p>
                                        <ActionBadge status={a.shown} />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        From the{' '}
                                        {a.decision_text.length > 90
                                            ? `${a.decision_text.slice(0, 90)}…`
                                            : a.decision_text}
                                    </p>
                                    <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <span className="flex flex-wrap gap-x-4 gap-y-1">
                                            <span>
                                                {a.responsible ?? (
                                                    <span className="text-muted-foreground">
                                                        No one assigned
                                                    </span>
                                                )}
                                            </span>
                                            <span
                                                className={cn(
                                                    a.shown === 'overdue'
                                                        ? 'font-medium text-red-600'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {deadlineText(a)}
                                            </span>
                                            <Link
                                                href={show(a.meeting.id)}
                                                className="text-muted-foreground underline hover:text-foreground"
                                            >
                                                {a.meeting.committee},{' '}
                                                {shortDate(a.meeting.date)}
                                            </Link>
                                        </span>
                                        {can('meetings.manage') && (
                                            <NativeSelect
                                                aria-label="Change status"
                                                className="h-8 w-auto py-0 text-xs"
                                                value={a.status}
                                                onChange={(e) =>
                                                    router.put(
                                                        update(a.id).url,
                                                        {
                                                            status: e.target
                                                                .value,
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                {Object.entries(statuses).map(
                                                    ([key, label]) => (
                                                        <option
                                                            key={key}
                                                            value={key}
                                                        >
                                                            {label}
                                                        </option>
                                                    ),
                                                )}
                                            </NativeSelect>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                        <Pagination page={actions} />
                    </>
                )}
            </div>
        </>
    );
}

Actions.layout = {
    breadcrumbs: [{ title: 'Actions', href: index() }],
};
