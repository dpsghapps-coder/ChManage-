import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, index, store, update } from '@/routes/admin/service-groups';

type Group = {
    id: number;
    name: string;
    short_name: string | null;
    members: number;
};

export default function ServiceGroupsIndex({ groups }: { groups: Group[] }) {
    // `null` closes the dialog; `'new'` adds a group.
    const [editing, setEditing] = useState<Group | 'new' | null>(null);
    const [removing, setRemoving] = useState<Group | null>(null);

    return (
        <>
            <Head title="Service Groups" />

            <div className="max-w-3xl space-y-6 p-4">
                <PageHeader
                    title="Service Groups"
                    description="The groups a member can belong to, offered on the Church step of the member form. Renaming a group changes it on every member in it."
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> Add Group
                        </Button>
                    }
                />

                {groups.length === 0 ? (
                    <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                        No service groups yet.
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {groups.map((group) => (
                            <li
                                key={group.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium">
                                        {group.name}
                                        {group.short_name && (
                                            <span className="font-normal text-muted-foreground">
                                                {' '}
                                                ({group.short_name})
                                            </span>
                                        )}
                                    </p>
                                    <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                        <Users className="size-3.5" />
                                        {group.members.toLocaleString()}{' '}
                                        {group.members === 1
                                            ? 'member'
                                            : 'members'}
                                    </p>
                                </div>
                                <div className="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setEditing(group)}
                                    >
                                        <Pencil /> Edit
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive hover:text-destructive"
                                        onClick={() => setRemoving(group)}
                                    >
                                        <Trash2 /> Remove
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {editing !== null && (
                <GroupDialog
                    group={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                />
            )}

            <Dialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove {removing?.name}?</DialogTitle>
                        <DialogDescription>
                            {removing && removing.members > 0
                                ? `${removing.members} member(s) still belong to it. Move them to another group on their records first; a group with members cannot be removed.`
                                : 'It is no longer offered on the member form.'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRemoving(null)}
                        >
                            {removing && removing.members > 0
                                ? 'Close'
                                : 'Cancel'}
                        </Button>
                        {removing && removing.members === 0 && (
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    router.delete(destroy(removing.id).url, {
                                        preserveScroll: true,
                                        onFinish: () => setRemoving(null),
                                    })
                                }
                            >
                                Remove
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function GroupDialog({
    group,
    onClose,
}: {
    group: Group | null;
    onClose: () => void;
}) {
    const form = useForm({
        name: group?.name ?? '',
        short_name: group?.short_name ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (group) {
            form.put(update(group.id).url, options);
        } else {
            form.post(store().url, options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {group ? `Edit ${group.name}` : 'Add Service Group'}
                        </DialogTitle>
                        <DialogDescription>
                            {group && group.members > 0
                                ? `A new name shows on all ${group.members} member(s) in it.`
                                : 'It will be offered on the member form straight away.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="group-name">Name</Label>
                        <Input
                            id="group-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. Ushering Team"
                            maxLength={150}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="group-short-name">Short name</Label>
                        <Input
                            id="group-short-name"
                            value={form.data.short_name}
                            onChange={(e) =>
                                form.setData('short_name', e.target.value)
                            }
                            placeholder="Optional, e.g. BSPG"
                            maxLength={50}
                        />
                        <InputError message={form.errors.short_name} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

ServiceGroupsIndex.layout = {
    breadcrumbs: [{ title: 'Service Groups', href: index() }],
};
