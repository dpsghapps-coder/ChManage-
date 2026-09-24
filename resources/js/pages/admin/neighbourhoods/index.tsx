import { Head, Link, router, useForm } from '@inertiajs/react';
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
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { edit as churchSettings } from '@/routes/admin/church';
import {
    destroy as destroyCity,
    store as storeCity,
    update as updateCity,
} from '@/routes/admin/cities';
import {
    destroy as destroyNeighbourhood,
    store as storeNeighbourhoods,
    update as updateNeighbourhood,
} from '@/routes/admin/cities/neighbourhoods';
import { index } from '@/routes/admin/neighbourhoods';

type CitySummary = {
    id: number;
    name: string;
    region: string | null;
    count: number;
};

type Neighbourhood = { id: number; name: string };

type Props = {
    cities: CitySummary[];
    city: {
        id: number;
        name: string;
        region: string | null;
        neighbourhoods: Neighbourhood[];
    } | null;
    churchCity: string;
    regions: string[];
};

export default function NeighbourhoodsIndex({
    cities,
    city,
    churchCity,
    regions,
}: Props) {
    const [query, setQuery] = useState('');
    // `null` closes the dialog; `'new'` adds a city.
    const [editingCity, setEditingCity] = useState<
        Props['city'] | 'new' | null
    >(null);
    const [removingCity, setRemovingCity] = useState(false);
    const [editing, setEditing] = useState<Neighbourhood | null>(null);

    const isChurchCity =
        !!city &&
        churchCity.trim().toLowerCase() === city.name.trim().toLowerCase();
    const q = query.trim().toLowerCase();
    const shown = (city?.neighbourhoods ?? []).filter(
        (n) => !q || n.name.toLowerCase().includes(q),
    );

    return (
        <>
            <Head title="Neighbourhoods" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Neighbourhoods"
                    description="The neighbourhoods the member form suggests for Residence. It uses the list of the city set in Church Settings, plus the other towns of that city's region."
                    actions={
                        <Button onClick={() => setEditingCity('new')}>
                            <Plus /> Add City
                        </Button>
                    }
                />

                {cities.length === 0 || !city ? (
                    <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                        No cities yet. Add one, then paste its neighbourhoods.
                    </p>
                ) : (
                    <>
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="grid w-full gap-2 sm:w-72">
                                <Label htmlFor="city">City</Label>
                                <NativeSelect
                                    id="city"
                                    value={city.id}
                                    onChange={(e) =>
                                        router.get(index().url, {
                                            city: e.target.value,
                                        })
                                    }
                                >
                                    {cities.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name} ({c.count})
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                        </div>

                        <section className="space-y-4 rounded-lg border p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="space-y-1">
                                    <h2 className="flex flex-wrap items-center gap-2 text-lg font-medium">
                                        {city.name}
                                        {isChurchCity && (
                                            <Badge>Church city</Badge>
                                        )}
                                    </h2>
                                    <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                        <MapPin className="size-3.5" />
                                        {city.region
                                            ? `${city.region} Region`
                                            : 'Region not set'}{' '}
                                        · {city.neighbourhoods.length}{' '}
                                        neighbourhoods
                                    </p>
                                    {!isChurchCity && (
                                        <p className="text-xs text-muted-foreground">
                                            Residence is using{' '}
                                            {churchCity ? (
                                                <strong>{churchCity}</strong>
                                            ) : (
                                                'no city'
                                            )}
                                            . Change it in{' '}
                                            <Link
                                                href={churchSettings()}
                                                className="underline underline-offset-4"
                                            >
                                                Church Settings
                                            </Link>
                                            .
                                        </p>
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setEditingCity(city)}
                                    >
                                        <Pencil /> Edit
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive hover:text-destructive"
                                        onClick={() => setRemovingCity(true)}
                                    >
                                        <Trash2 /> Remove
                                    </Button>
                                </div>
                            </div>

                            <AddNeighbourhoods cityId={city.id} />

                            {city.neighbourhoods.length > 0 && (
                                <div className="relative max-w-sm">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={query}
                                        onChange={(e) =>
                                            setQuery(e.target.value)
                                        }
                                        placeholder={`Search ${city.name}'s neighbourhoods`}
                                        className="pl-9"
                                        aria-label="Search neighbourhoods"
                                    />
                                </div>
                            )}

                            {city.neighbourhoods.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No neighbourhoods yet. Paste them above.
                                </p>
                            ) : shown.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    None match “{query}”.
                                </p>
                            ) : (
                                <ul className="flex flex-wrap gap-1.5">
                                    {shown.map((n) => (
                                        <li key={n.id}>
                                            <button
                                                type="button"
                                                onClick={() => setEditing(n)}
                                                title={`Edit ${n.name}`}
                                                className="rounded-md border bg-muted/40 px-2 py-0.5 text-xs hover:border-primary/60"
                                            >
                                                {n.name}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </>
                )}
            </div>

            {editingCity !== null && (
                <CityDialog
                    city={editingCity === 'new' ? null : editingCity}
                    regions={regions}
                    onClose={() => setEditingCity(null)}
                />
            )}

            {city && (
                <Dialog open={removingCity} onOpenChange={setRemovingCity}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Remove {city.name}?</DialogTitle>
                            <DialogDescription>
                                Its {city.neighbourhoods.length} neighbourhoods
                                are removed too. Members&apos; recorded
                                residences are not changed.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setRemovingCity(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    router.delete(destroyCity(city.id).url, {
                                        onFinish: () => setRemovingCity(false),
                                    })
                                }
                            >
                                Remove
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}

            {city && editing && (
                <NeighbourhoodDialog
                    cityId={city.id}
                    cityName={city.name}
                    neighbourhood={editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

/** Paste a list: one per line, or separated by commas. */
function AddNeighbourhoods({ cityId }: { cityId: number }) {
    const form = useForm({ names: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeNeighbourhoods(cityId).url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-2">
            <Label htmlFor="names">Add neighbourhoods</Label>
            <Textarea
                id="names"
                value={form.data.names}
                onChange={(e) => form.setData('names', e.target.value)}
                placeholder={
                    'One per line, or separated by commas:\nAdum\nAsafo\nBantama'
                }
                rows={3}
            />
            <InputError message={form.errors.names} />
            <div>
                <Button
                    type="submit"
                    size="sm"
                    disabled={form.processing || !form.data.names.trim()}
                >
                    <Plus /> Add
                </Button>
            </div>
        </form>
    );
}

function CityDialog({
    city,
    regions,
    onClose,
}: {
    city: Props['city'];
    regions: string[];
    onClose: () => void;
}) {
    const form = useForm({
        name: city?.name ?? '',
        region: city?.region ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: onClose };

        if (city) {
            form.put(updateCity(city.id).url, options);
        } else {
            form.post(storeCity().url, options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {city ? `Edit ${city.name}` : 'Add City'}
                        </DialogTitle>
                        <DialogDescription>
                            Residence also suggests the other towns of the
                            city&apos;s region. Leave the region blank to look
                            it up from the Ghana town list.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="city-name">Name</Label>
                        <Input
                            id="city-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. Kumasi"
                            maxLength={150}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="city-region">Region</Label>
                        <NativeSelect
                            id="city-region"
                            value={form.data.region}
                            onChange={(e) =>
                                form.setData('region', e.target.value)
                            }
                        >
                            <option value="">Look it up</option>
                            {regions.map((r) => (
                                <option key={r} value={r}>
                                    {r}
                                </option>
                            ))}
                        </NativeSelect>
                        <InputError message={form.errors.region} />
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

function NeighbourhoodDialog({
    cityId,
    cityName,
    neighbourhood,
    onClose,
}: {
    cityId: number;
    cityName: string;
    neighbourhood: Neighbourhood;
    onClose: () => void;
}) {
    const [confirming, setConfirming] = useState(false);
    const form = useForm({ name: neighbourhood.name });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(updateNeighbourhood([cityId, neighbourhood.id]).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{neighbourhood.name}</DialogTitle>
                        <DialogDescription>
                            In {cityName}. Changes affect suggestions only;
                            members&apos; recorded residences keep what they
                            say.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="neighbourhood-name">Name</Label>
                        <Input
                            id="neighbourhood-name"
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
                        {confirming ? (
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm">
                                    Remove {neighbourhood.name}?
                                </span>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(
                                            destroyNeighbourhood([
                                                cityId,
                                                neighbourhood.id,
                                            ]).url,
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

NeighbourhoodsIndex.layout = {
    breadcrumbs: [{ title: 'Neighbourhoods', href: index() }],
};
