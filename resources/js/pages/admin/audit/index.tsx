import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
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
import { index } from '@/routes/admin/audit';

type Log = {
    id: number;
    event: string;
    description: string;
    properties: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string | null;
    user: { id: number; username: string; name: string } | null;
};

type Props = {
    logs: Paginated<Log>;
    filters: { q?: string; group?: string };
    groups: Record<string, string>;
};

const tone = (event: string): 'destructive' | 'secondary' | 'outline' => {
    if (
        event === 'auth.failed' ||
        event === 'auth.blocked' ||
        event.endsWith('deactivated') ||
        event.endsWith('deleted')
    ) {
        return 'destructive';
    }

    return event.startsWith('auth.') ? 'secondary' : 'outline';
};

export default function AuditIndex({ logs, filters, groups }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [group, setGroup] = useState(filters.group ?? '');

    const apply = (next: { q?: string; group?: string }) => {
        router.get(
            index().url,
            { q, group, ...next },
            { preserveState: true, replace: true },
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({});
    };

    return (
        <>
            <Head title="Audit Log" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Audit Log"
                    description="A record of sign-ins and of changes to users, roles, permissions and staff. Entries cannot be edited or deleted here."
                />

                <form onSubmit={submit} className="flex flex-wrap gap-2">
                    <div className="relative min-w-56 flex-1">
                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search descriptions"
                            className="pl-9"
                            aria-label="Search the audit log"
                        />
                    </div>
                    <NativeSelect
                        value={group}
                        onChange={(e) => {
                            setGroup(e.target.value);
                            apply({ group: e.target.value });
                        }}
                        className="w-44"
                        aria-label="Filter by area"
                    >
                        <option value="">All areas</option>
                        {Object.entries(groups).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </NativeSelect>
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>When</TableHead>
                                <TableHead>Who</TableHead>
                                <TableHead>Event</TableHead>
                                <TableHead>What Happened</TableHead>
                                <TableHead>IP Address</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {logs.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        Nothing recorded yet.
                                    </TableCell>
                                </TableRow>
                            )}
                            {logs.data.map((log) => (
                                <TableRow key={log.id} className="align-top">
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {log.created_at
                                            ? new Date(
                                                  log.created_at,
                                              ).toLocaleString()
                                            : ''}
                                    </TableCell>
                                    <TableCell>
                                        {log.user ? (
                                            log.user.username
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Anonymous
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={tone(log.event)}
                                            className="font-mono text-[11px] font-normal"
                                        >
                                            {log.event}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="max-w-md">
                                        <p>{log.description}</p>
                                        {log.properties && (
                                            <details className="mt-1 text-xs text-muted-foreground">
                                                <summary className="cursor-pointer">
                                                    Details
                                                </summary>
                                                <pre className="mt-1 overflow-x-auto rounded bg-muted p-2 whitespace-pre-wrap">
                                                    {JSON.stringify(
                                                        log.properties,
                                                        null,
                                                        2,
                                                    )}
                                                </pre>
                                            </details>
                                        )}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs text-muted-foreground">
                                        {log.ip_address}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination page={logs} />
            </div>
        </>
    );
}

AuditIndex.layout = { breadcrumbs: [{ title: 'Audit Log', href: index() }] };
