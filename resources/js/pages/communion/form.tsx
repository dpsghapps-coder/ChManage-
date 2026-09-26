import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/communion';

type Service = {
    id: number;
    title: string;
    held_on: string;
    venue: string | null;
    status: string;
    note: string | null;
} | null;

export default function CommunionForm({
    service,
    statuses,
}: {
    service: Service;
    statuses: Record<string, string>;
}) {
    const form = useForm({
        title: service?.title ?? '',
        held_on: service?.held_on ?? '',
        venue: service?.venue ?? '',
        status: service?.status ?? 'scheduled',
        note: service?.note ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (service) {
            form.put(update(service.id).url);
        } else {
            form.post(store().url);
        }
    };

    return (
        <>
            <Head
                title={
                    service ? 'Edit communion service' : 'New communion service'
                }
            />

            <form onSubmit={submit} className="max-w-2xl space-y-5 p-4">
                <PageHeader
                    title={
                        service
                            ? 'Edit communion service'
                            : 'New communion service'
                    }
                />

                <div className="grid gap-2">
                    <Label htmlFor="title">Name *</Label>
                    <Input
                        id="title"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        placeholder="Harvest Communion"
                        maxLength={150}
                        required
                    />
                    <InputError message={form.errors.title} />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="held_on">Date *</Label>
                        <Input
                            id="held_on"
                            type="date"
                            value={form.data.held_on}
                            onChange={(e) =>
                                form.setData('held_on', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.held_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <NativeSelect
                            id="status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {Object.entries(statuses).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </NativeSelect>
                        <InputError message={form.errors.status} />
                    </div>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="venue">Venue</Label>
                    <Input
                        id="venue"
                        value={form.data.venue}
                        onChange={(e) => form.setData('venue', e.target.value)}
                        placeholder="Main Chapel"
                        maxLength={150}
                    />
                    <InputError message={form.errors.venue} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="note">Note</Label>
                    <Textarea
                        id="note"
                        rows={3}
                        value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)}
                        maxLength={2000}
                    />
                    <InputError message={form.errors.note} />
                </div>

                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Save
                    </Button>
                    <Button asChild variant="ghost">
                        <Link href={service ? show(service.id) : index()}>
                            Cancel
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

CommunionForm.layout = {
    breadcrumbs: [{ title: 'Communion', href: index() }],
};
