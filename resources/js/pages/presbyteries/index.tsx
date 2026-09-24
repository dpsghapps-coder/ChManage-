import { Head, router, useForm } from '@inertiajs/react';
import { MapPin, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
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
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import {
    index,
    store as storePresbytery,
    update as updatePresbytery,
} from '@/routes/presbyteries';
import {
    destroy as destroyDistrict,
    store as storeDistrict,
    update as updateDistrict,
} from '@/routes/presbyteries/districts';

type District = { id: number; name: string; note: string | null };

type Presbytery = {
    id: number;
    name: string;
    title: string;
    short_name: string | null;
    headquarters: string | null;
    coverage: string | null;
    facts: { label: string; value: string }[];
    districts: District[];
};

type Props = {
    presbyteries: Presbytery[];
    note: string | null;
    church: { presbytery: string | null; district: string | null };
};

// "Ga", "ga" and "Ga Presbytery" all name the same presbytery.
const normalise = (name: string | null) =>
    (name ?? '')
        .trim()
        .toLowerCase()
        .replace(/\s+(presbytery|district)$/, '');

export default function PresbyteriesIndex({
    presbyteries,
    note,
    church,
}: Props) {
    const { can } = usePermission();
    const editable = can('settings.manage');
    const [query, setQuery] = useState('');
    // `null` closes the dialog; `'new'` adds a presbytery.
    const [editingPresbytery, setEditingPresbytery] = useState<
        Presbytery | 'new' | null
    >(null);
    const [editingDistrict, setEditingDistrict] = useState<{
        presbytery: Presbytery;
        district: District;
    } | null>(null);
    const q = query.trim().toLowerCase();

    const districtCount = presbyteries.reduce(
        (sum, p) => sum + p.districts.length,
        0,
    );

    // A presbytery matching the search shows all its districts; otherwise only the matching districts.
    const shown = presbyteries
        .map((p) => {
            if (!q) {
                return p;
            }

            const own = [p.title, p.short_name, p.headquarters, p.coverage]
                .filter(Boolean)
                .some((text) => text!.toLowerCase().includes(q));

            return own
                ? p
                : {
                      ...p,
                      districts: p.districts.filter((d) =>
                          `${d.name} ${d.note ?? ''}`.toLowerCase().includes(q),
                      ),
                  };
        })
        .filter((p) => !q || p.districts.length > 0);

    const isOurs = (p: Presbytery) =>
        !!church.presbytery &&
        [p.name, p.title, p.short_name].some(
            (n) => n && normalise(n) === normalise(church.presbytery),
        );

    return (
        <>
            <Head title="PCG Presbyteries" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="PCG Presbyteries"
                    description={`The ${presbyteries.length} presbyteries of the Presbyterian Church of Ghana and the ${districtCount} districts listed under them. Used as suggestions for Church Settings, baptism and confirmation, and staff stations.${editable ? ' Tap a district to rename or remove it.' : ''}`}
                    actions={
                        editable && (
                            <Button onClick={() => setEditingPresbytery('new')}>
                                <Plus /> Add Presbytery
                            </Button>
                        )
                    }
                />

                <div className="relative max-w-md">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search a presbytery, headquarters or district…"
                        className="pl-9"
                        aria-label="Search presbyteries and districts"
                    />
                </div>

                {shown.length === 0 ? (
                    <p className="rounded-lg border p-6 text-center text-sm text-muted-foreground">
                        No presbytery or district matches “{query}”.
                    </p>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {shown.map((p) => {
                            const ours = isOurs(p);
                            // The full record, even when the search has narrowed its districts.
                            const full =
                                presbyteries.find((x) => x.id === p.id) ?? p;

                            return (
                                <section
                                    key={p.id}
                                    className={cn(
                                        'space-y-3 rounded-lg border p-5',
                                        ours &&
                                            'border-primary/50 bg-primary/5',
                                    )}
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <h2 className="font-medium">
                                                {p.title}
                                                {p.short_name && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        ({p.short_name})
                                                    </span>
                                                )}
                                            </h2>
                                            {p.headquarters && (
                                                <p className="mt-0.5 flex items-center gap-1 text-sm text-muted-foreground">
                                                    <MapPin className="size-3.5" />
                                                    {p.headquarters}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {ours && (
                                                <Badge>Our presbytery</Badge>
                                            )}
                                            {editable && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setEditingPresbytery(
                                                            full,
                                                        )
                                                    }
                                                >
                                                    <Pencil /> Edit
                                                </Button>
                                            )}
                                        </div>
                                    </div>

                                    {p.coverage && (
                                        <p className="text-sm">{p.coverage}</p>
                                    )}

                                    {p.facts.map((fact) => (
                                        <p
                                            key={fact.label}
                                            className="text-xs text-muted-foreground"
                                        >
                                            <span className="font-medium">
                                                {fact.label}:
                                            </span>{' '}
                                            {fact.value}
                                        </p>
                                    ))}

                                    <ul className="flex flex-wrap gap-1.5">
                                        {p.districts.map((d) => {
                                            const ourDistrict =
                                                ours &&
                                                normalise(d.name) ===
                                                    normalise(church.district);
                                            const chip = cn(
                                                'rounded-md border px-2 py-0.5 text-xs',
                                                ourDistrict
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'bg-muted/40',
                                            );
                                            const label = (
                                                <>
                                                    {d.name}
                                                    {d.note && (
                                                        <span
                                                            className={
                                                                ourDistrict
                                                                    ? 'opacity-80'
                                                                    : 'text-muted-foreground'
                                                            }
                                                        >
                                                            {' '}
                                                            ({d.note})
                                                        </span>
                                                    )}
                                                </>
                                            );

                                            return (
                                                <li key={d.id}>
                                                    {editable ? (
                                                        <button
                                                            type="button"
                                                            className={cn(
                                                                chip,
                                                                'hover:border-primary/60',
                                                            )}
                                                            title={`Edit ${d.name}`}
                                                            onClick={() =>
                                                                setEditingDistrict(
                                                                    {
                                                                        presbytery:
                                                                            full,
                                                                        district:
                                                                            d,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            {label}
                                                        </button>
                                                    ) : (
                                                        <span
                                                            className={chip}
                                                            title={
                                                                d.note ??
                                                                undefined
                                                            }
                                                        >
                                                            {label}
                                                        </span>
                                                    )}
                                                </li>
                                            );
                                        })}
                                    </ul>

                                    {editable && <AddDistrict presbytery={p} />}
                                </section>
                            );
                        })}
                    </div>
                )}

                {note && (
                    <p className="max-w-3xl text-xs text-muted-foreground">
                        {note}
                    </p>
                )}
            </div>

            {editingPresbytery !== null && (
                <PresbyteryDialog
                    presbytery={
                        editingPresbytery === 'new' ? null : editingPresbytery
                    }
                    onClose={() => setEditingPresbytery(null)}
                />
            )}

            {editingDistrict && (
                <DistrictDialog
                    presbytery={editingDistrict.presbytery}
                    district={editingDistrict.district}
                    onClose={() => setEditingDistrict(null)}
                />
            )}
        </>
    );
}

/** A one-line "add a district" form under a presbytery's districts. */
function AddDistrict({ presbytery }: { presbytery: Presbytery }) {
    const form = useForm({ name: '', note: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeDistrict(presbytery.id).url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-1">
            <div className="flex gap-2">
                <Input
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder={`Add a district to ${presbytery.title}`}
                    aria-label={`New district in ${presbytery.title}`}
                    maxLength={150}
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

function PresbyteryDialog({
    presbytery,
    onClose,
}: {
    presbytery: Presbytery | null;
    onClose: () => void;
}) {
    const form = useForm({
        name: presbytery?.name ?? '',
        short_name: presbytery?.short_name ?? '',
        headquarters: presbytery?.headquarters ?? '',
        coverage: presbytery?.coverage ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (presbytery) {
            form.put(updatePresbytery(presbytery.id).url, options);
        } else {
            form.post(storePresbytery().url, options);
        }
    };

    const field = (
        name: 'name' | 'short_name' | 'headquarters',
        label: string,
        props: React.ComponentProps<'input'> = {},
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`presbytery-${name}`}>{label}</Label>
            <Input
                id={`presbytery-${name}`}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                {...props}
            />
            <InputError message={form.errors[name]} />
        </div>
    );

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {presbytery
                                ? `Edit ${presbytery.title}`
                                : 'Add Presbytery'}
                        </DialogTitle>
                        <DialogDescription>
                            {presbytery
                                ? 'Renaming changes the suggestions only; records that already name this presbytery keep the old name.'
                                : 'Add its districts after saving.'}
                        </DialogDescription>
                    </DialogHeader>

                    {field('name', 'Name', {
                        required: true,
                        maxLength: 150,
                        placeholder: 'e.g. Ga West',
                    })}
                    {field('short_name', 'Short name', {
                        maxLength: 30,
                        placeholder: 'e.g. PNAA',
                    })}
                    {field('headquarters', 'Headquarters', { maxLength: 150 })}
                    <div className="grid gap-2">
                        <Label htmlFor="presbytery-coverage">
                            Geographical coverage
                        </Label>
                        <Textarea
                            id="presbytery-coverage"
                            value={form.data.coverage}
                            onChange={(e) =>
                                form.setData('coverage', e.target.value)
                            }
                            maxLength={500}
                            rows={3}
                        />
                        <InputError message={form.errors.coverage} />
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

function DistrictDialog({
    presbytery,
    district,
    onClose,
}: {
    presbytery: Presbytery;
    district: District;
    onClose: () => void;
}) {
    const [confirming, setConfirming] = useState(false);
    const form = useForm({ name: district.name, note: district.note ?? '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(updateDistrict([presbytery.id, district.id]).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const remove = () =>
        router.delete(destroyDistrict([presbytery.id, district.id]).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{district.name} District</DialogTitle>
                        <DialogDescription>
                            In {presbytery.title}. Changes affect suggestions
                            only; existing records keep what they say.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="district-name">Name</Label>
                        <Input
                            id="district-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            maxLength={150}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="district-note">Note</Label>
                        <Input
                            id="district-note"
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                            maxLength={150}
                            placeholder="e.g. Head Station"
                        />
                        <InputError message={form.errors.note} />
                    </div>

                    <DialogFooter className="gap-2 sm:justify-between">
                        {confirming ? (
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm">
                                    Remove {district.name}?
                                </span>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    onClick={remove}
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

PresbyteriesIndex.layout = {
    breadcrumbs: [{ title: 'PCG Presbyteries', href: index() }],
};
