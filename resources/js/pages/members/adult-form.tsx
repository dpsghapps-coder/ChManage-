import { Head, Link, useForm } from '@inertiajs/react';
import { Check, Plus, Trash2, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { GpsCapture } from '@/components/gps-capture';
import InputError from '@/components/input-error';
import { MemberPicker } from '@/components/member-picker';
import type { MemberHit } from '@/components/member-picker';
import { PageHeader } from '@/components/page-header';
import { PhotoPicker } from '@/components/photo-picker';
import { statusLabel } from '@/components/staff-status';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { digitsOnly, phoneInputProps } from '@/lib/phone';
import { index, update } from '@/routes/members';
import { store } from '@/routes/members/adult';

type Sacrament = { date: string; place: string; minister: string };

type ServiceRecord = {
    type: string;
    name: string;
    position: string;
    started_on: string;
    ended_on: string;
};

type Member = {
    id: number;
    title: string | null;
    first_name: string | null;
    last_name: string | null;
    other_names: string;
    sex: string | null;
    date_of_birth: string | null;
    place_of_birth: string | null;
    hometown: string | null;
    mobile: string | null;
    telephone: string | null;
    email: string | null;
    facebook_id: string | null;
    instagram_id: string | null;
    twitter_id: string | null;
    tiktok_id: string | null;
    residence: string | null;
    latitude: number | null;
    longitude: number | null;
    location_accuracy: number | null;
    marital_status: string | null;
    marriage_type: string | null;
    maiden_name: string | null;
    spouse_name: string | null;
    spouse_member: {
        id: number;
        member_number: string;
        full_name: string;
    } | null;
    father_name: string | null;
    mother_name: string | null;
    joined_on: string | null;
    generational_group: string | null;
    non_communicant: boolean;
    non_communicant_reason: string | null;
    photo_url: string | null;
    full_name: string;
    next_of_kin: {
        name: string;
        phone: string;
        residential_address: string;
        postal_address: string;
        member: { id: number; member_number: string; full_name: string } | null;
    };
    emergency_contact: {
        name: string;
        phone: string;
        relationship: string;
        member: { id: number; member_number: string; full_name: string } | null;
    };
    sacraments: Record<'baptism' | 'confirmation', Sacrament>;
    group_ids: number[];
    service_records: ServiceRecord[];
};

type Props = {
    /** The record being edited, or null when registering a new one. */
    member: Member | null;
    maritalStatuses: string[];
    marriageTypes: string[];
    generationalGroups: string[];
    serviceTypes: { value: string; label: string }[];
    groups: { id: number; name: string; short_name: string | null }[];
    residences: string[];
};

/** The sections of the record, in the same order as the member details. */
const STEPS = [
    'Basic Info',
    'Contact',
    'Family',
    'Church',
    'Service',
    'Sacraments',
] as const;

/** Which step each field lives on, so a server-side error can send the form back to it. */
const STEP_OF_FIELD: Record<string, number> = {
    title: 0,
    first_name: 0,
    last_name: 0,
    other_names: 0,
    sex: 0,
    date_of_birth: 0,
    place_of_birth: 0,
    hometown: 0,
    photo: 0,
    father_name: 0,
    mother_name: 0,
    next_of_kin: 0,
    emergency_contact: 0,
    mobile: 1,
    telephone: 1,
    email: 1,
    facebook_id: 1,
    instagram_id: 1,
    twitter_id: 1,
    tiktok_id: 1,
    residence: 1,
    latitude: 1,
    longitude: 1,
    location_accuracy: 1,
    marital_status: 2,
    marriage_type: 2,
    maiden_name: 2,
    spouse_name: 2,
    spouse_member_id: 2,
    joined_on: 3,
    generational_group: 3,
    group_ids: 3,
    service_records: 4,
    sacraments: 5,
    non_communicant: 5,
    non_communicant_reason: 5,
};

const stepOfError = (key: string) => STEP_OF_FIELD[key.split('.')[0]] ?? 0;

const blankSacrament: Sacrament = { date: '', place: '', minister: '' };

type TextField =
    | 'title'
    | 'first_name'
    | 'last_name'
    | 'other_names'
    | 'date_of_birth'
    | 'place_of_birth'
    | 'hometown'
    | 'mobile'
    | 'telephone'
    | 'email'
    | 'facebook_id'
    | 'instagram_id'
    | 'twitter_id'
    | 'tiktok_id'
    | 'residence'
    | 'maiden_name'
    | 'spouse_name'
    | 'father_name'
    | 'mother_name'
    | 'joined_on'
    | 'non_communicant_reason';

export default function AdultMemberForm({
    maritalStatuses,
    marriageTypes,
    generationalGroups,
    serviceTypes,
    groups,
    residences,
    member,
}: Props) {
    const editing = member !== null;
    const [step, setStep] = useState(0);
    const [reached, setReached] = useState(0);
    const [spouse, setSpouse] = useState<Member['spouse_member']>(
        member?.spouse_member ?? null,
    );
    const [kinMember, setKinMember] = useState<Member['next_of_kin']['member']>(
        member?.next_of_kin?.member ?? null,
    );
    const [contactMember, setContactMember] = useState<
        Member['emergency_contact']['member']
    >(member?.emergency_contact?.member ?? null);
    const panels = useRef<(HTMLDivElement | null)[]>([]);
    const last = STEPS.length - 1;

    const form = useForm({
        title: member?.title ?? '',
        first_name: member?.first_name ?? '',
        last_name: member?.last_name ?? '',
        other_names: member?.other_names ?? '',
        sex: member?.sex ?? '',
        date_of_birth: member?.date_of_birth ?? '',
        place_of_birth: member?.place_of_birth ?? '',
        hometown: member?.hometown ?? '',
        photo: null as File | null,
        mobile: member?.mobile ?? '',
        telephone: member?.telephone ?? '',
        email: member?.email ?? '',
        facebook_id: member?.facebook_id ?? '',
        instagram_id: member?.instagram_id ?? '',
        twitter_id: member?.twitter_id ?? '',
        tiktok_id: member?.tiktok_id ?? '',
        residence: member?.residence ?? '',
        latitude: (member?.latitude ?? null) as number | null,
        longitude: (member?.longitude ?? null) as number | null,
        location_accuracy: (member?.location_accuracy ?? null) as number | null,
        marital_status: member?.marital_status ?? '',
        marriage_type: member?.marriage_type ?? '',
        maiden_name: member?.maiden_name ?? '',
        spouse_name: member?.spouse_member ? '' : (member?.spouse_name ?? ''),
        spouse_member_id: (member?.spouse_member?.id ?? null) as number | null,
        father_name: member?.father_name ?? '',
        mother_name: member?.mother_name ?? '',
        joined_on: member?.joined_on ?? '',
        generational_group: member?.generational_group ?? '',
        non_communicant: member?.non_communicant ?? false,
        non_communicant_reason: member?.non_communicant_reason ?? '',
        // Tells the server the Next of Kin, Sacraments, Groups and Service steps were part of this save.
        has_related: true,
        next_of_kin: {
            name: member?.next_of_kin?.member
                ? ''
                : (member?.next_of_kin?.name ?? ''),
            phone: member?.next_of_kin?.member
                ? ''
                : (member?.next_of_kin?.phone ?? ''),
            residential_address: member?.next_of_kin?.residential_address ?? '',
            postal_address: member?.next_of_kin?.postal_address ?? '',
            member_id: (member?.next_of_kin?.member?.id ?? null) as
                | number
                | null,
        },
        emergency_contact: {
            name: member?.emergency_contact?.member
                ? ''
                : (member?.emergency_contact?.name ?? ''),
            phone: member?.emergency_contact?.member
                ? ''
                : (member?.emergency_contact?.phone ?? ''),
            relationship: member?.emergency_contact?.relationship ?? '',
            member_id: (member?.emergency_contact?.member?.id ?? null) as
                | number
                | null,
        },
        sacraments: member?.sacraments ?? {
            baptism: blankSacrament,
            confirmation: blankSacrament,
        },
        group_ids: member?.group_ids ?? ([] as number[]),
        service_records: member?.service_records ?? ([] as ServiceRecord[]),
    });

    const errors = form.errors as Record<string, string | undefined>;

    /** Checks the fields of one step with the browser's own rules and points at the first problem. */
    const stepIsValid = (at: number) => {
        const controls = panels.current[at]?.querySelectorAll<
            HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
        >('input, select, textarea');

        for (const control of controls ?? []) {
            if (!control.checkValidity()) {
                setStep(at);
                control.reportValidity();

                return false;
            }
        }

        return true;
    };

    const goTo = (at: number) => {
        setStep(at);
        setReached((current) => Math.max(current, at));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const next = () => {
        if (stepIsValid(step)) {
            goTo(step + 1);
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // Enter inside a field moves on, it does not save half a form.
        if (step < last) {
            next();

            return;
        }

        for (let i = 0; i <= last; i++) {
            if (!stepIsValid(i)) {
                return;
            }
        }

        if (editing) {
            // A file upload cannot be sent as PUT, so the update is a POST that says it means PUT.
            form.transform((data) => ({ ...data, _method: 'put' }));
        }

        form.post(editing ? update(member.id).url : store().url, {
            onError: (problems) => {
                goTo(Math.min(...Object.keys(problems).map(stepOfError)));
            },
        });
    };

    const text = (
        name: TextField,
        label: string,
        props: React.ComponentProps<'input'> = {},
    ) => {
        const isPhone = name === 'mobile' || name === 'telephone';

        return (
            <div className="grid gap-2">
                <Label htmlFor={name}>{label}</Label>
                <Input
                    id={name}
                    value={form.data[name]}
                    onChange={(e) =>
                        form.setData(
                            name,
                            isPhone
                                ? digitsOnly(e.target.value)
                                : e.target.value,
                        )
                    }
                    {...(isPhone ? phoneInputProps : {})}
                    {...props}
                />
                <InputError message={form.errors[name]} />
            </div>
        );
    };

    const select = (
        name: 'sex' | 'marital_status' | 'marriage_type' | 'generational_group',
        label: string,
        options: { value: string; label: string }[],
        props: { required?: boolean; blank?: string } = {},
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <NativeSelect
                id={name}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                required={props.required}
            >
                <option value="">{props.blank ?? 'Not stated'}</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </NativeSelect>
            <InputError message={form.errors[name]} />
        </div>
    );

    const kin = (
        name: 'name' | 'phone' | 'residential_address' | 'postal_address',
        label: string,
        multiline = false,
    ) => {
        const value = form.data.next_of_kin[name];
        const set = (v: string) =>
            form.setData('next_of_kin', {
                ...form.data.next_of_kin,
                [name]: name === 'phone' ? digitsOnly(v) : v,
            });

        return (
            <div className={`grid gap-2 ${multiline ? 'sm:col-span-2' : ''}`}>
                <Label htmlFor={`kin-${name}`}>{label}</Label>
                {multiline ? (
                    <Textarea
                        id={`kin-${name}`}
                        rows={2}
                        maxLength={200}
                        value={value}
                        onChange={(e) => set(e.target.value)}
                    />
                ) : (
                    <Input
                        id={`kin-${name}`}
                        value={value}
                        onChange={(e) => set(e.target.value)}
                        {...(name === 'phone' ? phoneInputProps : {})}
                    />
                )}
                <InputError message={errors[`next_of_kin.${name}`]} />
            </div>
        );
    };

    const contactField = (
        name: 'name' | 'phone' | 'relationship',
        label: string,
    ) => {
        const value = form.data.emergency_contact[name];
        const set = (v: string) =>
            form.setData('emergency_contact', {
                ...form.data.emergency_contact,
                [name]: name === 'phone' ? digitsOnly(v) : v,
            });

        return (
            <div className="grid gap-2">
                <Label htmlFor={`contact-${name}`}>{label}</Label>
                <Input
                    id={`contact-${name}`}
                    value={value}
                    onChange={(e) => set(e.target.value)}
                    {...(name === 'phone' ? phoneInputProps : {})}
                />
                <InputError message={errors[`emergency_contact.${name}`]} />
            </div>
        );
    };

    const sacramentFields = (
        kind: 'baptism' | 'confirmation',
        title: string,
    ) => {
        const value = form.data.sacraments[kind];
        const set = (field: keyof Sacrament, v: string) =>
            form.setData('sacraments', {
                ...form.data.sacraments,
                [kind]: { ...value, [field]: v },
            });

        return (
            <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-3">
                <h2 className="font-medium sm:col-span-3">{title}</h2>
                <div className="grid gap-2">
                    <Label htmlFor={`${kind}-date`}>Date</Label>
                    <Input
                        id={`${kind}-date`}
                        type="date"
                        value={value.date}
                        onChange={(e) => set('date', e.target.value)}
                    />
                    <InputError message={errors[`sacraments.${kind}.date`]} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`${kind}-place`}>Place</Label>
                    <Input
                        id={`${kind}-place`}
                        value={value.place}
                        onChange={(e) => set('place', e.target.value)}
                    />
                    <InputError message={errors[`sacraments.${kind}.place`]} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`${kind}-minister`}>Minister</Label>
                    <Input
                        id={`${kind}-minister`}
                        value={value.minister}
                        onChange={(e) => set('minister', e.target.value)}
                    />
                    <InputError
                        message={errors[`sacraments.${kind}.minister`]}
                    />
                </div>
            </section>
        );
    };

    const changeService = (at: number, changes: Partial<ServiceRecord>) =>
        form.setData(
            'service_records',
            form.data.service_records.map((record, i) =>
                i === at ? { ...record, ...changes } : record,
            ),
        );

    /** One step's content. Every step stays mounted (only hidden) so typed values and checks are never lost. */
    const panel = (at: number, children: ReactNode) => (
        <div
            ref={(el) => {
                panels.current[at] = el;
            }}
            hidden={step !== at}
            className="space-y-6"
        >
            {children}
        </div>
    );

    const title = editing ? `Edit ${member.full_name}` : 'Add New Member';

    return (
        <>
            <Head title={title} />

            <form
                onSubmit={submit}
                noValidate
                className="max-w-4xl space-y-6 p-4"
            >
                <PageHeader title={title} />

                <nav aria-label="Steps" className="rounded-lg border p-4">
                    <ol className="flex flex-wrap gap-x-2 gap-y-3">
                        {STEPS.map((label, i) => {
                            const done =
                                i < step || (i <= reached && i !== step);

                            return (
                                <li
                                    key={label}
                                    className="flex items-center gap-2"
                                >
                                    <button
                                        type="button"
                                        disabled={i > reached}
                                        onClick={() => setStep(i)}
                                        aria-current={
                                            step === i ? 'step' : undefined
                                        }
                                        className="flex items-center gap-2 text-sm disabled:cursor-not-allowed"
                                    >
                                        <span
                                            className={`flex size-7 items-center justify-center rounded-full border text-xs font-medium ${
                                                step === i
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : done
                                                      ? 'border-primary text-primary'
                                                      : 'text-muted-foreground'
                                            }`}
                                        >
                                            {done && step !== i ? (
                                                <Check className="size-3.5" />
                                            ) : (
                                                i + 1
                                            )}
                                        </span>
                                        <span
                                            className={
                                                step === i
                                                    ? 'font-medium'
                                                    : 'text-muted-foreground'
                                            }
                                        >
                                            {label}
                                        </span>
                                    </button>
                                    {i < last && (
                                        <span className="mx-1 hidden h-px w-4 bg-border sm:block" />
                                    )}
                                </li>
                            );
                        })}
                    </ol>
                </nav>

                {/* 1. Basic Info */}
                {panel(
                    0,
                    <>
                        <section className="grid gap-5 rounded-lg border p-5">
                            <h2 className="font-medium">Photo</h2>
                            <PhotoPicker
                                value={form.data.photo}
                                onChange={(photo) =>
                                    form.setData('photo', photo)
                                }
                                currentUrl={member?.photo_url}
                                error={form.errors.photo}
                            />
                        </section>

                        <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-3">
                            <h2 className="font-medium sm:col-span-3">
                                Basic Info
                            </h2>
                            <datalist id="titles">
                                {[
                                    'Mr',
                                    'Mrs',
                                    'Miss',
                                    'Ms',
                                    'Madam',
                                    'Dr',
                                    'Prof',
                                    'Rev',
                                ].map((t) => (
                                    <option key={t} value={t} />
                                ))}
                            </datalist>
                            {text('title', 'Title', {
                                list: 'titles',
                                maxLength: 20,
                            })}
                            {text('first_name', 'First name', {
                                required: true,
                            })}
                            {text('last_name', 'Surname', { required: true })}
                            {text('other_names', 'Other names')}
                            {select(
                                'sex',
                                'Sex',
                                [
                                    { value: 'male', label: 'Male' },
                                    { value: 'female', label: 'Female' },
                                ],
                                { required: true, blank: 'Select…' },
                            )}
                            {text('date_of_birth', 'Date of birth', {
                                type: 'date',
                                required: true,
                            })}
                            {text('place_of_birth', 'Place of birth')}
                            {text('hometown', 'Home town')}
                        </section>

                        <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                            <h2 className="font-medium sm:col-span-2">
                                Parents
                            </h2>
                            {text('father_name', "Father's name")}
                            {text('mother_name', "Mother's name")}
                        </section>

                        <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <h2 className="font-medium">
                                    Emergency Contact
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Who to contact in an emergency. Optional.
                                </p>
                            </div>
                            {contactMember ? (
                                <div className="flex items-start justify-between gap-3 rounded-md border bg-muted/40 p-3 sm:col-span-2">
                                    <div className="text-sm">
                                        <div className="font-medium">
                                            {contactMember.full_name}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {contactMember.member_number} · a
                                            member
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setContactMember(null);
                                            form.setData('emergency_contact', {
                                                ...form.data.emergency_contact,
                                                member_id: null,
                                            });
                                        }}
                                    >
                                        <X /> Remove
                                    </Button>
                                </div>
                            ) : (
                                <>
                                    {contactField('name', 'Name')}
                                    {contactField('phone', 'Phone')}
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label className="text-xs font-normal text-muted-foreground">
                                            Or find them if they are a member
                                        </Label>
                                        <MemberPicker
                                            invalid={Boolean(
                                                errors[
                                                    'emergency_contact.member_id'
                                                ],
                                            )}
                                            onPick={(hit: MemberHit) => {
                                                setContactMember({
                                                    id: hit.id,
                                                    member_number:
                                                        hit.member_number,
                                                    full_name: hit.full_name,
                                                });
                                                form.setData(
                                                    'emergency_contact',
                                                    {
                                                        ...form.data
                                                            .emergency_contact,
                                                        member_id: hit.id,
                                                    },
                                                );
                                            }}
                                        />
                                    </div>
                                </>
                            )}
                            {contactField('relationship', 'Relationship')}
                        </section>

                        <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <h2 className="font-medium">Next of Kin</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Closest relative for official records.
                                    Optional.
                                </p>
                            </div>
                            {kinMember ? (
                                <div className="flex items-start justify-between gap-3 rounded-md border bg-muted/40 p-3 sm:col-span-2">
                                    <div className="text-sm">
                                        <div className="font-medium">
                                            {kinMember.full_name}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {kinMember.member_number} · a member
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setKinMember(null);
                                            form.setData('next_of_kin', {
                                                ...form.data.next_of_kin,
                                                member_id: null,
                                            });
                                        }}
                                    >
                                        <X /> Remove
                                    </Button>
                                </div>
                            ) : (
                                <>
                                    {kin('name', 'Name')}
                                    {kin('phone', 'Phone')}
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label className="text-xs font-normal text-muted-foreground">
                                            Or find them if they are a member
                                        </Label>
                                        <MemberPicker
                                            invalid={Boolean(
                                                errors['next_of_kin.member_id'],
                                            )}
                                            onPick={(hit: MemberHit) => {
                                                setKinMember({
                                                    id: hit.id,
                                                    member_number:
                                                        hit.member_number,
                                                    full_name: hit.full_name,
                                                });
                                                form.setData('next_of_kin', {
                                                    ...form.data.next_of_kin,
                                                    member_id: hit.id,
                                                });
                                            }}
                                        />
                                    </div>
                                </>
                            )}
                            {kin(
                                'residential_address',
                                'Residential address',
                                true,
                            )}
                            {kin('postal_address', 'Postal address', true)}
                        </section>
                    </>,
                )}

                {/* 2. Contact */}
                {panel(
                    1,
                    <>
                        <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                            <h2 className="font-medium sm:col-span-2">
                                Contact
                            </h2>
                            {text('mobile', 'Primary mobile')}
                            {text('telephone', 'Secondary mobile')}
                            {text('email', 'Email', { type: 'email' })}
                            {text('residence', 'Residence', {
                                list: 'residences',
                                placeholder: 'Neighbourhood',
                            })}
                            <datalist id="residences">
                                {residences.map((place) => (
                                    <option key={place} value={place} />
                                ))}
                            </datalist>
                        </section>

                        <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                            <h2 className="font-medium sm:col-span-2">
                                Social Media
                            </h2>
                            {text('facebook_id', 'Facebook', {
                                placeholder: 'facebook.com/…',
                            })}
                            {text('instagram_id', 'Instagram', {
                                placeholder: '@username',
                            })}
                            {text('twitter_id', 'Twitter / X', {
                                placeholder: '@handle',
                            })}
                            {text('tiktok_id', 'TikTok', {
                                placeholder: '@username',
                            })}
                        </section>

                        <section className="grid gap-3 rounded-lg border p-5">
                            <h2 className="font-medium">Home Location</h2>
                            <GpsCapture
                                value={
                                    form.data.latitude !== null &&
                                    form.data.longitude !== null
                                        ? {
                                              latitude: form.data.latitude,
                                              longitude: form.data.longitude,
                                              accuracy:
                                                  form.data.location_accuracy,
                                          }
                                        : null
                                }
                                onChange={(fix) =>
                                    form.setData((data) => ({
                                        ...data,
                                        latitude: fix?.latitude ?? null,
                                        longitude: fix?.longitude ?? null,
                                        location_accuracy:
                                            fix?.accuracy ?? null,
                                    }))
                                }
                            />
                            {(form.errors.latitude ??
                                form.errors.longitude) && (
                                <p className="text-sm text-destructive">
                                    {form.errors.latitude ??
                                        form.errors.longitude}
                                </p>
                            )}
                        </section>
                    </>,
                )}

                {/* 3. Family */}
                {panel(
                    2,
                    <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                        <h2 className="font-medium sm:col-span-2">Marital</h2>
                        {select(
                            'marital_status',
                            'Marital status',
                            maritalStatuses.map((value) => ({
                                value,
                                label: statusLabel(value),
                            })),
                        )}
                        {select(
                            'marriage_type',
                            'Marriage type',
                            marriageTypes.map((value) => ({
                                value,
                                label: statusLabel(value),
                            })),
                        )}
                        {text('maiden_name', 'Maiden name')}

                        <div className="grid gap-2">
                            {spouse ? (
                                <>
                                    <Label>Spouse</Label>
                                    <div className="flex items-start justify-between gap-3 rounded-md border bg-muted/40 p-3">
                                        <div className="text-sm">
                                            <div className="font-medium">
                                                {spouse.full_name}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {spouse.member_number} · a
                                                member
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                setSpouse(null);
                                                form.setData(
                                                    'spouse_member_id',
                                                    null,
                                                );
                                            }}
                                        >
                                            <X /> Remove
                                        </Button>
                                    </div>
                                </>
                            ) : (
                                <>
                                    {text('spouse_name', 'Spouse name')}
                                    <Label className="text-xs font-normal text-muted-foreground">
                                        Or find the spouse if they are a member
                                    </Label>
                                    <MemberPicker
                                        invalid={Boolean(
                                            errors.spouse_member_id,
                                        )}
                                        onPick={(hit: MemberHit) => {
                                            setSpouse({
                                                id: hit.id,
                                                member_number:
                                                    hit.member_number,
                                                full_name: hit.full_name,
                                            });
                                            form.setData(
                                                'spouse_member_id',
                                                hit.id,
                                            );
                                        }}
                                    />
                                </>
                            )}
                            <InputError message={errors.spouse_member_id} />
                        </div>
                    </section>,
                )}

                {/* 4. Church */}
                {panel(
                    3,
                    <>
                        <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                            <h2 className="font-medium sm:col-span-2">
                                Church
                            </h2>
                            {text('joined_on', 'Date joined', { type: 'date' })}
                            {select(
                                'generational_group',
                                'Generational group',
                                generationalGroups.map((value) => ({
                                    value,
                                    label: value,
                                })),
                                { blank: 'Select group' },
                            )}
                        </section>
                        <section className="space-y-4 rounded-lg border p-5">
                            <div>
                                <h2 className="font-medium">Service Groups</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Tick every group this member belongs to.
                                </p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {groups.map((group) => (
                                    <label
                                        key={group.id}
                                        className="flex items-center gap-3 rounded-md border p-3 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={form.data.group_ids.includes(
                                                group.id,
                                            )}
                                            onChange={(e) =>
                                                form.setData(
                                                    'group_ids',
                                                    e.target.checked
                                                        ? [
                                                              ...form.data
                                                                  .group_ids,
                                                              group.id,
                                                          ]
                                                        : form.data.group_ids.filter(
                                                              (id) =>
                                                                  id !==
                                                                  group.id,
                                                          ),
                                                )
                                            }
                                            className="size-4 rounded border"
                                        />
                                        <span>
                                            <span className="font-medium">
                                                {group.name}
                                            </span>
                                            {group.short_name &&
                                                group.short_name !==
                                                    group.name && (
                                                    <span className="ml-1 text-muted-foreground">
                                                        ({group.short_name})
                                                    </span>
                                                )}
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.group_ids} />
                        </section>
                    </>,
                )}

                {/* 5. Service */}
                {panel(
                    4,
                    <section className="space-y-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="font-medium">Service</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Committees, executives and leadership posts
                                    held.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    form.setData('service_records', [
                                        ...form.data.service_records,
                                        {
                                            type:
                                                serviceTypes[0]?.value ??
                                                'committee',
                                            name: '',
                                            position: '',
                                            started_on: '',
                                            ended_on: '',
                                        },
                                    ])
                                }
                            >
                                <Plus /> Add Service
                            </Button>
                        </div>

                        {form.data.service_records.length === 0 && (
                            <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                No service records. Use Add Service to record
                                one.
                            </p>
                        )}

                        {form.data.service_records.map((record, i) => (
                            <div
                                key={i}
                                className="grid gap-4 rounded-lg border p-5 sm:grid-cols-2"
                            >
                                <div className="flex items-center justify-between sm:col-span-2">
                                    <span className="text-sm font-medium text-muted-foreground">
                                        Service {i + 1}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Remove service ${i + 1}`}
                                        onClick={() =>
                                            form.setData(
                                                'service_records',
                                                form.data.service_records.filter(
                                                    (_, at) => at !== i,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`service-type-${i}`}>
                                        Type of service
                                    </Label>
                                    <NativeSelect
                                        id={`service-type-${i}`}
                                        value={record.type}
                                        onChange={(e) =>
                                            changeService(i, {
                                                type: e.target.value,
                                            })
                                        }
                                    >
                                        {serviceTypes.map((type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <InputError
                                        message={
                                            errors[`service_records.${i}.type`]
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`service-name-${i}`}>
                                        Name of committee or body
                                    </Label>
                                    <Input
                                        id={`service-name-${i}`}
                                        value={record.name}
                                        onChange={(e) =>
                                            changeService(i, {
                                                name: e.target.value,
                                            })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={
                                            errors[`service_records.${i}.name`]
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`service-position-${i}`}>
                                        Position
                                    </Label>
                                    <Input
                                        id={`service-position-${i}`}
                                        value={record.position}
                                        onChange={(e) =>
                                            changeService(i, {
                                                position: e.target.value,
                                            })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={
                                            errors[
                                                `service_records.${i}.position`
                                            ]
                                        }
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor={`service-start-${i}`}>
                                            Date started
                                        </Label>
                                        <Input
                                            id={`service-start-${i}`}
                                            type="date"
                                            value={record.started_on}
                                            onChange={(e) =>
                                                changeService(i, {
                                                    started_on: e.target.value,
                                                })
                                            }
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `service_records.${i}.started_on`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor={`service-end-${i}`}>
                                            Date ended
                                        </Label>
                                        <Input
                                            id={`service-end-${i}`}
                                            type="date"
                                            value={record.ended_on}
                                            onChange={(e) =>
                                                changeService(i, {
                                                    ended_on: e.target.value,
                                                })
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `service_records.${i}.ended_on`
                                                ]
                                            }
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}
                        <InputError message={errors.service_records} />
                    </section>,
                )}

                {/* 6. Sacraments */}
                {panel(
                    5,
                    <>
                        {sacramentFields('baptism', 'Baptism')}
                        {sacramentFields('confirmation', 'Confirmation')}
                        <section className="grid gap-5 rounded-lg border p-5">
                            <h2 className="font-medium">Communion</h2>
                            <div className="grid gap-2 sm:max-w-xs">
                                <Label htmlFor="non_communicant">
                                    Non-communicant
                                </Label>
                                <NativeSelect
                                    id="non_communicant"
                                    value={
                                        form.data.non_communicant ? 'yes' : 'no'
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'non_communicant',
                                            e.target.value === 'yes',
                                        )
                                    }
                                >
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </NativeSelect>
                                <InputError message={errors.non_communicant} />
                            </div>
                            {form.data.non_communicant && (
                                <div className="grid gap-2">
                                    <Label htmlFor="non_communicant_reason">
                                        Reason
                                    </Label>
                                    <Textarea
                                        id="non_communicant_reason"
                                        rows={3}
                                        maxLength={500}
                                        value={form.data.non_communicant_reason}
                                        onChange={(e) =>
                                            form.setData(
                                                'non_communicant_reason',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Enter reason…"
                                    />
                                    <InputError
                                        message={errors.non_communicant_reason}
                                    />
                                </div>
                            )}
                        </section>
                    </>,
                )}

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Button variant="outline" asChild>
                        <Link href={index()}>Cancel</Link>
                    </Button>
                    <div className="flex gap-2">
                        {step > 0 && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => goTo(step - 1)}
                            >
                                Previous
                            </Button>
                        )}
                        {step < last ? (
                            <Button type="button" onClick={next}>
                                Next
                            </Button>
                        ) : (
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'Save Changes' : 'Register'}
                            </Button>
                        )}
                    </div>
                </div>
            </form>
        </>
    );
}
