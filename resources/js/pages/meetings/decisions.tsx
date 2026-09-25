import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { MeetingsNav, shortDate } from '@/components/meetings-nav';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { index as decisionsIndex } from '@/routes/decisions';
import { index as actionsIndex } from '@/routes/actions';
import { show } from '@/routes/meetings';

type Row = {
    id: number;
    kind: string;
    text: string;
    actions_total: number;
    actions_open: number;
    meeting: { id: number; date: string; committee: string };
};

type Filters = { committee: number | null; kind: string | null; q: string };

type Props = {
    decisions: Paginated<Row>;
    filters: Filters;
    committees: { id: number; name: string }[];
    kinds: Record<string, string>;
};

export default function Decisions({
    decisions,
    filters,
    committees,
    kinds,
}: Props) {
    const [term, setTerm] = useState(filters.q);
    const first = useRef(true);

    const visit = (changes: Partial<Filters>) => {
        const next = { ...filters, q: term, ...changes };
        router.get(
            decisionsIndex().url,
            {
                q: next.q || undefined,
                committee: next.committee || undefined,
                kind: next.kind || undefined,
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
            <Head title="Decisions" />

            <div className="max-w-5xl space-y-5 p-4">
                <PageHeader
                    title="Decisions & Resolutions"
                    description="Everything the committees have decided, with the actions that followed. Newest meetings first."
                />
                <MeetingsNav />

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-56 flex-1 sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Search the wording"
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
                        aria-label="Kind"
                        className="w-auto"
                        value={filters.kind ?? ''}
                        onChange={(e) =>
                            visit({ kind: e.target.value || null })
                        }
                    >
                        <option value="">Decisions and resolutions</option>
                        {Object.entries(kinds).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}s
                            </option>
                        ))}
                    </NativeSelect>
                </div>

                {decisions.data.length === 0 ? (
                    <p className="rounded-lg border py-12 text-center text-sm text-muted-foreground">
                        No decisions match.
                    </p>
                ) : (
                    <>
                        <ul className="divide-y rounded-lg border">
                            {decisions.data.map((d) => (
                                <li key={d.id} className="space-y-1 p-4">
                                    <p className="text-sm">
                                        <span className="mr-2 rounded bg-muted px-1.5 py-0.5 text-xs">
                                            {kinds[d.kind]}
                                        </span>
                                        {d.text}
                                    </p>
                                    <p className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                                        <Link
                                            href={show(d.meeting.id)}
                                            className="underline hover:text-foreground"
                                        >
                                            {d.meeting.committee},{' '}
                                            {shortDate(d.meeting.date)}
                                        </Link>
                                        {d.actions_total > 0 ? (
                                            <Link
                                                href={actionsIndex({
                                                    query: {
                                                        view: 'all',
                                                        q: '',
                                                    },
                                                })}
                                                className="hover:text-foreground"
                                            >
                                                {d.actions_open} of{' '}
                                                {d.actions_total} action
                                                {d.actions_total === 1
                                                    ? ''
                                                    : 's'}{' '}
                                                still open
                                            </Link>
                                        ) : (
                                            <span>No actions</span>
                                        )}
                                    </p>
                                </li>
                            ))}
                        </ul>
                        <Pagination page={decisions} />
                    </>
                )}
            </div>
        </>
    );
}

Decisions.layout = {
    breadcrumbs: [{ title: 'Decisions', href: decisionsIndex() }],
};
