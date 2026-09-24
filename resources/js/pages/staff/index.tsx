import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, MapPin, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { StatusBadge, statusLabel } from '@/components/staff-status';
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
import { create, index, show } from '@/routes/staff';

type StaffRow = {
    id: number;
    staff_number: string;
    title: string | null;
    full_name: string;
    telephone: string | null;
    location: string | null;
    status: string;
    department: string | null;
    position: string | null;
    user: { username: string; is_active: boolean } | null;
};

type Props = {
    staff: Paginated<StaffRow>;
    filters: {
        q?: string;
        department_id?: string;
        position_id?: string;
        status?: string;
    };
    departments: { id: number; name: string }[];
    positions: { id: number; name: string }[];
    statuses: string[];
};

export default function StaffIndex({
    staff,
    filters,
    departments,
    positions,
    statuses,
}: Props) {
    const { can } = usePermission();
    const [q, setQ] = useState(filters.q ?? '');
    const [departmentId, setDepartmentId] = useState(
        filters.department_id ?? '',
    );
    const [positionId, setPositionId] = useState(filters.position_id ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const apply = (next: Record<string, string>) => {
        router.get(
            index().url,
            {
                q,
                department_id: departmentId,
                position_id: positionId,
                status,
                ...next,
            },
            { preserveState: true, replace: true },
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({});
    };

    return (
        <>
            <Head title="Staff Directory" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Staff Directory"
                    description="Everyone who works for the church. A staff member can be linked to a user account; transferring them never changes that account or its role."
                    actions={
                        can('staff.create') && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> Add Staff
                                </Link>
                            </Button>
                        )
                    }
                />

                <form onSubmit={submit} className="flex flex-wrap gap-2">
                    <div className="relative w-full sm:w-auto sm:min-w-56 sm:flex-1">
                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search name, number or phone"
                            className="pl-9"
                            aria-label="Search staff"
                        />
                    </div>
                    <NativeSelect
                        value={departmentId}
                        onChange={(e) => {
                            setDepartmentId(e.target.value);
                            apply({ department_id: e.target.value });
                        }}
                        className="w-full sm:w-48"
                        aria-label="Filter by department"
                    >
                        <option value="">All departments</option>
                        {departments.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        value={positionId}
                        onChange={(e) => {
                            setPositionId(e.target.value);
                            apply({ position_id: e.target.value });
                        }}
                        className="w-full sm:w-44"
                        aria-label="Filter by position"
                    >
                        <option value="">All positions</option>
                        {positions.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        value={status}
                        onChange={(e) => {
                            setStatus(e.target.value);
                            apply({ status: e.target.value });
                        }}
                        className="w-full sm:w-40"
                        aria-label="Filter by status"
                    >
                        <option value="">Any status</option>
                        {statuses.map((s) => (
                            <option key={s} value={s}>
                                {statusLabel(s)}
                            </option>
                        ))}
                    </NativeSelect>
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                {/* Phones: one card per person. */}
                <div className="grid gap-3 md:hidden">
                    {staff.data.length === 0 && (
                        <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                            No staff match those filters.
                        </p>
                    )}
                    {staff.data.map((person) => (
                        <Link
                            key={person.id}
                            href={show(person.id)}
                            className="flex items-start gap-3 rounded-lg border p-4 transition-colors active:bg-muted/60"
                        >
                            <div className="min-w-0 flex-1 space-y-1.5">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {person.title
                                                ? `${person.title} `
                                                : ''}
                                            {person.full_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {person.staff_number}
                                            {person.telephone
                                                ? ` · ${person.telephone}`
                                                : ''}
                                        </p>
                                    </div>
                                    <StatusBadge status={person.status} />
                                </div>
                                {(person.position || person.department) && (
                                    <p className="text-sm">
                                        {[person.position, person.department]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                )}
                                {person.location && (
                                    <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                        <MapPin className="size-3.5 shrink-0" />
                                        <span className="truncate">
                                            {person.location}
                                        </span>
                                    </p>
                                )}
                                {person.user && (
                                    <Badge
                                        variant="outline"
                                        className={
                                            person.user.is_active
                                                ? ''
                                                : 'opacity-60'
                                        }
                                    >
                                        @{person.user.username}
                                    </Badge>
                                )}
                            </div>
                            <ChevronRight className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        </Link>
                    ))}
                </div>

                <div className="hidden rounded-lg border md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Staff Member</TableHead>
                                <TableHead>Position</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Station</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Login</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {staff.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        No staff match those filters.
                                    </TableCell>
                                </TableRow>
                            )}
                            {staff.data.map((person) => (
                                <TableRow key={person.id}>
                                    <TableCell>
                                        <Link
                                            href={show(person.id)}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {person.title
                                                ? `${person.title} `
                                                : ''}
                                            {person.full_name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {person.staff_number}
                                            {person.telephone
                                                ? ` · ${person.telephone}`
                                                : ''}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {person.position ?? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {person.department ?? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {person.location ?? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={person.status} />
                                    </TableCell>
                                    <TableCell>
                                        {person.user ? (
                                            <Badge
                                                variant="outline"
                                                className={
                                                    person.user.is_active
                                                        ? ''
                                                        : 'opacity-60'
                                                }
                                            >
                                                @{person.user.username}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                No login
                                            </span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination page={staff} />
            </div>
        </>
    );
}

StaffIndex.layout = {
    breadcrumbs: [{ title: 'Staff Directory', href: index() }],
};
