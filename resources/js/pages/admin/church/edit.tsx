import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/admin/church';

type Settings = {
    church_name: string;
    presbytery: string;
    district: string;
    congregation: string;
};

const fields: {
    name: keyof Settings;
    label: string;
    hint: string;
    placeholder: string;
}[] = [
    {
        name: 'church_name',
        label: 'Church Name',
        hint: 'The full name of the church.',
        placeholder: 'Presbyterian Church of Ghana',
    },
    {
        name: 'presbytery',
        label: 'Presbytery',
        hint: 'The presbytery this congregation belongs to.',
        placeholder: 'Accra West',
    },
    {
        name: 'district',
        label: 'District',
        hint: 'The district within the presbytery.',
        placeholder: 'Mamprobi',
    },
    {
        name: 'congregation',
        label: 'Congregation',
        hint: 'The name of this congregation.',
        placeholder: 'Ebenezer Congregation, Mamprobi',
    },
];

export default function ChurchSettings({ settings }: { settings: Settings }) {
    const form = useForm<Settings>({ ...settings });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update().url, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Church Settings" />

            <form onSubmit={submit} className="max-w-2xl space-y-6 p-4">
                <PageHeader
                    title="Church Settings"
                    description="Where this congregation sits in the church. These details identify it on reports and printed records."
                />

                <section className="grid gap-5 rounded-lg border p-5">
                    {fields.map((field) => (
                        <div key={field.name} className="grid gap-2">
                            <Label htmlFor={field.name}>{field.label}</Label>
                            <Input
                                id={field.name}
                                value={form.data[field.name]}
                                onChange={(e) =>
                                    form.setData(field.name, e.target.value)
                                }
                                placeholder={field.placeholder}
                                maxLength={150}
                                required
                            />
                            <p className="text-xs text-muted-foreground">
                                {field.hint}
                            </p>
                            <InputError message={form.errors[field.name]} />
                        </div>
                    ))}
                </section>

                <Button type="submit" disabled={form.processing}>
                    Save Changes
                </Button>
            </form>
        </>
    );
}

ChurchSettings.layout = {
    breadcrumbs: [{ title: 'Church Settings', href: edit() }],
};
