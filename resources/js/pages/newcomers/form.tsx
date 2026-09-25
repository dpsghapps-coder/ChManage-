import { Head, Link, useForm } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { ChoiceOrOther } from '@/components/choice-or-other';
import { GpsCapture } from '@/components/gps-capture';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PhotoPicker } from '@/components/photo-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { digitsOnly, phoneInputProps } from '@/lib/phone';
import { duplicates, index, show, store, update } from '@/routes/newcomers';

type Newcomer = {
    id: number;
    first_visit_on: string;
    first_service: string | null;
    purpose: string | null;
    heard_via: string | null;
    heard_contact: string | null;
    title: string | null;
    surname: string;
    first_name: string;
    middle_name: string | null;
    sex: 'male' | 'female' | null;
    date_of_birth: string | null;
    mobile: string | null;
    other_numbers: string | null;
    whatsapp: string | null;
    residential_address: string | null;
    postal_address: string | null;
    emergency_number: string | null;
    email: string | null;
    current_status: string | null;
    marital_status: string | null;
    marriage_type: string | null;
    religious_background: string | null;
    religious_other: string | null;
    former_church: string | null;
    is_baptized: boolean;
    is_confirmed: boolean;
    guardian_name: string | null;
    guardian_relationship: string | null;
    guardian_phone: string | null;
    counsellor_id: number | null;
    remarks: string | null;
    photo_url: string | null;
    latitude: number | null;
    longitude: number | null;
    location_accuracy: number | null;
} | null;

type Props = {
    newcomer: Newcomer;
    lists: Record<string, string[]>;
    counsellors: { id: number; name: string }[];
    relationships: string[];
    backgrounds: Record<string, string>;
};

type Match = {
    kind: string;
    id: number;
    name: string;
    detail: string;
    url: string;
};

const today = () => new Date().toISOString().slice(0, 10);

const isUnder18 = (dob: string) => {
    if (!dob) {
        return false;
    }

    const limit = new Date();
    limit.setFullYear(limit.getFullYear() - 18);

    return new Date(`${dob}T00:00:00`) > limit;
};

export default function NewcomerForm({
    newcomer,
    lists,
    counsellors,
    relationships,
    backgrounds,
}: Props) {
    const editing = newcomer !== null;
    const form = useForm({
        first_visit_on: newcomer?.first_visit_on ?? today(),
        first_service: newcomer?.first_service ?? '',
        purpose: newcomer?.purpose ?? '',
        heard_via: newcomer?.heard_via ?? '',
        heard_contact: newcomer?.heard_contact ?? '',
        title: newcomer?.title ?? '',
        surname: newcomer?.surname ?? '',
        first_name: newcomer?.first_name ?? '',
        middle_name: newcomer?.middle_name ?? '',
        sex: newcomer?.sex ?? '',
        date_of_birth: newcomer?.date_of_birth ?? '',
        mobile: newcomer?.mobile ?? '',
        other_numbers: newcomer?.other_numbers ?? '',
        whatsapp: newcomer?.whatsapp ?? '',
        residential_address: newcomer?.residential_address ?? '',
        postal_address: newcomer?.postal_address ?? '',
        emergency_number: newcomer?.emergency_number ?? '',
        email: newcomer?.email ?? '',
        current_status: newcomer?.current_status ?? '',
        marital_status: newcomer?.marital_status ?? '',
        marriage_type: newcomer?.marriage_type ?? '',
        religious_background: newcomer?.religious_background ?? '',
        religious_other: newcomer?.religious_other ?? '',
        former_church: newcomer?.former_church ?? '',
        is_baptized: newcomer?.is_baptized ?? false,
        is_confirmed: newcomer?.is_confirmed ?? false,
        guardian_name: newcomer?.guardian_name ?? '',
        guardian_relationship: newcomer?.guardian_relationship ?? '',
        guardian_phone: newcomer?.guardian_phone ?? '',
        counsellor_id: newcomer?.counsellor_id
            ? String(newcomer.counsellor_id)
            : '',
        remarks: newcomer?.remarks ?? '',
        photo: null as File | null,
        latitude: (newcomer?.latitude ?? null) as number | null,
        longitude: (newcomer?.longitude ?? null) as number | null,
        location_accuracy: (newcomer?.location_accuracy ?? null) as
            | number
            | null,
    });
    const { data, setData, errors } = form;
    const minor = isUnder18(data.date_of_birth);
    const matches = useMatches(data, newcomer?.id ?? null);

    const set =
        (name: keyof typeof data) => (e: { target: { value: string } }) =>
            setData(name, e.target.value as never);
    const phone =
        (name: keyof typeof data) => (e: { target: { value: string } }) =>
            setData(name, digitsOnly(e.target.value) as never);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        // A file upload cannot be sent as PUT, so the update is a POST that says it means PUT.
        form.transform((data) => ({
            ...data,
            ...(newcomer ? { _method: 'put' } : {}),
        }));
        form.post(newcomer ? update(newcomer.id).url : store().url);
    };

    return (
        <>
            <Head title={editing ? 'Edit Person' : 'Register Visitor'} />

            <form onSubmit={submit} className="max-w-4xl space-y-6 p-4">
                <PageHeader
                    title={editing ? 'Edit Person' : 'Register a Visitor'}
                    description="First Time Worshippers / New Comers form, Ebenezer Congregation."
                />

                {matches.length > 0 && (
                    <div
                        role="alert"
                        className="flex gap-3 rounded-lg border border-amber-500/50 bg-amber-500/10 p-4 text-sm"
                    >
                        <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />
                        <div className="space-y-1">
                            <p className="font-medium">
                                This person may already be on the books.
                            </p>
                            <ul className="space-y-0.5">
                                {matches.map((m) => (
                                    <li key={`${m.kind}-${m.id}`}>
                                        <a
                                            href={m.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="underline"
                                        >
                                            {m.name}
                                        </a>{' '}
                                        <span className="text-muted-foreground">
                                            ({m.detail})
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            <p className="text-muted-foreground">
                                Open the record to check. If this is someone
                                else, carry on.
                            </p>
                        </div>
                    </div>
                )}

                <Section title="The visit">
                    <Field
                        id="first_visit_on"
                        label="Date"
                        error={errors.first_visit_on}
                    >
                        <Input
                            id="first_visit_on"
                            type="date"
                            max={today()}
                            value={data.first_visit_on}
                            onChange={set('first_visit_on')}
                            required
                        />
                    </Field>
                    <Field
                        id="first_service"
                        label="Service"
                        error={errors.first_service}
                    >
                        <ChoiceOrOther
                            id="first_service"
                            value={data.first_service}
                            options={lists.service}
                            onChange={(v) => setData('first_service', v)}
                            placeholder="The service"
                        />
                    </Field>
                    <Field id="purpose" label="Purpose" error={errors.purpose}>
                        <ChoiceOrOther
                            id="purpose"
                            value={data.purpose}
                            options={lists.purpose}
                            onChange={(v) => setData('purpose', v)}
                            placeholder="The purpose"
                        />
                    </Field>
                    <Field
                        id="heard_via"
                        label="How did you hear of the congregation?"
                        error={errors.heard_via}
                    >
                        <ChoiceOrOther
                            id="heard_via"
                            value={data.heard_via}
                            options={lists.source}
                            onChange={(v) => setData('heard_via', v)}
                            placeholder="How they heard"
                        />
                    </Field>
                    <Field
                        id="heard_contact"
                        label="Name and contact of the person or source"
                        error={errors.heard_contact}
                        wide
                    >
                        <Input
                            id="heard_contact"
                            value={data.heard_contact}
                            onChange={set('heard_contact')}
                            maxLength={200}
                        />
                    </Field>
                </Section>

                <Section title="Photo and location">
                    <div className="grid content-start gap-2">
                        <span className="text-sm font-medium">Photo</span>
                        <PhotoPicker
                            value={data.photo}
                            onChange={(photo) => setData('photo', photo)}
                            currentUrl={newcomer?.photo_url}
                            error={errors.photo}
                        />
                    </div>
                    <div className="grid content-start gap-2">
                        <span className="text-sm font-medium">
                            Residence location
                        </span>
                        <GpsCapture
                            value={
                                data.latitude !== null &&
                                data.longitude !== null
                                    ? {
                                          latitude: data.latitude,
                                          longitude: data.longitude,
                                          accuracy: data.location_accuracy,
                                      }
                                    : null
                            }
                            onChange={(fix) =>
                                setData((current) => ({
                                    ...current,
                                    latitude: fix?.latitude ?? null,
                                    longitude: fix?.longitude ?? null,
                                    location_accuracy: fix?.accuracy ?? null,
                                }))
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            Optional. Best captured where they live, so a
                            counsellor can find the house.
                        </p>
                        <InputError
                            message={errors.latitude ?? errors.longitude}
                        />
                    </div>
                </Section>

                <Section title="Basic information">
                    <Field id="title" label="Title" error={errors.title}>
                        <ChoiceOrOther
                            id="title"
                            value={data.title}
                            options={lists.title}
                            onChange={(v) => setData('title', v)}
                            placeholder="The title"
                        />
                    </Field>
                    <Field id="sex" label="Gender" error={errors.sex}>
                        <NativeSelect
                            id="sex"
                            value={data.sex}
                            onChange={set('sex')}
                        >
                            <option value="">Select…</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </NativeSelect>
                    </Field>
                    <Field id="surname" label="Surname" error={errors.surname}>
                        <Input
                            id="surname"
                            value={data.surname}
                            onChange={set('surname')}
                            maxLength={100}
                            required
                        />
                    </Field>
                    <Field
                        id="first_name"
                        label="First name"
                        error={errors.first_name}
                    >
                        <Input
                            id="first_name"
                            value={data.first_name}
                            onChange={set('first_name')}
                            maxLength={100}
                            required
                        />
                    </Field>
                    <Field
                        id="middle_name"
                        label="Middle name"
                        error={errors.middle_name}
                    >
                        <Input
                            id="middle_name"
                            value={data.middle_name}
                            onChange={set('middle_name')}
                            maxLength={100}
                        />
                    </Field>
                    <Field
                        id="date_of_birth"
                        label="Date of birth"
                        error={errors.date_of_birth}
                    >
                        <Input
                            id="date_of_birth"
                            type="date"
                            max={today()}
                            value={data.date_of_birth}
                            onChange={set('date_of_birth')}
                        />
                    </Field>
                </Section>

                <Section title="Contact information">
                    <Field
                        id="mobile"
                        label="Mobile number"
                        error={errors.mobile}
                    >
                        <Input
                            id="mobile"
                            {...phoneInputProps}
                            value={data.mobile}
                            onChange={phone('mobile')}
                        />
                    </Field>
                    <Field
                        id="whatsapp"
                        label="WhatsApp number"
                        error={errors.whatsapp}
                    >
                        <Input
                            id="whatsapp"
                            {...phoneInputProps}
                            value={data.whatsapp}
                            onChange={phone('whatsapp')}
                        />
                    </Field>
                    <Field
                        id="other_numbers"
                        label="Other numbers"
                        error={errors.other_numbers}
                    >
                        <Input
                            id="other_numbers"
                            value={data.other_numbers}
                            onChange={set('other_numbers')}
                            maxLength={100}
                        />
                    </Field>
                    <Field
                        id="emergency_number"
                        label="Emergency number"
                        error={errors.emergency_number}
                    >
                        <Input
                            id="emergency_number"
                            {...phoneInputProps}
                            value={data.emergency_number}
                            onChange={phone('emergency_number')}
                        />
                    </Field>
                    <Field id="email" label="Email" error={errors.email}>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={set('email')}
                            maxLength={150}
                        />
                    </Field>
                    <Field
                        id="residential_address"
                        label="Residential address"
                        error={errors.residential_address}
                        wide
                    >
                        <Input
                            id="residential_address"
                            value={data.residential_address}
                            onChange={set('residential_address')}
                            maxLength={250}
                        />
                    </Field>
                    <Field
                        id="postal_address"
                        label="Postal address"
                        error={errors.postal_address}
                        wide
                    >
                        <Input
                            id="postal_address"
                            value={data.postal_address}
                            onChange={set('postal_address')}
                            maxLength={250}
                        />
                    </Field>
                </Section>

                {minor && (
                    <Section
                        title="Parent or guardian"
                        note="Asked because they are under 18."
                    >
                        <Field
                            id="guardian_name"
                            label="Name"
                            error={errors.guardian_name}
                        >
                            <Input
                                id="guardian_name"
                                value={data.guardian_name}
                                onChange={set('guardian_name')}
                                maxLength={200}
                                required
                            />
                        </Field>
                        <Field
                            id="guardian_relationship"
                            label="Relationship"
                            error={errors.guardian_relationship}
                        >
                            <ChoiceOrOther
                                id="guardian_relationship"
                                value={data.guardian_relationship}
                                options={relationships}
                                onChange={(v) =>
                                    setData('guardian_relationship', v)
                                }
                                placeholder="The relationship"
                            />
                        </Field>
                        <Field
                            id="guardian_phone"
                            label="Phone"
                            error={errors.guardian_phone}
                        >
                            <Input
                                id="guardian_phone"
                                {...phoneInputProps}
                                value={data.guardian_phone}
                                onChange={phone('guardian_phone')}
                            />
                        </Field>
                    </Section>
                )}

                <Section title="Other information">
                    <Field
                        id="current_status"
                        label="Current status"
                        error={errors.current_status}
                    >
                        <ChoiceOrOther
                            id="current_status"
                            value={data.current_status}
                            options={lists.current_status}
                            onChange={(v) => setData('current_status', v)}
                            placeholder="Current status"
                        />
                    </Field>
                    <Field
                        id="marital_status"
                        label="Marital status"
                        error={errors.marital_status}
                    >
                        <NativeSelect
                            id="marital_status"
                            value={data.marital_status}
                            onChange={set('marital_status')}
                        >
                            <option value="">Select…</option>
                            <option value="single">Single</option>
                            <option value="married">Married</option>
                            <option value="divorced">Divorced</option>
                            <option value="widowed">Widowed</option>
                        </NativeSelect>
                    </Field>
                    {data.marital_status === 'married' && (
                        <Field
                            id="marriage_type"
                            label="Marriage"
                            error={errors.marriage_type}
                        >
                            <NativeSelect
                                id="marriage_type"
                                value={data.marriage_type}
                                onChange={set('marriage_type')}
                            >
                                <option value="">Select…</option>
                                <option value="ordinance">Ordinance</option>
                                <option value="customary">Customary</option>
                            </NativeSelect>
                        </Field>
                    )}
                    <Field
                        id="religious_background"
                        label="Religious background"
                        error={errors.religious_background}
                    >
                        <NativeSelect
                            id="religious_background"
                            value={data.religious_background}
                            onChange={set('religious_background')}
                        >
                            <option value="">Select…</option>
                            {Object.entries(backgrounds).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    {data.religious_background === 'other' && (
                        <Field
                            id="religious_other"
                            label="Specify"
                            error={errors.religious_other}
                        >
                            <Input
                                id="religious_other"
                                value={data.religious_other}
                                onChange={set('religious_other')}
                                maxLength={150}
                            />
                        </Field>
                    )}
                    {data.religious_background === 'christian' && (
                        <Field
                            id="former_church"
                            label="Former church"
                            error={errors.former_church}
                        >
                            <Input
                                id="former_church"
                                list="former-churches"
                                value={data.former_church}
                                onChange={set('former_church')}
                                maxLength={200}
                            />
                            <datalist id="former-churches">
                                {lists.former_church.map((c) => (
                                    <option key={c} value={c} />
                                ))}
                            </datalist>
                        </Field>
                    )}
                    <div className="flex items-center gap-6 sm:col-span-2">
                        <span className="text-sm font-medium">
                            Sacramental status
                        </span>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.is_baptized}
                                onChange={(e) =>
                                    setData('is_baptized', e.target.checked)
                                }
                            />
                            Baptized
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.is_confirmed}
                                onChange={(e) =>
                                    setData('is_confirmed', e.target.checked)
                                }
                            />
                            Confirmed
                        </label>
                    </div>
                </Section>

                <Section title="Official use only">
                    <Field
                        id="counsellor_id"
                        label="Name of counsellor"
                        error={errors.counsellor_id}
                    >
                        <NativeSelect
                            id="counsellor_id"
                            value={data.counsellor_id}
                            onChange={set('counsellor_id')}
                        >
                            <option value="">No counsellor yet</option>
                            {counsellors.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </NativeSelect>
                        <p className="text-xs text-muted-foreground">
                            Assigning a counsellor makes a visitor a newcomer.
                        </p>
                    </Field>
                    <Field
                        id="remarks"
                        label="Remarks"
                        error={errors.remarks}
                        wide
                    >
                        <Textarea
                            id="remarks"
                            rows={3}
                            value={data.remarks}
                            onChange={set('remarks')}
                            maxLength={2000}
                        />
                    </Field>
                </Section>

                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save' : 'Register'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={newcomer ? show(newcomer.id) : index()}>
                            Cancel
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

/** Looks for the same person, by mobile number or by name and date of birth, once enough has been typed. */
function useMatches(
    data: {
        mobile: string;
        first_name: string;
        surname: string;
        date_of_birth: string;
    },
    ignore: number | null,
): Match[] {
    const [matches, setMatches] = useState<Match[]>([]);

    useEffect(() => {
        const controller = new AbortController();
        const named = data.first_name && data.surname && data.date_of_birth;

        if (data.mobile.length < 10 && !named) {
            setMatches([]);

            return;
        }

        const timer = setTimeout(async () => {
            try {
                const response = await fetch(
                    duplicates({
                        query: {
                            mobile: data.mobile,
                            first_name: data.first_name,
                            surname: data.surname,
                            date_of_birth: data.date_of_birth,
                            ...(ignore ? { ignore } : {}),
                        },
                    }).url,
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );
                setMatches(response.ok ? await response.json() : []);
            } catch {
                // Aborted by a newer keystroke, or offline: keep what is showing.
            }
        }, 400);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [
        data.mobile,
        data.first_name,
        data.surname,
        data.date_of_birth,
        ignore,
    ]);

    return matches;
}

function Section({
    title,
    note,
    children,
}: {
    title: string;
    note?: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4 rounded-lg border p-5">
            <div>
                <h2 className="font-medium">{title}</h2>
                {note && (
                    <p className="text-sm text-muted-foreground">{note}</p>
                )}
            </div>
            <div className="grid gap-4 sm:grid-cols-2">{children}</div>
        </section>
    );
}

function Field({
    id,
    label,
    error,
    wide,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    wide?: boolean;
    children: ReactNode;
}) {
    return (
        <div
            className={`grid content-start gap-2 ${wide ? 'sm:col-span-2' : ''}`}
        >
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

NewcomerForm.layout = {
    breadcrumbs: [{ title: 'Newcomers', href: index() }],
};
