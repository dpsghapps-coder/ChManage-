import { Head, Link, useForm } from '@inertiajs/react';
import { Landmark, MapPinned, Signpost } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
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
    /** Days without a visit or lesson before a newcomer is flagged for follow-up. */
    followup_days: number;
    /** Days before a committee term ends that it is flagged. */
    term_warning_days: number;
    /** The member phone number the portal signs people in with. */
    portal_phone_field: string;
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
    portalPhoneFields,
    requestTypes,
    requestHandlers,
    roles,
}: {
    settings: Settings;
    portalPhoneFields: Record<string, string>;
    requestTypes: { key: string; label: string }[];
    /** Type => role id ('' = administrators only). */
    requestHandlers: Record<string, string>;
    roles: { id: number; name: string }[];
    presbyteries: Presbytery[];
    cities: string[];
}) {
    const form = useForm<
        Settings & { request_handlers: Record<string, string> }
    >({ ...settings, request_handlers: requestHandlers });
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

                <section className="grid gap-2 rounded-lg border p-5">
                    <h2 className="font-medium">Committees</h2>
                    <Label htmlFor="term_warning_days">
                        Warn before a term ends
                    </Label>
                    <div className="flex items-center gap-2">
                        <Input
                            id="term_warning_days"
                            type="number"
                            inputMode="numeric"
                            min={7}
                            max={365}
                            className="w-28"
                            value={form.data.term_warning_days}
                            onChange={(e) =>
                                form.setData(
                                    'term_warning_days',
                                    Number(e.target.value),
                                )
                            }
                            required
                        />
                        <span className="text-sm text-muted-foreground">
                            days
                        </span>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        A committee term ending within this many days shows as
                        “Ends in …” on the committee, in the Terms ending soon
                        list, and as a count on the Committees menu item.
                    </p>
                    <InputError message={form.errors.term_warning_days} />
                </section>

                <section className="grid gap-2 rounded-lg border p-5">
                    <h2 className="font-medium">Newcomers</h2>
                    <Label htmlFor="followup_days">
                        Flag for follow-up after
                    </Label>
                    <div className="flex items-center gap-2">
                        <Input
                            id="followup_days"
                            type="number"
                            inputMode="numeric"
                            min={7}
                            max={365}
                            className="w-28"
                            value={form.data.followup_days}
                            onChange={(e) =>
                                form.setData(
                                    'followup_days',
                                    Number(e.target.value),
                                )
                            }
                            required
                        />
                        <span className="text-sm text-muted-foreground">
                            days
                        </span>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        A visitor, newcomer or catechumen with no visit and no
                        completed lesson for this long shows under “Needs
                        follow-up” on the Newcomers overview.
                    </p>
                    <InputError message={form.errors.followup_days} />
                </section>

                <section className="grid gap-2 rounded-lg border p-5">
                    <h2 className="font-medium">Member portal</h2>
                    <Label htmlFor="portal_phone_field">
                        Primary contact number
                    </Label>
                    <NativeSelect
                        id="portal_phone_field"
                        className="w-48"
                        value={form.data.portal_phone_field}
                        onChange={(e) =>
                            form.setData('portal_phone_field', e.target.value)
                        }
                    >
                        {Object.entries(portalPhoneFields).map(
                            ([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ),
                        )}
                    </NativeSelect>
                    <p className="text-xs text-muted-foreground">
                        Members sign in to the portal with this number and their
                        date of birth. It is also where the sign-in code is sent
                        once that is switched on.
                    </p>
                    <InputError message={form.errors.portal_phone_field} />
                </section>

                <section className="grid gap-3 rounded-lg border p-5">
                    <div>
                        <h2 className="font-medium">
                            Who handles member requests
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Choose the role that receives each kind of request
                            from the member portal. Only people whose role has
                            the “Member Requests” permission can act on them.
                            Administrators see every request, and staff whose
                            position is Administrator always receive requests to
                            change details.
                        </p>
                    </div>
                    {requestTypes.map((type) => (
                        <div
                            key={type.key}
                            className="grid items-center gap-2 sm:grid-cols-2"
                        >
                            <Label htmlFor={`handler-${type.key}`}>
                                {type.label}
                            </Label>
                            <NativeSelect
                                id={`handler-${type.key}`}
                                value={
                                    form.data.request_handlers[type.key] ?? ''
                                }
                                onChange={(e) =>
                                    form.setData('request_handlers', {
                                        ...form.data.request_handlers,
                                        [type.key]: e.target.value,
                                    })
                                }
                            >
                                <option value="">Administrators only</option>
                                {roles.map((role) => (
                                    <option key={role.id} value={role.id}>
                                        {role.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </div>
                    ))}
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
