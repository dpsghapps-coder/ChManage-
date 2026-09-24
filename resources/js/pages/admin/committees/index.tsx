import { Head, router, useForm } from '@inertiajs/react';
import { FileText, Pencil, Plus, Trash2 } from 'lucide-react';
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
import { destroy, index, store, update } from '@/routes/admin/committees';

type Committee = { id: number; name: string; records: number };

export default function CommitteesIndex({
    committees,
}: {
    committees: Committee[];
}) {
    // `null` closes the dialog; `'new'` adds a committee.
    const [editing, setEditing] = useState<Committee | 'new' | null>(null);
    const [removing, setRemoving] = useState<Committee | null>(null);

    return (
        <>
            <Head title="Committees" />

            <div className="max-w-3xl space-y-6 p-4">
                <PageHeader
                    title="Committees"
                    description="The committees offered for a committee service record on the member form. Anything not listed can still be typed under Other."
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> Add Committee
                        </Button>
                    }
                />

                {committees.length === 0 ? (
                    <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                        No committees yet.
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {committees.map((committee) => (
                            <li
                                key={committee.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium">
                                        {committee.name}
                                    </p>
                                    <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                        <FileText className="size-3.5" />
                                        {committee.records}{' '}
                                        {committee.records === 1
                                            ? 'service record'
                                            : 'service records'}
                                    </p>
                                </div>
                                <div className="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setEditing(committee)}
                                    >
                                        <Pencil /> Edit
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive hover:text-destructive"
                                        onClick={() => setRemoving(committee)}
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
                <CommitteeDialog
                    committee={editing === 'new' ? null : editing}
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
                            It is no longer offered on the member form.
                            {removing && removing.records > 0
                                ? ` The ${removing.records} service record(s) that name it keep the name.`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRemoving(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                removing &&
                                router.delete(destroy(removing.id).url, {
                                    preserveScroll: true,
                                    onFinish: () => setRemoving(null),
                                })
                            }
                        >
                            Remove
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function CommitteeDialog({
    committee,
    onClose,
}: {
    committee: Committee | null;
    onClose: () => void;
}) {
    const form = useForm({ name: committee?.name ?? '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (committee) {
            form.put(update(committee.id).url, options);
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
                            {committee
                                ? `Edit ${committee.name}`
                                : 'Add Committee'}
                        </DialogTitle>
                        <DialogDescription>
                            {committee && committee.records > 0
                                ? `A new name is carried over to the ${committee.records} service record(s) that name it.`
                                : 'It will be offered on the member form straight away.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="committee-name">Name</Label>
                        <Input
                            id="committee-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. Committee on Finance"
                            maxLength={150}
                            required
                        />
                        <InputError message={form.errors.name} />
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

CommitteesIndex.layout = {
    breadcrumbs: [{ title: 'Committees', href: index() }],
};
