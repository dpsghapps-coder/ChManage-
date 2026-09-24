import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
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
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/admin/service-positions';

type Position = { id: number; name: string };

type ServiceType = {
    type: string;
    label: string;
    positions: Position[];
};

export default function ServicePositionsIndex({
    types,
}: {
    types: ServiceType[];
}) {
    const [editing, setEditing] = useState<{
        type: ServiceType;
        position: Position;
    } | null>(null);

    return (
        <>
            <Head title="Service Positions" />

            <div className="max-w-4xl space-y-6 p-4">
                <PageHeader
                    title="Service Positions"
                    description="The positions offered on the Service step of the member form, for each type of service. Anything not listed can still be typed under Other. Tap a position to rename or remove it."
                />

                {types.map((type) => (
                    <section
                        key={type.type}
                        className="space-y-3 rounded-lg border p-5"
                    >
                        <h2 className="font-medium">
                            {type.label}{' '}
                            <span className="text-sm font-normal text-muted-foreground">
                                ({type.positions.length})
                            </span>
                        </h2>

                        {type.positions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No positions yet.
                            </p>
                        ) : (
                            <ul className="flex flex-wrap gap-1.5">
                                {type.positions.map((position) => (
                                    <li key={position.id}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setEditing({ type, position })
                                            }
                                            title={`Edit ${position.name}`}
                                            className="rounded-md border bg-muted/40 px-2 py-0.5 text-xs hover:border-primary/60"
                                        >
                                            {position.name}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <AddPosition type={type} />
                    </section>
                ))}
            </div>

            {editing && (
                <PositionDialog
                    type={editing.type}
                    position={editing.position}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function AddPosition({ type }: { type: ServiceType }) {
    const form = useForm({ type: type.type, name: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => form.reset('name'),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-1">
            <div className="flex max-w-md gap-2">
                <Input
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder={`Add a ${type.label.toLowerCase()} position`}
                    aria-label={`New ${type.label} position`}
                    maxLength={100}
                    className="h-8 text-sm"
                    required
                />
                <Button
                    type="submit"
                    size="sm"
                    variant="secondary"
                    disabled={form.processing || !form.data.name.trim()}
                >
                    <Plus /> Add
                </Button>
            </div>
            <InputError message={form.errors.name} />
        </form>
    );
}

function PositionDialog({
    type,
    position,
    onClose,
}: {
    type: ServiceType;
    position: Position;
    onClose: () => void;
}) {
    const [confirming, setConfirming] = useState(false);
    const form = useForm({ name: position.name });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update(position.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{position.name}</DialogTitle>
                        <DialogDescription>
                            A {type.label} position. Changes affect what the
                            form offers; members&apos; service records keep what
                            they say.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="position-name">Name</Label>
                        <Input
                            id="position-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            maxLength={100}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <DialogFooter className="gap-2 sm:justify-between">
                        {confirming ? (
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm">
                                    Remove {position.name}?
                                </span>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(
                                            destroy(position.id).url,
                                            {
                                                preserveScroll: true,
                                                onSuccess: onClose,
                                            },
                                        )
                                    }
                                >
                                    Yes, remove
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setConfirming(false)}
                                >
                                    Keep
                                </Button>
                            </div>
                        ) : (
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                onClick={() => setConfirming(true)}
                            >
                                <Trash2 /> Remove
                            </Button>
                        )}
                        <div className="flex gap-2">
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
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

ServicePositionsIndex.layout = {
    breadcrumbs: [{ title: 'Service Positions', href: index() }],
};
