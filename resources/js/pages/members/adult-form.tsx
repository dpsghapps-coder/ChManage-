import { Head, Link, useForm } from '@inertiajs/react';
import { Check, Church, Plus, Save, Trash2, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { ChoiceOrOther } from '@/components/choice-or-other';
import { GpsCapture } from '@/components/gps-capture';
import InputError from '@/components/input-error';
import { MemberPicker } from '@/components/member-picker';
import type { MemberHit } from '@/components/member-picker';
import { PageHeader } from '@/components/page-header';
import { PhotoPicker } from '@/components/photo-picker';
import { statusLabel } from '@/components/staff-status';
import {
    TownSuggestions,
    townTooltip,
    useGhanaTowns,
} from '@/components/town-suggestions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { digitsOnly, phoneInputProps } from '@/lib/phone';
import { index, update } from '@/routes/members';
import { store } from '@/routes/members/adult';

type Sacrament = {
    date: string;
    presbytery: string;
    district: string;
    /** The congregation. */
    place: string;
    minister: string;
};

type LinkedMember = {
    id: number;
    member_number: string;
    full_name: string;
} | null;

type Presbytery = {
    name: string;
    headquarters: string | null;
    districts: string[];
};

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
    profession_id: number | null;
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
    marriage_date: string | null;
    marriage_church: string | null;
    spouse_name: string | null;
    spouse_member: {
        id: number;
        member_number: string;
        full_name: string;
    } | null;
    father_name: string | null;
    father_member: LinkedMember;
    mother_name: string | null;
    mother_member: LinkedMember;
    joined_on: string | null;
    previous_congregation: string | null;
    generational_group: string | null;
    non_communicant: boolean;
    non_communicant_reason: string | null;
    photo_url: string | null;
    full_name: string;
    next_of_kin: {
        name: string;
        relationship: string;
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
    /** The step to open on: after "Save" on a step, the form comes back to it. */
    initialStep?: number;
    maritalStatuses: string[];
    marriageTypes: string[];
    /** Stored value => name; the group itself follows age and sex (see generationalGroupFor). */
    generationalGroups: Record<string, string>;
    serviceTypes: { value: string; label: string }[];
    groups: { id: number; name: string; short_name: string | null }[];
    residences: string[];
    /** Choices for the next of kin's and emergency contact's relationship; the last is "Other". */
    relationships: string[];
    /** Positions offered for each type of service (committee, executive, leadership = Session). */
    positions: Record<string, string[]>;
    /** Service groups and fellowships an executive can serve in. */
    executiveGroups: string[];
    /** The church's committees, for a committee service record. */
    committees: string[];
    /** Occupations grouped by category, for Basic Info. */
    occupations: {
        category: string;
        options: { id: number; name: string }[];
    }[];
    /** Previous congregations already typed on other records. */
    previousCongregations: string[];
    /** The church's city (Church Settings): its neighbourhoods and region narrow the Residence suggestions. */
    residenceArea: {
        city: string | null;
        region: string | null;
        neighbourhoods: string[];
    };
    /** For the sacraments' presbytery → district → congregation pickers. */
    presbyteries: Presbytery[];
    congregations: string[];
    church: { presbytery: string; district: string; congregation: string };
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
    profession_id: 0,
    photo: 0,
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
    emergency_contact: 1,
    marital_status: 2,
    marriage_type: 2,
    maiden_name: 2,
    marriage_date: 2,
    marriage_church: 2,
    spouse_name: 2,
    spouse_member_id: 2,
    father_name: 2,
    father_member_id: 2,
    mother_name: 2,
    mother_member_id: 2,
    next_of_kin: 2,
    joined_on: 3,
    previous_congregation: 3,
    group_ids: 3,
    service_records: 4,
    sacraments: 5,
    non_communicant: 5,
    non_communicant_reason: 5,
};

/** Mirrors Member::generationalGroupFor on the server, which is what is saved. */
const generationalGroupFor = (
    dateOfBirth: string,
    sex: string,
    names: Record<string, string>,
): string | null => {
    if (!dateOfBirth) {
        return null;
    }

    const born = new Date(`${dateOfBirth}T00:00:00`);
    const today = new Date();
    const age =
        today.getFullYear() -
        born.getFullYear() -
        (today.getMonth() < born.getMonth() ||
        (today.getMonth() === born.getMonth() &&
            today.getDate() < born.getDate())
            ? 1
            : 0);

    const group =
        age < 15
            ? 'CS'
            : age < 18
              ? 'JY'
              : age < 30
                ? 'YPG'
                : age < 40
                  ? 'YAF'
                  : sex === 'male'
                    ? "Men's Fellowship"
                    : sex === 'female'
                      ? "Women's Fellowship"
                      : null;

    return group ? (names[group] ?? group) : null;
};

const stepOfError = (key: string) => STEP_OF_FIELD[key.split('.')[0]] ?? 0;

const blankSacrament: Sacrament = {
    date: '',
    presbytery: '',
    district: '',
    place: '',
    minister: '',
};

// "Ga", "ga" and "Ga Presbytery" all name the same presbytery.
const normalise = (name: string) =>
    name
        .trim()
        .toLowerCase()
        .replace(/\s+presbytery$/, '');

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
    | 'marriage_date'
    | 'marriage_church'
    | 'spouse_name'
    | 'father_name'
    | 'mother_name'
    | 'joined_on'
    | 'previous_congregation'
    | 'non_communicant_reason';

export default function AdultMemberForm({
    maritalStatuses,
    marriageTypes,
    generationalGroups,
    serviceTypes,
    groups,
    residences,
    relationships,
    positions,
    executiveGroups,
    committees,
    occupations,
    previousCongregations,
    residenceArea,
    presbyteries,
    congregations,
    church,
    member,
    initialStep = 0,
}: Props) {
    const editing = member !== null;
    const opening = Math.min(Math.max(initialStep, 0), STEPS.length - 1);
    const [step, setStep] = useState(opening);
    // Editing, every step is open from the start; registering, a step opens once reached with Next.
    const [reached, setReached] = useState(
        editing ? STEPS.length - 1 : opening,
    );
    const [spouse, setSpouse] = useState<Member['spouse_member']>(
        member?.spouse_member ?? null,
    );
    const [parents, setParents] = useState<{
        father: LinkedMember;
        mother: LinkedMember;
    }>({
        father: member?.father_member ?? null,
        mother: member?.mother_member ?? null,
    });
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
        profession_id: member?.profession_id
            ? String(member.profession_id)
            : '',
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
        marriage_date: member?.marriage_date ?? '',
        marriage_church: member?.marriage_church ?? '',
        spouse_name: member?.spouse_member ? '' : (member?.spouse_name ?? ''),
        spouse_member_id: (member?.spouse_member?.id ?? null) as number | null,
        father_name: member?.father_member ? '' : (member?.father_name ?? ''),
        father_member_id: (member?.father_member?.id ?? null) as number | null,
        mother_name: member?.mother_member ? '' : (member?.mother_name ?? ''),
        mother_member_id: (member?.mother_member?.id ?? null) as number | null,
        joined_on: member?.joined_on ?? '',
        previous_congregation: member?.previous_congregation ?? '',
        non_communicant: member?.non_communicant ?? false,
        non_communicant_reason: member?.non_communicant_reason ?? '',
        // Tells the server the Next of Kin, Sacraments, Groups and Service steps were part of this save.
        has_related: true,
        next_of_kin: {
            name: member?.next_of_kin?.member
                ? ''
                : (member?.next_of_kin?.name ?? ''),
            relationship: member?.next_of_kin?.relationship ?? '',
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
        sacraments: {
            baptism: { ...blankSacrament, ...member?.sacraments.baptism },
            confirmation: {
                ...blankSacrament,
                ...member?.sacraments.confirmation,
            },
        },
        group_ids: member?.group_ids ?? ([] as number[]),
        service_records: member?.service_records ?? ([] as ServiceRecord[]),
    });

    const errors = form.errors as Record<string, string | undefined>;

    // Hover text for the town fields: the town's district and region.
    const towns = useGhanaTowns();
    const residenceTooltip = () => {
        const value = form.data.residence.trim().toLowerCase();
        const neighbourhood = residenceArea.neighbourhoods.find(
            (n) => n.toLowerCase() === value,
        );

        return neighbourhood
            ? [
                  `Neighbourhood: ${neighbourhood}`,
                  `City: ${residenceArea.city}`,
                  residenceArea.region
                      ? `Region: ${residenceArea.region} Region`
                      : null,
              ]
                  .filter(Boolean)
                  .join(' · ')
            : townTooltip(towns, form.data.residence);
    };

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

    /**
     * Saves the whole record. `stay` is the Save button on a step: the server brings the form back on this step
     * (a new member comes back as an edit of the saved record) instead of leaving it.
     */
    const save = (stay: boolean) => {
        for (let i = 0; i <= last; i++) {
            if (!stepIsValid(i)) {
                return;
            }
        }

        form.transform((data) => ({
            ...data,
            // A file upload cannot be sent as PUT, so the update is a POST that says it means PUT.
            ...(editing ? { _method: 'put' } : {}),
            ...(stay ? { continue: true, step } : {}),
        }));

        form.post(editing ? update(member.id).url : store().url, {
            preserveScroll: stay,
            onError: (problems) => {
                goTo(Math.min(...Object.keys(problems).map(stepOfError)));
            },
        });
    };

    /**
     * Enter inside a field never saves: before the last step it moves on, on the last step it does nothing. Saving
     * is only ever a click on Save, Save Changes or Register (all type="button", so a click cannot turn into a
     * submit when the button under the pointer changes from Next to Save Changes).
     */
    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (step < last) {
            next();
        }
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
        name: 'sex' | 'marital_status' | 'marriage_type',
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

    const contactField = (name: 'name' | 'phone', label: string) => {
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

    /**
     * Relationship of the next of kin or the emergency contact: a listed one, or "Other" with the relationship typed
     * in. A saved relationship that is not on the list opens as "Other" with its text.
     */
    const otherRelationship = relationships[relationships.length - 1];
    const choiceFor = (saved: string | undefined) =>
        !saved || relationships.includes(saved)
            ? (saved ?? '')
            : otherRelationship;
    const [relationshipChoice, setRelationshipChoice] = useState({
        next_of_kin: choiceFor(member?.next_of_kin?.relationship),
        emergency_contact: choiceFor(member?.emergency_contact?.relationship),
    });

    const relationshipField = (target: 'next_of_kin' | 'emergency_contact') => {
        const choice = relationshipChoice[target];
        const id = `${target}-relationship`;
        const setRelationship = (relationship: string) =>
            form.setData(target, { ...form.data[target], relationship });

        return (
            <div className="grid gap-2">
                <Label htmlFor={id}>Relationship</Label>
                <NativeSelect
                    id={id}
                    value={choice}
                    onChange={(e) => {
                        setRelationshipChoice({
                            ...relationshipChoice,
                            [target]: e.target.value,
                        });
                        // "Other" is not stored; the typed relationship is.
                        setRelationship(
                            e.target.value === otherRelationship
                                ? ''
                                : e.target.value,
                        );
                    }}
                >
                    <option value="">Not stated</option>
                    {relationships.map((r) => (
                        <option key={r} value={r}>
                            {r}
                        </option>
                    ))}
                </NativeSelect>
                {choice === otherRelationship && (
                    <Input
                        aria-label="Relationship, in words"
                        value={form.data[target].relationship}
                        onChange={(e) => setRelationship(e.target.value)}
                        placeholder="e.g. Pastor, Neighbour"
                        maxLength={100}
                        autoFocus
                    />
                )}
                <InputError message={errors[`${target}.relationship`]} />
            </div>
        );
    };

    /** Father or mother: a typed name, or a link to their own member record. */
    const parentField = (which: 'father' | 'mother', label: string) => {
        const linked = parents[which];
        const idField = `${which}_member_id` as const;
        const nameField = `${which}_name` as const;

        return (
            <div className="grid content-start gap-2">
                {linked ? (
                    <>
                        <Label>{label}</Label>
                        <div className="flex items-start justify-between gap-3 rounded-md border bg-muted/40 p-3">
                            <div className="text-sm">
                                <div className="font-medium">
                                    {linked.full_name}
                                </div>
                                <div className="text-muted-foreground">
                                    {linked.member_number} · a member
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setParents({ ...parents, [which]: null });
                                    form.setData(idField, null);
                                }}
                            >
                                <X /> Remove
                            </Button>
                        </div>
                    </>
                ) : (
                    <>
                        {text(nameField, `${label}'s name`)}
                        <Label className="text-xs font-normal text-muted-foreground">
                            Or find the {which} if they are a member
                        </Label>
                        <MemberPicker
                            invalid={Boolean(errors[idField])}
                            onPick={(hit: MemberHit) => {
                                setParents({
                                    ...parents,
                                    [which]: {
                                        id: hit.id,
                                        member_number: hit.member_number,
                                        full_name: hit.full_name,
                                    },
                                });
                                form.setData((data) => ({
                                    ...data,
                                    [idField]: hit.id,
                                    [nameField]: '',
                                }));
                            }}
                        />
                    </>
                )}
                <InputError message={errors[idField]} />
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

        const chosen = presbyteries.find(
            (p) => normalise(p.name) === normalise(value.presbytery),
        );
        const districts = chosen
            ? chosen.districts
            : [...new Set(presbyteries.flatMap((p) => p.districts))].sort();
        const churchKnown = Boolean(
            church.presbytery || church.district || church.congregation,
        );
        const field = (
            name: 'presbytery' | 'district' | 'place',
            label: string,
            list: string,
            placeholder: string,
        ) => (
            <div className="grid gap-2">
                <Label htmlFor={`${kind}-${name}`}>{label}</Label>
                <Input
                    id={`${kind}-${name}`}
                    value={value[name]}
                    onChange={(e) => set(name, e.target.value)}
                    list={list}
                    autoComplete="off"
                    placeholder={placeholder}
                    maxLength={150}
                />
                <InputError message={errors[`sacraments.${kind}.${name}`]} />
            </div>
        );

        return (
            <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-3">
                <div className="flex flex-wrap items-center justify-between gap-2 sm:col-span-3">
                    <h2 className="font-medium">{title}</h2>
                    {churchKnown && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                form.setData('sacraments', {
                                    ...form.data.sacraments,
                                    [kind]: {
                                        ...value,
                                        presbytery: church.presbytery,
                                        district: church.district,
                                        place: church.congregation,
                                    },
                                })
                            }
                        >
                            <Church /> This congregation
                        </Button>
                    )}
                </div>
                {field(
                    'presbytery',
                    'Presbytery',
                    'sacrament-presbyteries',
                    'e.g. Ga West',
                )}
                {field(
                    'district',
                    'District',
                    `${kind}-districts`,
                    chosen ? `A ${chosen.name} district` : 'e.g. Kaneshie',
                )}
                <datalist id={`${kind}-districts`}>
                    {districts.map((d) => (
                        <option key={d} value={d} />
                    ))}
                </datalist>
                {field(
                    'place',
                    'Congregation',
                    'sacrament-congregations',
                    'e.g. Ebenezer Congregation',
                )}
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
                                        onClick={() => goTo(i)}
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
                            {text('place_of_birth', 'Place of birth', {
                                list: 'ghana-towns',
                                autoComplete: 'off',
                                placeholder: 'Town',
                                title: townTooltip(
                                    towns,
                                    form.data.place_of_birth,
                                ),
                            })}
                            {text('hometown', 'Home town', {
                                list: 'ghana-towns',
                                autoComplete: 'off',
                                placeholder: 'Town',
                                title: townTooltip(towns, form.data.hometown),
                            })}
                            <TownSuggestions id="ghana-towns" />
                            <div className="grid gap-2">
                                <Label htmlFor="profession_id">
                                    Occupation / Profession
                                </Label>
                                <NativeSelect
                                    id="profession_id"
                                    value={form.data.profession_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'profession_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Not stated</option>
                                    {occupations.map((group) => (
                                        <optgroup
                                            key={group.category}
                                            label={group.category}
                                        >
                                            {group.options.map((o) => (
                                                <option key={o.id} value={o.id}>
                                                    {o.name}
                                                </option>
                                            ))}
                                        </optgroup>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.profession_id} />
                            </div>
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
                                autoComplete: 'off',
                                title: residenceTooltip(),
                                placeholder: residenceArea.city
                                    ? `Neighbourhood in ${residenceArea.city}`
                                    : 'Neighbourhood or town',
                            })}
                            {/* Places already recorded, then the church city's neighbourhoods, then the towns of its region. */}
                            <TownSuggestions
                                id="residences"
                                extra={[
                                    ...residences,
                                    ...residenceArea.neighbourhoods,
                                ]}
                                region={residenceArea.region}
                            />
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
                            {relationshipField('emergency_contact')}
                        </section>
                    </>,
                )}

                {/* 3. Family */}
                {panel(
                    2,
                    <>
                        <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <h2 className="font-medium">Parents</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Type a name, or find the parent if they are
                                    a member so the records are linked.
                                </p>
                            </div>
                            {parentField('father', 'Father')}
                            {parentField('mother', 'Mother')}
                        </section>

                        <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                            <h2 className="font-medium sm:col-span-2">
                                Marital
                            </h2>
                            {select(
                                'marital_status',
                                'Marital status',
                                maritalStatuses.map((value) => ({
                                    value,
                                    label: statusLabel(value),
                                })),
                            )}
                            {/* Marriage details are recorded for married members only. */}
                            {form.data.marital_status === 'married' ? (
                                <>
                                    {select(
                                        'marriage_type',
                                        'Marriage type',
                                        marriageTypes.map((value) => ({
                                            value,
                                            label: statusLabel(value),
                                        })),
                                    )}
                                    {text('marriage_date', 'Date of marriage', {
                                        type: 'date',
                                        max: new Date()
                                            .toISOString()
                                            .slice(0, 10),
                                    })}
                                    {text(
                                        'marriage_church',
                                        'Church of marriage',
                                        {
                                            list: 'sacrament-congregations',
                                            autoComplete: 'off',
                                            placeholder:
                                                'Where the marriage took place',
                                            maxLength: 150,
                                        },
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
                                                            {
                                                                spouse.member_number
                                                            }{' '}
                                                            · a member
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
                                                {text(
                                                    'spouse_name',
                                                    'Spouse name',
                                                )}
                                                <Label className="text-xs font-normal text-muted-foreground">
                                                    Or find the spouse if they
                                                    are a member
                                                </Label>
                                                <MemberPicker
                                                    invalid={Boolean(
                                                        errors.spouse_member_id,
                                                    )}
                                                    onPick={(
                                                        hit: MemberHit,
                                                    ) => {
                                                        setSpouse({
                                                            id: hit.id,
                                                            member_number:
                                                                hit.member_number,
                                                            full_name:
                                                                hit.full_name,
                                                        });
                                                        form.setData(
                                                            'spouse_member_id',
                                                            hit.id,
                                                        );
                                                    }}
                                                />
                                            </>
                                        )}
                                        <InputError
                                            message={errors.spouse_member_id}
                                        />
                                    </div>
                                </>
                            ) : (
                                <p className="self-end pb-2 text-sm text-muted-foreground">
                                    Choose Married to record marriage details.
                                </p>
                            )}
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
                            {relationshipField('next_of_kin')}
                            {kin(
                                'residential_address',
                                'Residential address',
                                true,
                            )}
                            {kin('postal_address', 'Postal address', true)}
                        </section>
                    </>,
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
                            {text(
                                'previous_congregation',
                                'Previous congregation',
                                {
                                    list: 'previous-congregations',
                                    autoComplete: 'off',
                                    placeholder: 'Where the member came from',
                                    maxLength: 150,
                                },
                            )}
                            <datalist id="previous-congregations">
                                {previousCongregations.map((c) => (
                                    <option key={c} value={c} />
                                ))}
                            </datalist>
                            <div className="grid gap-2">
                                <Label>Generational group</Label>
                                <p className="flex h-9 items-center rounded-md border bg-muted/40 px-3 text-sm">
                                    {generationalGroupFor(
                                        form.data.date_of_birth,
                                        form.data.sex,
                                        generationalGroups,
                                    ) ?? (
                                        <span className="text-muted-foreground">
                                            Set once date of birth and sex are
                                            entered
                                        </span>
                                    )}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Set from age and sex: 18–29 YPG, 30–39 YAF,
                                    40 and over Men&apos;s or Women&apos;s
                                    Fellowship.
                                </p>
                            </div>
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
                                    Executive, Session and committee posts held.
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
                                            // The positions differ by type, so the old one is cleared.
                                            changeService(i, {
                                                type: e.target.value,
                                                position: '',
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
                                        {record.type === 'executive'
                                            ? 'Group'
                                            : record.type === 'committee'
                                              ? 'Name of committee'
                                              : 'Name of body'}
                                    </Label>
                                    {record.type === 'leadership' ? (
                                        <Input
                                            id={`service-name-${i}`}
                                            value={record.name}
                                            onChange={(e) =>
                                                changeService(i, {
                                                    name: e.target.value,
                                                })
                                            }
                                            placeholder="e.g. Session"
                                            required
                                        />
                                    ) : (
                                        <ChoiceOrOther
                                            key={`name-${i}-${record.type}`}
                                            id={`service-name-${i}`}
                                            value={record.name}
                                            options={
                                                record.type === 'executive'
                                                    ? executiveGroups
                                                    : committees
                                            }
                                            onChange={(name) =>
                                                changeService(i, { name })
                                            }
                                            placeholder={
                                                record.type === 'executive'
                                                    ? 'The group served'
                                                    : 'The committee'
                                            }
                                            required
                                        />
                                    )}
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
                                    <ChoiceOrOther
                                        key={`position-${i}-${record.type}`}
                                        id={`service-position-${i}`}
                                        value={record.position}
                                        options={positions[record.type] ?? []}
                                        onChange={(position) =>
                                            changeService(i, { position })
                                        }
                                        placeholder="The position held"
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
                        <datalist id="sacrament-presbyteries">
                            {presbyteries.map((p) => (
                                <option key={p.name} value={p.name} />
                            ))}
                        </datalist>
                        <datalist id="sacrament-congregations">
                            {[
                                ...new Set(
                                    [
                                        church.congregation,
                                        ...congregations,
                                    ].filter(Boolean),
                                ),
                            ].map((c) => (
                                <option key={c} value={c} />
                            ))}
                        </datalist>
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
                        {step < last && (
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={form.processing}
                                onClick={() => save(true)}
                                title="Save what is entered so far and stay on this step"
                            >
                                <Save /> Save
                            </Button>
                        )}
                        {step < last ? (
                            <Button key="next" type="button" onClick={next}>
                                Next
                            </Button>
                        ) : (
                            <Button
                                key="finish"
                                type="button"
                                disabled={form.processing}
                                onClick={() => save(false)}
                            >
                                {editing ? 'Save Changes' : 'Register'}
                            </Button>
                        )}
                    </div>
                </div>
            </form>
        </>
    );
}
