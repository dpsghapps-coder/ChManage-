import { Head, Link, router, usePage } from '@inertiajs/react';
import { Lock, Pencil, Plus, Trash2, Users } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { usePermission } from '@/hooks/use-permission';
import { create, destroy, edit, index } from '@/routes/admin/roles';

type RoleCard = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    users_count: number;
    permissions: string[];
};

type Props = {
    roles: RoleCard[];
    modules: Record<string, string>;
};

/** "members.view", "members.edit" -> { members: 2 } */
function countByModule(permissions: string[]): Record<string, number> {
    return permissions.reduce<Record<string, number>>((counts, name) => {
        const module = name.split('.')[0];
        counts[module] = (counts[module] ?? 0) + 1;

        return counts;
    }, {});
}

export default function RolesIndex({ roles, modules }: Props) {
    const { can } = usePermission();
    const errors = usePage().props.errors as Record<string, string> | undefined;

    return (
        <>
            <Head title="Roles" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Roles"
                    description="A role is a bundle of permissions. Give each user one role. Change a role here and everyone who has it is updated at once."
                    actions={
                        can('roles.manage') && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> New Role
                                </Link>
                            </Button>
                        )
                    }
                />

                {errors?.role && (
                    <p
                        role="alert"
                        className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {errors.role}
                    </p>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {roles.map((role) => {
                        const counts = countByModule(role.permissions);
                        const deletable =
                            !role.is_system && role.users_count === 0;

                        return (
                            <Card key={role.id} className="flex flex-col">
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-2">
                                        <CardTitle className="flex items-center gap-2">
                                            {role.name}
                                            {role.is_system && (
                                                <Lock
                                                    className="size-3.5 text-muted-foreground"
                                                    aria-label="System role"
                                                />
                                            )}
                                        </CardTitle>
                                        <Badge
                                            variant="secondary"
                                            className="shrink-0"
                                        >
                                            <Users className="size-3" />{' '}
                                            {role.users_count}
                                        </Badge>
                                    </div>
                                    <CardDescription>
                                        {role.description ?? (
                                            <span className="italic">
                                                No description
                                            </span>
                                        )}
                                    </CardDescription>
                                </CardHeader>

                                <CardContent className="flex-1 space-y-3">
                                    <p className="text-sm font-medium">
                                        {role.slug === 'admin'
                                            ? 'Everything'
                                            : `${role.permissions.length} permission${role.permissions.length === 1 ? '' : 's'}`}
                                    </p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {Object.entries(counts).map(
                                            ([module, count]) => (
                                                <Badge
                                                    key={module}
                                                    variant="outline"
                                                    className="font-normal"
                                                >
                                                    {modules[module] ?? module}{' '}
                                                    · {count}
                                                </Badge>
                                            ),
                                        )}
                                        {role.permissions.length === 0 && (
                                            <span className="text-sm text-muted-foreground">
                                                No permissions — this role
                                                cannot do anything yet.
                                            </span>
                                        )}
                                    </div>
                                </CardContent>

                                {can('roles.manage') && (
                                    <CardFooter className="gap-2">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link href={edit(role.id)}>
                                                <Pencil />{' '}
                                                {role.slug === 'admin'
                                                    ? 'View'
                                                    : 'Edit'}
                                            </Link>
                                        </Button>

                                        {deletable && (
                                            <Dialog>
                                                <DialogTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive hover:text-destructive"
                                                    >
                                                        <Trash2 /> Delete
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogHeader>
                                                        <DialogTitle>
                                                            Delete the{' '}
                                                            {role.name} Role?
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            No users have this
                                                            role. This cannot be
                                                            undone.
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <DialogFooter>
                                                        <DialogClose asChild>
                                                            <Button variant="ghost">
                                                                Cancel
                                                            </Button>
                                                        </DialogClose>
                                                        <DialogClose asChild>
                                                            <Button
                                                                variant="destructive"
                                                                onClick={() =>
                                                                    router.delete(
                                                                        destroy(
                                                                            role.id,
                                                                        ).url,
                                                                    )
                                                                }
                                                            >
                                                                Delete Role
                                                            </Button>
                                                        </DialogClose>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>
                                        )}
                                    </CardFooter>
                                )}
                            </Card>
                        );
                    })}
                </div>
            </div>
        </>
    );
}

RolesIndex.layout = { breadcrumbs: [{ title: 'Roles', href: index() }] };
