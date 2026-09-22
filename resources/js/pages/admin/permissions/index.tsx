import { Head, Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';
import { edit } from '@/routes/admin/roles';
import { index } from '@/routes/admin/permissions';

type Props = {
    groups: {
        module: string;
        label: string;
        permissions: {
            id: number;
            name: string;
            description: string | null;
            role_ids: number[];
        }[];
    }[];
    roles: { id: number; name: string; slug: string }[];
};

export default function PermissionsIndex({ groups, roles }: Props) {
    const { can } = usePermission();

    return (
        <>
            <Head title="Permissions" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Permission List"
                    description="Every action the system can allow or deny, and which roles currently hold it. Permissions are defined by the system; build roles from them on the Roles page."
                />

                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="min-w-72">
                                    Permission
                                </TableHead>
                                {roles.map((role) => (
                                    <TableHead
                                        key={role.id}
                                        className="text-center"
                                    >
                                        <span className="block max-w-24 leading-tight whitespace-normal">
                                            {can('roles.manage') &&
                                            role.slug !== 'admin' ? (
                                                <Link
                                                    href={edit(role.id)}
                                                    className="hover:underline"
                                                >
                                                    {role.name}
                                                </Link>
                                            ) : (
                                                role.name
                                            )}
                                        </span>
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {groups.map((group) => (
                                <GroupRows
                                    key={group.module}
                                    group={group}
                                    roles={roles}
                                />
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

function GroupRows({
    group,
    roles,
}: {
    group: Props['groups'][number];
    roles: Props['roles'];
}) {
    return (
        <>
            <TableRow className="bg-muted/40 hover:bg-muted/40">
                <TableCell
                    colSpan={roles.length + 1}
                    className="py-2 font-medium"
                >
                    {group.label}
                </TableCell>
            </TableRow>
            {group.permissions.map((permission) => (
                <TableRow key={permission.id}>
                    <TableCell>
                        <div className="font-mono text-xs">
                            {permission.name}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {permission.description}
                        </div>
                    </TableCell>
                    {roles.map((role) => (
                        <TableCell key={role.id} className="text-center">
                            {permission.role_ids.includes(role.id) ? (
                                <Check
                                    className="mx-auto size-4 text-emerald-600"
                                    aria-label={`${role.name} has ${permission.name}`}
                                />
                            ) : (
                                <span
                                    className="text-muted-foreground/40"
                                    aria-label={`${role.name} does not have ${permission.name}`}
                                >
                                    –
                                </span>
                            )}
                        </TableCell>
                    ))}
                </TableRow>
            ))}
        </>
    );
}

PermissionsIndex.layout = {
    breadcrumbs: [{ title: 'Permissions', href: index() }],
};
