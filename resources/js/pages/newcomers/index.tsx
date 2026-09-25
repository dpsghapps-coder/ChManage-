import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { PersonAvatar } from '@/components/person-avatar';
import type { Paginated } from '@/components/pagination';
import {
    NewcomersNav,
    PersonStatusBadge,
    StageBadge,
} from '@/components/newcomers-nav';
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
import { create, index, show } from '@/routes/newcomers';

type Row = {
    id: number;
    name: string;
    title: string | null;
    sex: 'male' | 'female' | null;
    age: number | null;
    mobile: string | null;
    photo_url: string | null;
    stage: string;
    status: string;
    first_visit_on: string | null;
    counsellor: string | null;
    lessons_done: number;
    lessons_total: number;
};

type Filters = {
    q: string;
    stage: string | null;
    status: string | null;
    counsellor: string | null;
    mine: boolean;
};

type Props = {
    people: Paginated<Row>;
    stages: Record<string, string>;
    statuses: Record<string, string>;
    counts: Record<string, number>;
    counsellors: { id: number; name: string }[];
    myCounsellorId: number | null;
    filters: Filters;
};

const formatDate = (iso: string | null) =>
    iso
        ? new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';

export default function NewcomersIndex({
    people,
    stages,
    statuses,
    counts,
    counsellors,
    myCounsellorId,
    filters,
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
                stage: next.stage || undefined,
                status: next.status || undefined,
                counsellor: next.counsellor || undefined,
                mine: next.mine ? 1 : undefined,
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

    const total = Object.values(counts).reduce((a, b) => a + b, 0);

    return (
        <>
            <Head title="Newcomers" />

            <div className="max-w-6xl space-y-5 p-4">
                <PageHeader
                    title="Visitors & Newcomers"
                    description="People on the way to membership: Visitor, Newcomer, Catechumen, then Member."
                    actions={
                        can('newcomers.manage') && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> Register Visitor
                                </Link>
                            </Button>
                        )
                    }
                />

                <NewcomersNav />

                <div
                    className="flex flex-wrap gap-2"
                    role="group"
                    aria-label="Stage"
                >
                    <StageChip
                        label="All"
                        count={total}
                        active={!filters.stage}
                        onClick={() => visit({ stage: null })}
                    />
                    {Object.keys(stages).map((key) => (
                        <StageChip
                            key={key}
                            label={stages[key]}
                            count={counts[key] ?? 0}
                            active={filters.stage === key}
                            onClick={() => visit({ stage: key })}
                        />
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-56 flex-1 sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Search name or phone"
                            className="pl-9"
                            aria-label="Search"
                        />
                    </div>
                    <NativeSelect
                        aria-label="Status"
                        className="w-auto"
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            visit({ status: e.target.value || null })
                        }
                    >
                        <option value="">Active and on hold</option>
                        {Object.entries(statuses).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label} only
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        aria-label="Counsellor"
                        className="w-auto"
                        value={filters.counsellor ?? ''}
                        onChange={(e) =>
                            visit({ counsellor: e.target.value || null })
                        }
                    >
                        <option value="">Any counsellor</option>
                        <option value="none">No counsellor yet</option>
                        {counsellors.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </NativeSelect>
                    {myCounsellorId && (
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={filters.mine}
                                onChange={(e) =>
                                    visit({ mine: e.target.checked })
                                }
                            />
                            My newcomers
                        </label>
                    )}
                </div>

                {people.data.length === 0 ? (
                    <p className="rounded-lg border py-12 text-center text-sm text-muted-foreground">
                        No one matches.{' '}
                        {can('newcomers.manage') &&
                            'Register a visitor to begin.'}
                    </p>
                ) : (
                    <>
                        {/* Phone: cards. */}
                        <ul className="space-y-2 md:hidden">
                            {people.data.map((p) => (
                                <li key={p.id}>
                                    <Link
                                        href={show(p.id)}
                                        className="block space-y-1.5 rounded-lg border p-3 hover:border-primary/60"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="flex items-center gap-2 font-medium">
                                                <PersonAvatar
                                                    name={p.name}
                                                    photoUrl={p.photo_url}
                                                />
                                                <span>
                                                    {p.title
                                                        ? `${p.title} `
                                                        : ''}
                                                    {p.name}
                                                </span>
                                            </span>
                                            <span className="flex gap-1">
                                                <PersonStatusBadge
                                                    status={p.status}
                                                />
                                                <StageBadge stage={p.stage} />
                                            </span>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {[
                                                p.mobile,
                                                p.age !== null
                                                    ? `${p.age} yrs`
                                                    : null,
                                                `First visit ${formatDate(p.first_visit_on)}`,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {p.counsellor
                                                ? `Counsellor: ${p.counsellor}`
                                                : 'No counsellor yet'}
                                            {p.stage === 'catechumen' &&
                                            p.lessons_total > 0
                                                ? ` · Lessons ${p.lessons_done}/${p.lessons_total}`
                                                : ''}
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
                                        <TableHead>Name</TableHead>
                                        <TableHead>Stage</TableHead>
                                        <TableHead>Phone</TableHead>
                                        <TableHead>Age</TableHead>
                                        <TableHead>First visit</TableHead>
                                        <TableHead>Lessons</TableHead>
                                        <TableHead>Counsellor</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {people.data.map((p) => (
                                        <TableRow key={p.id}>
                                            <TableCell>
                                                <Link
                                                    href={show(p.id)}
                                                    className="flex items-center gap-3 font-medium hover:underline"
                                                >
                                                    <PersonAvatar
                                                        name={p.name}
                                                        photoUrl={p.photo_url}
                                                    />
                                                    <span>
                                                        {p.title
                                                            ? `${p.title} `
                                                            : ''}
                                                        {p.name}
                                                    </span>
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <span className="flex gap-1">
                                                    <StageBadge
                                                        stage={p.stage}
                                                    />
                                                    <PersonStatusBadge
                                                        status={p.status}
                                                    />
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {p.mobile ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {p.age ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(p.first_visit_on)}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {p.stage === 'catechumen' &&
                                                p.lessons_total > 0
                                                    ? `${p.lessons_done}/${p.lessons_total}`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell
                                                className={cn(
                                                    !p.counsellor &&
                                                        'text-muted-foreground',
                                                )}
                                            >
                                                {p.counsellor ?? 'None yet'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination page={people} />
                    </>
                )}
            </div>
        </>
    );
}

function StageChip({
    label,
    count,
    active,
    onClick,
}: {
    label: string;
    count: number;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'rounded-full border px-3 py-1 text-sm transition-colors',
                active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'hover:bg-accent',
            )}
        >
            {label}{' '}
            <span className="ml-1 tabular-nums opacity-80">{count}</span>
        </button>
    );
}

NewcomersIndex.layout = {
    breadcrumbs: [{ title: 'Newcomers', href: index() }],
};
