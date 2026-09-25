import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EventsNav } from '@/components/events-nav';
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
import { index } from '@/routes/events';
import {
    destroy,
    index as venuesIndex,
    store,
    update,
} from '@/routes/events/venues';

type Venue = { id: number; name: string; events: number };

export default function Venues({ venues }: { venues: Venue[] }) {
    // `null` closes the dialog; `'new'` adds a venue.
    const [editing, setEditing] = useState<Venue | 'new' | null>(null);

    return (
        <>
            <Head title="Venues" />

            <div className="max-w-5xl space-y-5 p-4">
                <PageHeader
                    title="Venues"
                    description="The places events are held, offered on the event form. The form also lets someone type a venue that is not listed. Renaming or removing one never changes an event already saved."
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> Add Venue
                        </Button>
                    }
                />
                <EventsNav />

                {venues.length === 0 ? (
                    <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                        No venues yet. Add the first one.
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {venues.map((v) => (
                            <li
                                key={v.id}
                                className="flex items-center justify-between gap-3 p-4"
                            >
                                <span className="font-medium">{v.name}</span>
                                <span className="flex items-center gap-3 text-sm text-muted-foreground">
                                    {v.events} event{v.events === 1 ? '' : 's'}
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Edit ${v.name}`}
                                        onClick={() => setEditing(v)}
                                    >
                                        <Pencil />
                                    </Button>
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {editing && (
                <VenueDialog
                    venue={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function VenueDialog({
    venue,
    onClose,
}: {
    venue: Venue | null;
    onClose: () => void;
}) {
    const [confirming, setConfirming] = useState(false);
    const form = useForm({ name: venue?.name ?? '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (venue) {
            form.put(update(venue.id).url, options);
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
                            {venue ? venue.name : 'Add Venue'}
                        </DialogTitle>
                        <DialogDescription>
                            {venue && venue.events > 0
                                ? `${venue.events} event(s) were held here; they keep the name they were saved with.`
                                : 'It will be offered on the event form straight away.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="venue-name">Name</Label>
                        <Input
                            id="venue-name"
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
                        {venue ? (
                            confirming ? (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm">
                                        Remove {venue.name}?
                                    </span>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(
                                                destroy(venue.id).url,
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

Venues.layout = {
    breadcrumbs: [
        { title: 'Events', href: index() },
        { title: 'Venues', href: venuesIndex() },
    ],
};
