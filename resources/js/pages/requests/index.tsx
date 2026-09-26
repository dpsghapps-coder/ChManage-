import { Head, Link, router } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { RequestStatus } from '@/components/request-status';
import { NativeSelect } from '@/components/ui/native-select';
import { index, show } from '@/routes/requests';

type Row = {
    id: number;
    reference: string;
    type: string;
    type_label: string;
    status: string;
    member: {
        id: number;
        member_number: string;
        full_name: string;
        mobile: string | null;
    };
    submitted_at: string;
    summary: string;
};

type Props = {
    requests: Paginated<Row>;
    filters: { status: string; type: string };
    types: { key: string; label: string }[];
    statuses: Record<string, string>;
    openCount: number;
};

export default function RequestsIndex({
    requests,
    filters,
    types,
    statuses,
    openCount,
}: Props) {
    const apply = (next: Partial<Props['filters']>) =>
        router.get(
            index().url,
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Member requests" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Member requests"
                    description={`What members have asked for from the portal. ${openCount} waiting.`}
                />

                <div className="flex flex-wrap gap-2">
                    <NativeSelect
                        value={filters.status}
                        onChange={(e) => apply({ status: e.target.value })}
                        className="w-full sm:w-44"
                        aria-label="Filter by status"
                    >
                        <option value="open">Waiting</option>
                        <option value="all">Everything</option>
                        {Object.entries(statuses).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        value={filters.type}
                        onChange={(e) => apply({ type: e.target.value })}
                        className="w-full sm:w-64"
                        aria-label="Filter by type"
                    >
                        <option value="">All kinds</option>
                        {types.map((t) => (
                            <option key={t.key} value={t.key}>
                                {t.label}
                            </option>
                        ))}
                    </NativeSelect>
                </div>

                {requests.data.length === 0 ? (
                    <p className="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground">
                        No requests here.
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {requests.data.map((r) => (
                            <li key={r.id}>
                                <Link
                                    href={show(r.id)}
                                    className="flex flex-wrap items-center justify-between gap-3 p-3 hover:bg-accent/50"
                                >
                                    <span className="min-w-0">
                                        <span className="block font-medium">
                                            {r.type_label}{' '}
                                            <span className="text-sm font-normal text-muted-foreground">
                                                · {r.member.full_name} (
                                                {r.member.member_number})
                                            </span>
                                        </span>
                                        <span className="block truncate text-sm text-muted-foreground">
                                            {r.summary || 'No details'}
                                        </span>
                                    </span>
                                    <span className="flex items-center gap-3 text-xs text-muted-foreground">
                                        {r.submitted_at.slice(0, 10)}
                                        <RequestStatus status={r.status} />
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination page={requests} />
            </div>
        </>
    );
}

RequestsIndex.layout = {
    breadcrumbs: [{ title: 'Member requests', href: index() }],
};
