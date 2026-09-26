import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { day } from '@/lib/day';
import { create, index, show } from '@/routes/communion';

type Service = {
    id: number;
    title: string;
    held_on: string;
    venue: string | null;
    status: string;
    expected: number;
    received: number;
};

export default function CommunionIndex({
    services,
    statuses,
}: {
    services: Paginated<Service>;
    statuses: Record<string, string>;
}) {
    const { can } = usePermission();

    return (
        <>
            <Head title="Communion" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Communion"
                    description="The communion services, and who received communion at each."
                    actions={
                        can('communion.manage') && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> New service
                                </Link>
                            </Button>
                        )
                    }
                />

                {services.data.length === 0 ? (
                    <p className="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground">
                        No communion services yet.
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {services.data.map((s) => (
                            <li key={s.id}>
                                <Link
                                    href={show(s.id)}
                                    className="flex flex-wrap items-center justify-between gap-3 p-3 hover:bg-accent/50"
                                >
                                    <span className="min-w-0">
                                        <span className="block font-medium">
                                            {s.title}
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            {day(s.held_on)}
                                            {s.venue ? ` · ${s.venue}` : ''}
                                        </span>
                                    </span>
                                    <span className="flex items-center gap-3 text-sm">
                                        {s.expected > 0 ? (
                                            <span className="tabular-nums">
                                                {s.received} of {s.expected}{' '}
                                                received
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                No list yet
                                            </span>
                                        )}
                                        <Badge
                                            variant={
                                                s.status === 'scheduled'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {statuses[s.status] ?? s.status}
                                        </Badge>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination page={services} />
            </div>
        </>
    );
}

CommunionIndex.layout = {
    breadcrumbs: [{ title: 'Communion', href: index() }],
};
