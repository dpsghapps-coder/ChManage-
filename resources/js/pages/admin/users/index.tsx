import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
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
import { usePermission } from '@/hooks/use-permission';
import { create, edit, index } from '@/routes/admin/users';
import { show as staffShow } from '@/routes/staff';

type UserRow = {
    id: number;
    username: string;
    name: string;
    email: string | null;
    is_active: boolean;
    must_reset_password: boolean;
    has_password: boolean;
    last_login_at: string | null;
    role: { id: number; name: string; slug: string } | null;
    staff: { id: number; staff_number: string; full_name: string } | null;
};

type Props = {
    users: Paginated<UserRow>;
    roles: { id: number; name: string }[];
    filters: { q?: string; role_id?: string; status?: string };
};

function accountState(user: UserRow) {
    if (!user.is_active) {
        return <Badge variant="secondary">Inactive</Badge>;
    }

    if (!user.has_password) {
        return <Badge variant="outline">No password yet</Badge>;
    }

    if (user.must_reset_password) {
        return <Badge variant="outline">Must change password</Badge>;
    }

    return (
        <Badge className="bg-emerald-600 text-white hover:bg-emerald-600">
            Active
        </Badge>
    );
}

export default function UsersIndex({ users, roles, filters }: Props) {
    const { can } = usePermission();
    const [q, setQ] = useState(filters.q ?? '');
    const [roleId, setRoleId] = useState(filters.role_id ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const apply = (next: { q?: string; role_id?: string; status?: string }) => {
        router.get(
            index().url,
            { q, role_id: roleId, status, ...next },
            { preserveState: true, replace: true },
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({});
    };

    return (
        <>
            <Head title="Users" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Users"
                    description="Sign-in accounts. Each user has one role, which decides what they can do, and may be linked to a staff member."
                    actions={
                        can('users.create') && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> New User
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
                            placeholder="Search name, username or email"
                            className="pl-9"
                            aria-label="Search users"
                        />
                    </div>
                    <NativeSelect
                        value={roleId}
                        onChange={(e) => {
                            setRoleId(e.target.value);
                            apply({ role_id: e.target.value });
                        }}
                        className="w-full sm:w-48"
                        aria-label="Filter by role"
                    >
                        <option value="">All roles</option>
                        {roles.map((role) => (
                            <option key={role.id} value={role.id}>
                                {role.name}
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
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </NativeSelect>
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                {/* Phones: one card per account. */}
                <div className="grid gap-3 md:hidden">
                    {users.data.length === 0 && (
                        <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                            No users match those filters.
                        </p>
                    )}
                    {users.data.map((user) => (
                        <div
                            key={user.id}
                            className="space-y-3 rounded-lg border p-4"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {user.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        @{user.username}
                                    </p>
                                </div>
                                {accountState(user)}
                            </div>
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Role
                                    </dt>
                                    <dd>{user.role?.name ?? 'None'}</dd>
                                </div>
                                <div className="min-w-0">
                                    <dt className="text-xs text-muted-foreground">
                                        Staff record
                                    </dt>
                                    <dd className="truncate">
                                        {user.staff ? (
                                            can('staff.view') ? (
                                                <Link
                                                    href={staffShow(
                                                        user.staff.id,
                                                    )}
                                                    className="underline underline-offset-4"
                                                >
                                                    {user.staff.full_name}
                                                </Link>
                                            ) : (
                                                user.staff.full_name
                                            )
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Not linked
                                            </span>
                                        )}
                                    </dd>
                                </div>
                                <div className="col-span-2">
                                    <dt className="text-xs text-muted-foreground">
                                        Last sign-in
                                    </dt>
                                    <dd>
                                        {user.last_login_at
                                            ? new Date(
                                                  user.last_login_at,
                                              ).toLocaleString()
                                            : 'Never'}
                                    </dd>
                                </div>
                            </dl>
                            {can('users.edit') && (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="w-full"
                                >
                                    <Link href={edit(user.id)}>Edit user</Link>
                                </Button>
                            )}
                        </div>
                    ))}
                </div>

                <div className="hidden rounded-lg border md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>User</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Staff Record</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Last Sign-In</TableHead>
                                <TableHead className="w-20" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        No users match those filters.
                                    </TableCell>
                                </TableRow>
                            )}
                            {users.data.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {user.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            @{user.username}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {user.role ? (
                                            <Badge variant="outline">
                                                {user.role.name}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                None
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {user.staff ? (
                                            can('staff.view') ? (
                                                <Link
                                                    href={staffShow(
                                                        user.staff.id,
                                                    )}
                                                    className="underline-offset-4 hover:underline"
                                                >
                                                    {user.staff.full_name}
                                                </Link>
                                            ) : (
                                                user.staff.full_name
                                            )
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Not linked
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>{accountState(user)}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {user.last_login_at
                                            ? new Date(
                                                  user.last_login_at,
                                              ).toLocaleString()
                                            : 'Never'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {can('users.edit') && (
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link href={edit(user.id)}>
                                                    Edit
                                                </Link>
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination page={users} />
            </div>
        </>
    );
}

UsersIndex.layout = { breadcrumbs: [{ title: 'Users', href: index() }] };
