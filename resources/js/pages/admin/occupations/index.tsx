import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
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
import { destroy, index, store, update } from '@/routes/admin/occupations';
import { update as renameCategory } from '@/routes/admin/occupations/categories';

type Occupation = { id: number; name: string; members: number };

type Group = { category: string; options: Occupation[] };

type Props = { groups: Group[]; uncategorised: string };

export default function OccupationsIndex({ groups, uncategorised }: Props) {
    const [query, setQuery] = useState('');
    // `null` closes the dialog; `'new'` adds an occupation.
    const [editing, setEditing] = useState<{
        occupation: Occupation | null;
        category: string;
    } | null>(null);
    const [renaming, setRenaming] = useState<string | null>(null);

    const categories = groups
        .map((g) => g.category)
        .filter((c) => c !== uncategorised);
    const total = groups.reduce((sum, g) => sum + g.options.length, 0);
    const q = query.trim().toLowerCase();
    const shown = groups
        .map((g) => ({
            ...g,
            options: g.options.filter(
                (o) =>
                    !q ||
                    o.name.toLowerCase().includes(q) ||
                    g.category.toLowerCase().includes(q),
            ),
        }))
        .filter((g) => g.options.length > 0);

    return (
        <>
            <Head title="Occupations" />

            <div className="max-w-5xl space-y-6 p-4">
                <PageHeader
                    title="Occupations"
                    description={`The ${total} occupations offered on the member form, by category. A rename shows on every member who has it; tap an occupation to edit it.`}
                    actions={
                        <Button
                            onClick={() =>
                                setEditing({ occupation: null, category: '' })
                            }
                        >
                            <Plus /> Add Occupation
                        </Button>
                    }
                />

                <div className="relative max-w-md">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search occupations or categories"
                        className="pl-9"
                        aria-label="Search occupations"
                    />
                </div>

                {shown.length === 0 ? (
                    <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                        Nothing matches “{query}”.
                    </p>
                ) : (
                    shown.map((group) => (
                        <section
                            key={group.category}
                            className="space-y-3 rounded-lg border p-5"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 className="font-medium">
                                    {group.category}{' '}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        ({group.options.length})
                                    </span>
                                </h2>
                                <div className="flex gap-1">
                                    {group.category !== uncategorised && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setRenaming(group.category)
                                            }
                                        >
                                            <Pencil /> Rename category
                                        </Button>
                                    )}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            setEditing({
                                                occupation: null,
                                                category:
                                                    group.category ===
                                                    uncategorised
                                                        ? ''
                                                        : group.category,
                                            })
                                        }
                                    >
                                        <Plus /> Add here
                                    </Button>
                                </div>
                            </div>
                            <ul className="flex flex-wrap gap-1.5">
                                {group.options.map((o) => (
                                    <li key={o.id}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setEditing({
                                                    occupation: o,
                                                    category:
                                                        group.category ===
                                                        uncategorised
                                                            ? ''
                                                            : group.category,
                                                })
                                            }
                                            title={`${o.name} · ${group.category} · ${o.members} member(s)`}
                                            className="rounded-md border bg-muted/40 px-2 py-0.5 text-xs hover:border-primary/60"
                                        >
                                            {o.name}
                                            {o.members > 0 && (
                                                <span className="ml-1 text-muted-foreground">
                                                    ({o.members})
                                                </span>
                                            )}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))
                )}
            </div>

            <datalist id="occupation-categories">
                {categories.map((c) => (
                    <option key={c} value={c} />
                ))}
            </datalist>

            {editing && (
                <OccupationDialog
                    occupation={editing.occupation}
                    category={editing.category}
                    onClose={() => setEditing(null)}
                />
            )}

            {renaming && (
                <RenameCategoryDialog
                    category={renaming}
                    onClose={() => setRenaming(null)}
                />
            )}
        </>
    );
}

function OccupationDialog({
    occupation,
    category,
    onClose,
}: {
    occupation: Occupation | null;
    category: string;
    onClose: () => void;
}) {
    const [confirming, setConfirming] = useState(false);
    const form = useForm({ name: occupation?.name ?? '', category });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (occupation) {
            form.put(update(occupation.id).url, options);
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
                            {occupation ? occupation.name : 'Add Occupation'}
                        </DialogTitle>
                        <DialogDescription>
                            {occupation && occupation.members > 0
                                ? `${occupation.members} member(s) have this occupation; a new name shows on all of them.`
                                : 'It will be offered on the member form straight away.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="occupation-name">Name</Label>
                        <Input
                            id="occupation-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. Pharmacist"
                            maxLength={150}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="occupation-category">Category</Label>
                        <Input
                            id="occupation-category"
                            value={form.data.category}
                            onChange={(e) =>
                                form.setData('category', e.target.value)
                            }
                            list="occupation-categories"
                            autoComplete="off"
                            placeholder="Pick one, or type a new category"
                            maxLength={100}
                            required
                        />
                        <InputError message={form.errors.category} />
                    </div>

                    <DialogFooter className="gap-2 sm:justify-between">
                        {occupation ? (
                            confirming ? (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm">
                                        Remove {occupation.name}?
                                    </span>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(
                                                destroy(occupation.id).url,
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
                                    disabled={occupation.members > 0}
                                    title={
                                        occupation.members > 0
                                            ? 'Members have this occupation, so it cannot be removed.'
                                            : undefined
                                    }
                                    onClick={() => setConfirming(true)}
                                >
                                    <Trash2 /> Remove
                                </Button>
                            )
                        ) : (
                            <span />
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

function RenameCategoryDialog({
    category,
    onClose,
}: {
    category: string;
    onClose: () => void;
}) {
    const form = useForm({ from: category, to: category });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(renameCategory().url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Rename {category}</DialogTitle>
                        <DialogDescription>
                            Every occupation in it moves to the new name. Use an
                            existing category&apos;s name to merge the two.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="category-name">New name</Label>
                        <Input
                            id="category-name"
                            value={form.data.to}
                            onChange={(e) => form.setData('to', e.target.value)}
                            list="occupation-categories"
                            autoComplete="off"
                            maxLength={100}
                            required
                        />
                        <InputError message={form.errors.to} />
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

OccupationsIndex.layout = {
    breadcrumbs: [{ title: 'Occupations', href: index() }],
};
