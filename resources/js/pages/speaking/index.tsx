import { Head, Link, router } from '@inertiajs/react';
import { Plus, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { usePermission } from '@/hooks/use-permission';
import { day } from '@/lib/day';
import { create, index, show } from '@/routes/speaking';

export type Note = {
    id: number;
    member: { id: number; full_name: string; member_number: string };
    service: { id: number; title: string; held_on: string } | null;
    spoken_on: string;
    spoken_by: string | null;
    outcome: string;
};

type Props = {
    notes: Paginated<Note>;
    filters: { service?: string; outcome?: string; q?: string };
    services: { id: number; title: string; held_on: string }[];
    outcomes: Record<string, string>;
};

export default function SpeakingIndex({
    notes,
    filters,
    services,
    outcomes,
}: Props) {
    const { can } = usePermission();
    const [q, setQ] = useState(filters.q ?? '');

    const apply = (next: Partial<Props['filters']>) =>
        router.get(
            index().url,
            { ...filters, q, ...next },
            { preserveState: true, replace: true },
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({});
    };

    return (
        <>
            <Head title="Speaking" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Speaking"
                    description="Notes of speaking to members before communion."
                    actions={
                        can('speaking.manage') && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> New note
                                </Link>
                            </Button>
                        )
                    }
                />

                <p className="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3 text-sm">
                    <ShieldAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />
                    <span>
                        These are confidential. Only people with the Speaking
                        permission can read them, and every note opened is
                        recorded in the audit log.
                    </span>
                </p>

                <form onSubmit={submit} className="flex flex-wrap gap-2">
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Member name or number"
                        className="w-full sm:w-64"
                        aria-label="Search by member"
                    />
                    <NativeSelect
                        value={filters.service ?? ''}
                        onChange={(e) => apply({ service: e.target.value })}
                        className="w-full sm:w-64"
                        aria-label="Filter by communion service"
                    >
                        <option value="">All communion services</option>
                        {services.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.title} ({s.held_on})
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        value={filters.outcome ?? ''}
                        onChange={(e) => apply({ outcome: e.target.value })}
                        className="w-full sm:w-52"
                        aria-label="Filter by outcome"
                    >
                        <option value="">Any outcome</option>
                        {Object.entries(outcomes).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </NativeSelect>
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                {notes.data.length === 0 ? (
                    <p className="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground">
                        No notes match.
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {notes.data.map((n) => (
                            <li key={n.id}>
                                <Link
                                    href={show(n.id)}
                                    className="flex flex-wrap items-center justify-between gap-3 p-3 hover:bg-accent/50"
                                >
                                    <span className="min-w-0">
                                        <span className="block font-medium">
                                            {n.member.full_name}{' '}
                                            <span className="text-sm font-normal text-muted-foreground">
                                                {n.member.member_number}
                                            </span>
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            {day(n.spoken_on)}
                                            {n.spoken_by
                                                ? ` · by ${n.spoken_by}`
                                                : ''}
                                            {n.service
                                                ? ` · ${n.service.title}`
                                                : ''}
                                        </span>
                                    </span>
                                    <Badge
                                        variant={
                                            n.outcome === 'cleared'
                                                ? 'outline'
                                                : 'default'
                                        }
                                    >
                                        {outcomes[n.outcome] ?? n.outcome}
                                    </Badge>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination page={notes} />
            </div>
        </>
    );
}

SpeakingIndex.layout = {
    breadcrumbs: [{ title: 'Speaking', href: index() }],
};
