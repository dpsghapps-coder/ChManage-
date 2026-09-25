import { Head, Link, useForm } from '@inertiajs/react';
import { Landmark, MapPinned, Signpost } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    TownSuggestions,
    townTooltip,
    useGhanaTowns,
} from '@/components/town-suggestions';
import { edit, update } from '@/routes/admin/church';
import { index as neighbourhoodsIndex } from '@/routes/admin/neighbourhoods';
import { index as townsIndex } from '@/routes/admin/towns';
import { index as presbyteriesIndex } from '@/routes/presbyteries';

type Settings = {
    church_name: string;
    presbytery: string;
    district: string;
    congregation: string;
    city: string;
};

type Presbytery = {
    name: string;
    headquarters: string | null;
    districts: string[];
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
        placeholder: 'Ga West',
    },
    {
        name: 'district',
        label: 'District',
        hint: 'The district within the presbytery.',
        placeholder: 'Kaneshie',
    },
    {
        name: 'congregation',
        label: 'Congregation',
        hint: 'The name of this congregation.',
        placeholder: 'Ebenezer Congregation, Kaneshie',
    },
    {
        name: 'city',
        label: 'City',
        hint: "Where the congregation is. The member form suggests this city's neighbourhoods for Residence.",
        placeholder: 'Accra',
    },
];

/** Every field is required except City. */
const optional: (keyof Settings)[] = ['city'];

// "Ga", "ga" and "Ga Presbytery" all name the same presbytery.
const normalise = (name: string) =>
    name
        .trim()
        .toLowerCase()
        .replace(/\s+presbytery$/, '');
const same = (a: string, b: string) => normalise(a) === normalise(b);

export default function ChurchSettings({
    settings,
    presbyteries,
    cities,
}: {
    settings: Settings;
    presbyteries: Presbytery[];
    cities: string[];
}) {
    const form = useForm<Settings>({ ...settings });
    // Hover text for City: the town's district and region.
    const towns = useGhanaTowns();

    // Once a listed presbytery is chosen, only its districts are suggested.
    const chosen = presbyteries.find((p) => same(p.name, form.data.presbytery));
    const suggestions: Partial<Record<keyof Settings, string[]>> = {
        presbytery: presbyteries.map((p) => p.name),
        district: chosen
            ? chosen.districts
            : [...new Set(presbyteries.flatMap((p) => p.districts))].sort(),
    };

    const hint = (field: (typeof fields)[number]) => {
        if (field.name === 'presbytery' && chosen?.headquarters) {
            return `${field.hint} Headquarters: ${chosen.headquarters}.`;
        }

        if (field.name === 'district' && form.data.presbytery && !chosen) {
            return `${field.hint} This presbytery is not on the PCG list, so every listed district is suggested.`;
        }

        return field.hint;
    };

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
                                list={
                                    suggestions[field.name] ||
                                    field.name === 'city'
                                        ? `${field.name}-options`
                                        : undefined
                                }
                                autoComplete="off"
                                title={
                                    field.name === 'city'
                                        ? townTooltip(towns, form.data.city)
                                        : undefined
                                }
                                maxLength={150}
                                required={!optional.includes(field.name)}
                            />
                            {suggestions[field.name] && (
                                <datalist id={`${field.name}-options`}>
                                    {suggestions[field.name]!.map((option) => (
                                        <option key={option} value={option} />
                                    ))}
                                </datalist>
                            )}
                            <p className="text-xs text-muted-foreground">
                                {hint(field)}
                            </p>
                            <InputError message={form.errors[field.name]} />
                        </div>
                    ))}
                    {/* City suggestions: cities with a neighbourhood list, then the Ghana town list. */}
                    <TownSuggestions id="city-options" extra={cities} />
                </section>

                {/* The lists these settings draw their suggestions from. */}
                <nav
                    aria-label="Lists used here"
                    className="flex flex-wrap gap-2 text-sm"
                >
                    {[
                        {
                            label: 'PCG Presbyteries',
                            href: presbyteriesIndex(),
                            icon: Landmark,
                        },
                        {
                            label: 'Neighbourhoods',
                            href: neighbourhoodsIndex(),
                            icon: Signpost,
                        },
                        {
                            label: 'Ghana Towns',
                            href: townsIndex(),
                            icon: MapPinned,
                        },
                    ].map(({ label, href, icon: Icon }) => (
                        <Link
                            key={label}
                            href={href}
                            className="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-muted-foreground transition-colors hover:border-primary/50 hover:text-foreground"
                        >
                            <Icon className="size-4" />
                            {label}
                        </Link>
                    ))}
                </nav>

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
