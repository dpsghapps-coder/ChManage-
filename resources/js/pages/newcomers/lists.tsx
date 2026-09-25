import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { NewcomersNav } from '@/components/newcomers-nav';
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
import { index } from '@/routes/newcomers';
import {
    destroy,
    index as listsIndex,
    store,
    update,
} from '@/routes/newcomers/lists';

type Option = { id: number; name: string };

type Props = {
    kinds: Record<string, string>;
    options: Record<string, Option[]>;
};

export default function Lists({ kinds, options }: Props) {
    // `option` null adds to the list `kind`; otherwise it edits that choice.
    const [editing, setEditing] = useState<{
        kind: string;
        option: Option | null;
    } | null>(null);

    return (
        <>
            <Head title="Form lists" />

            <div className="max-w-5xl space-y-5 p-4">
                <PageHeader
                    title="Form lists"
                    description="The choices offered on the newcomer form. The form also lets someone type anything not listed. Removing or renaming a choice never changes a record already saved."
                />
                <NewcomersNav />

                {Object.entries(kinds).map(([kind, label]) => (
                    <section
                        key={kind}
                        className="space-y-3 rounded-lg border p-5"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2 className="font-medium">
                                {label}{' '}
                                <span className="text-sm font-normal text-muted-foreground">
                                    ({options[kind]?.length ?? 0})
                                </span>
                            </h2>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    setEditing({ kind, option: null })
                                }
                            >
                                <Plus /> Add
                            </Button>
                        </div>
                        {(options[kind] ?? []).length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing listed yet.
                            </p>
                        ) : (
                            <ul className="flex flex-wrap gap-1.5">
                                {options[kind].map((option) => (
                                    <li key={option.id}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setEditing({ kind, option })
                                            }
                                            className="inline-flex items-center gap-1 rounded-md border bg-muted/40 px-2 py-0.5 text-sm hover:border-primary/60"
                                        >
                                            {option.name}
                                            <Pencil className="size-3 text-muted-foreground" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                ))}
            </div>

            {editing && (
                <OptionDialog
                    label={kinds[editing.kind]}
                    kind={editing.kind}
                    option={editing.option}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function OptionDialog({
    label,
    kind,
    option,
    onClose,
}: {
    label: string;
    kind: string;
    option: Option | null;
    onClose: () => void;
}) {
    const [confirming, setConfirming] = useState(false);
    const form = useForm({ kind, name: option?.name ?? '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (option) {
            form.put(update(option.id).url, options);
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
                            {option ? option.name : `Add to ${label}`}
                        </DialogTitle>
                        <DialogDescription>{label}</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="option-name">Name</Label>
                        <Input
                            id="option-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            maxLength={150}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <DialogFooter className="gap-2 sm:justify-between">
                        {option ? (
                            confirming ? (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm">
                                        Remove {option.name}?
                                    </span>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(
                                                destroy(option.id).url,
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

Lists.layout = {
    breadcrumbs: [
        { title: 'Newcomers', href: index() },
        { title: 'Form lists', href: listsIndex() },
    ],
};
