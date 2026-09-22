import { Head, Link, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index, store, update } from '@/routes/admin/roles';

type Permission = {
    id: number;
    name: string;
    action: string;
    description: string | null;
};
type Group = { module: string; label: string; permissions: Permission[] };

type Props = {
    role: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        is_system: boolean;
        is_admin: boolean;
        users_count: number;
        permission_ids: number[];
    } | null;
    groups: Group[];
    /** Permission ids the signed-in user is allowed to grant. */
    held: number[];
};

const humanise = (action: string) =>
    action.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());

export default function RoleForm({ role, groups, held }: Props) {
    const editing = role !== null;
    const locked = role?.is_admin ?? false;
    const heldSet = new Set(held);

    const form = useForm({
        name: role?.name ?? '',
        description: role?.description ?? '',
        permissions: role?.permission_ids ?? ([] as number[]),
    });

    const selected = new Set(form.data.permissions);
    const total = groups.reduce(
        (sum, group) => sum + group.permissions.length,
        0,
    );

    const setSelected = (next: Set<number>) =>
        form.setData('permissions', [...next]);

    const toggle = (id: number, on: boolean) => {
        const next = new Set(selected);

        if (on) {
            next.add(id);
        } else {
            next.delete(id);
        }

        setSelected(next);
    };

    const toggleGroup = (group: Group, on: boolean) => {
        const next = new Set(selected);

        group.permissions
            .filter((p) => heldSet.has(p.id))
            .forEach((p) => (on ? next.add(p.id) : next.delete(p.id)));
        setSelected(next);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing) {
            form.put(update(role.id).url);
        } else {
            form.post(store().url);
        }
    };

    return (
        <>
            <Head title={editing ? `Edit ${role.name}` : 'New Role'} />

            <form onSubmit={submit} className="max-w-4xl space-y-6 p-4">
                <PageHeader
                    title={
                        editing
                            ? locked
                                ? role.name
                                : `Edit ${role.name}`
                            : 'New Role'
                    }
                    description="Tick the permissions this role should have. Users with the role can do exactly these things and nothing else."
                />

                {locked && (
                    <Alert>
                        <Lock />
                        <AlertDescription>
                            The Administrator role always has every permission
                            and cannot be renamed or restricted. You can only
                            change its description.
                        </AlertDescription>
                    </Alert>
                )}

                {editing && role.users_count > 0 && !locked && (
                    <Alert>
                        <AlertDescription>
                            {role.users_count} user
                            {role.users_count === 1 ? ' has' : 's have'} this
                            role. Changes apply to them immediately.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-5 rounded-lg border p-5">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Role name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            disabled={locked}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            rows={2}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            placeholder="What is this role for?"
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </div>

                {!locked && (
                    <section className="space-y-4">
                        <div className="flex items-baseline justify-between">
                            <h2 className="font-medium">Permissions</h2>
                            <p className="text-sm text-muted-foreground">
                                {selected.size} of {total} selected
                            </p>
                        </div>
                        <InputError message={form.errors.permissions} />

                        <div className="grid gap-4 md:grid-cols-2">
                            {groups.map((group) => {
                                const grantable = group.permissions.filter(
                                    (p) => heldSet.has(p.id),
                                );
                                const chosen = group.permissions.filter((p) =>
                                    selected.has(p.id),
                                ).length;
                                const allOn =
                                    grantable.length > 0 &&
                                    grantable.every((p) => selected.has(p.id));
                                const state: boolean | 'indeterminate' = allOn
                                    ? true
                                    : chosen > 0
                                      ? 'indeterminate'
                                      : false;

                                return (
                                    <fieldset
                                        key={group.module}
                                        className="rounded-lg border"
                                    >
                                        <legend className="sr-only">
                                            {group.label}
                                        </legend>
                                        <div className="flex items-center gap-3 border-b bg-muted/40 px-4 py-2.5">
                                            <Checkbox
                                                id={`group-${group.module}`}
                                                checked={state}
                                                disabled={
                                                    grantable.length === 0
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleGroup(
                                                        group,
                                                        checked === true,
                                                    )
                                                }
                                                aria-label={`Select all ${group.label}`}
                                            />
                                            <Label
                                                htmlFor={`group-${group.module}`}
                                                className="flex-1 font-medium"
                                            >
                                                {group.label}
                                            </Label>
                                            <span className="text-xs text-muted-foreground">
                                                {chosen}/
                                                {group.permissions.length}
                                            </span>
                                        </div>
                                        <ul className="divide-y">
                                            {group.permissions.map(
                                                (permission) => {
                                                    const allowed = heldSet.has(
                                                        permission.id,
                                                    );

                                                    return (
                                                        <li
                                                            key={permission.id}
                                                            className="flex items-start gap-3 px-4 py-2.5"
                                                        >
                                                            <Checkbox
                                                                id={`perm-${permission.id}`}
                                                                className="mt-0.5"
                                                                checked={selected.has(
                                                                    permission.id,
                                                                )}
                                                                disabled={
                                                                    !allowed
                                                                }
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    toggle(
                                                                        permission.id,
                                                                        checked ===
                                                                            true,
                                                                    )
                                                                }
                                                            />
                                                            <Label
                                                                htmlFor={`perm-${permission.id}`}
                                                                className="grid flex-1 gap-0.5 font-normal"
                                                            >
                                                                <span className="font-medium">
                                                                    {humanise(
                                                                        permission.action,
                                                                    )}
                                                                </span>
                                                                <span className="text-xs text-muted-foreground">
                                                                    {allowed
                                                                        ? permission.description
                                                                        : 'You do not hold this permission, so you cannot grant it.'}
                                                                </span>
                                                            </Label>
                                                        </li>
                                                    );
                                                },
                                            )}
                                        </ul>
                                    </fieldset>
                                );
                            })}
                        </div>
                    </section>
                )}

                <div className="flex items-center gap-3 border-t pt-5">
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save Role' : 'Create Role'}
                    </Button>
                    <Button asChild variant="ghost">
                        <Link href={index()}>Cancel</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

RoleForm.layout = { breadcrumbs: [{ title: 'Roles', href: index() }] };
