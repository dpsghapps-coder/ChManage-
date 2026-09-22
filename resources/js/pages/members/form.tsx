import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { GpsCapture } from '@/components/gps-capture';
import { MemberPicker as MemberSearch } from '@/components/member-picker';
import type { MemberHit } from '@/components/member-picker';
import { PersonAvatar } from '@/components/person-avatar';
import { PhotoPicker } from '@/components/photo-picker';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { digitsOnly, phoneInputProps } from '@/lib/phone';
import { index, store } from '@/routes/members';
import { update } from '@/routes/members/young';

type Guardian = {
    key: number;
    relationship: string;
    relationship_other: string;
    is_member: boolean;
    member: MemberHit | null;
    name: string;
    phone: string;
    is_primary: boolean;
};

type GuardianPayload = {
    relationship: string;
    relationship_other: string;
    is_member: boolean;
    member_id: number | null;
    name: string;
    phone: string;
    is_primary: boolean;
};

type Child = {
    id: number;
    first_name: string | null;
    last_name: string | null;
    other_names: string | null;
    date_of_birth: string | null;
    joined_on: string | null;
    mobile: string | null;
    telephone: string | null;
    photo_url: string | null;
    latitude: number | null;
    longitude: number | null;
    location_accuracy: number | null;
    guardians: Omit<Guardian, 'key'>[];
};

type Props = {
    /** The record being edited, or null when registering a new one. */
    child: Child | null;
    relationships: { value: string; label: string }[];
};

let nextKey = 1;

const newGuardian = (isPrimary: boolean): Guardian => ({
    key: nextKey++,
    relationship: 'mother',
    relationship_other: '',
    is_member: false,
    member: null,
    name: '',
    phone: '',
    is_primary: isPrimary,
});

export default function MemberForm({ relationships, child }: Props) {
    const editing = child !== null;
    const [guardians, setGuardians] = useState<Guardian[]>(() =>
        child && child.guardians.length
            ? child.guardians.map((g) => ({ ...g, key: nextKey++ }))
            : [newGuardian(true)],
    );

    const form = useForm({
        first_name: child?.first_name ?? '',
        last_name: child?.last_name ?? '',
        other_names: child?.other_names ?? '',
        date_of_birth: child?.date_of_birth ?? '',
        joined_on: child?.joined_on ?? '',
        mobile: child?.mobile ?? '',
        telephone: child?.telephone ?? '',
        photo: null as File | null,
        latitude: (child?.latitude ?? null) as number | null,
        longitude: (child?.longitude ?? null) as number | null,
        location_accuracy: (child?.location_accuracy ?? null) as number | null,
        guardians: [] as GuardianPayload[],
    });

    const errors = form.errors as Record<string, string | undefined>;

    const change = (key: number, changes: Partial<Guardian>) =>
        setGuardians((list) =>
            list.map((g) => (g.key === key ? { ...g, ...changes } : g)),
        );

    const markPrimary = (key: number) =>
        setGuardians((list) =>
            list.map((g) => ({ ...g, is_primary: g.key === key })),
        );

    const remove = (key: number) =>
        setGuardians((list) => {
            const rest = list.filter((g) => g.key !== key);

            // The primary contact went: the first guardian left takes over.
            return rest.length && !rest.some((g) => g.is_primary)
                ? rest.map((g, i) => ({ ...g, is_primary: i === 0 }))
                : rest;
        });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            // A file upload cannot be sent as PUT, so the update is a POST that says it means PUT.
            ...(editing ? { _method: 'put' } : {}),
            guardians: guardians.map((g) => ({
                relationship: g.relationship,
                relationship_other: g.relationship_other,
                is_member: g.is_member,
                member_id: g.is_member ? (g.member?.id ?? null) : null,
                name: g.name,
                phone: g.phone,
                is_primary: g.is_primary,
            })),
        }));
        form.post(editing ? update(child.id).url : store().url);
    };

    const text = (
        name:
            | 'first_name'
            | 'last_name'
            | 'other_names'
            | 'date_of_birth'
            | 'joined_on'
            | 'mobile'
            | 'telephone',
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

    return (
        <>
            <Head
                title={
                    editing
                        ? 'Edit Junior Youth / Child'
                        : 'Register Junior Youth / Child'
                }
            />

            <form onSubmit={submit} className="max-w-4xl space-y-6 p-4">
                <PageHeader
                    title={
                        editing
                            ? 'Edit Junior Youth / Child'
                            : 'Register Junior Youth / Child'
                    }
                />

                <section className="grid gap-5 rounded-lg border p-5">
                    <h2 className="font-medium">Photo and Location</h2>
                    <PhotoPicker
                        value={form.data.photo}
                        onChange={(photo) => form.setData('photo', photo)}
                        currentUrl={child?.photo_url}
                        error={form.errors.photo}
                    />
                    <div className="grid gap-2">
                        <span className="text-sm font-medium">
                            Home Location
                        </span>
                        <GpsCapture
                            value={
                                form.data.latitude !== null &&
                                form.data.longitude !== null
                                    ? {
                                          latitude: form.data.latitude,
                                          longitude: form.data.longitude,
                                          accuracy: form.data.location_accuracy,
                                      }
                                    : null
                            }
                            onChange={(fix) =>
                                form.setData((data) => ({
                                    ...data,
                                    latitude: fix?.latitude ?? null,
                                    longitude: fix?.longitude ?? null,
                                    location_accuracy: fix?.accuracy ?? null,
                                }))
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            Optional. Best captured at the child’s home.
                        </p>
                        {(form.errors.latitude ?? form.errors.longitude) && (
                            <p className="text-sm text-destructive">
                                {form.errors.latitude ?? form.errors.longitude}
                            </p>
                        )}
                    </div>
                </section>

                <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-3">
                    <h2 className="font-medium sm:col-span-3">Member</h2>
                    {text('first_name', 'First name', { required: true })}
                    {text('last_name', 'Surname', { required: true })}
                    {text('other_names', 'Other names')}
                    {text('date_of_birth', 'Date of birth', {
                        type: 'date',
                        required: true,
                    })}
                    {text('joined_on', 'Date joined', { type: 'date' })}
                </section>

                <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <h2 className="font-medium">Contact</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            The member’s own numbers. Guardians’ numbers go on
                            their cards below.
                        </p>
                    </div>
                    {text('mobile', 'Contact no.')}
                    {text('telephone', 'Contact no. 2')}
                </section>

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="font-medium">Guardians</h2>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setGuardians((list) => [
                                    ...list,
                                    newGuardian(false),
                                ])
                            }
                        >
                            <Plus /> Add Guardian
                        </Button>
                    </div>
                    <InputError message={errors.guardians} />

                    {guardians.map((guardian, i) => (
                        <div
                            key={guardian.key}
                            className="grid gap-4 rounded-lg border p-5 sm:grid-cols-2"
                        >
                            <div className="flex items-center justify-between sm:col-span-2">
                                <span className="text-sm font-medium text-muted-foreground">
                                    Guardian {i + 1}
                                </span>
                                {guardians.length > 1 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Remove guardian ${i + 1}`}
                                        onClick={() => remove(guardian.key)}
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`relationship-${guardian.key}`}>
                                    Type of guardian
                                </Label>
                                <NativeSelect
                                    id={`relationship-${guardian.key}`}
                                    value={guardian.relationship}
                                    onChange={(e) =>
                                        change(guardian.key, {
                                            relationship: e.target.value,
                                        })
                                    }
                                >
                                    {relationships.map((r) => (
                                        <option key={r.value} value={r.value}>
                                            {r.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <InputError
                                    message={
                                        errors[`guardians.${i}.relationship`]
                                    }
                                />
                            </div>

                            {guardian.relationship === 'other' && (
                                <div className="grid gap-2">
                                    <Label htmlFor={`other-${guardian.key}`}>
                                        Relationship
                                    </Label>
                                    <Input
                                        id={`other-${guardian.key}`}
                                        value={guardian.relationship_other}
                                        onChange={(e) =>
                                            change(guardian.key, {
                                                relationship_other:
                                                    e.target.value,
                                            })
                                        }
                                        placeholder="e.g. Family friend"
                                    />
                                    <InputError
                                        message={
                                            errors[
                                                `guardians.${i}.relationship_other`
                                            ]
                                        }
                                    />
                                </div>
                            )}

                            <label className="flex items-center gap-2 text-sm font-medium sm:col-span-2">
                                <input
                                    type="checkbox"
                                    checked={guardian.is_member}
                                    onChange={(e) =>
                                        change(guardian.key, {
                                            is_member: e.target.checked,
                                            member: null,
                                        })
                                    }
                                    className="size-4 rounded border"
                                />
                                Guardian is a member of the church
                            </label>

                            {guardian.is_member ? (
                                <div className="grid gap-2 sm:col-span-2">
                                    {guardian.member ? (
                                        <div className="flex items-start justify-between gap-3 rounded-md border bg-muted/40 p-3">
                                            <PersonAvatar
                                                name={guardian.member.full_name}
                                                photoUrl={
                                                    guardian.member.photo_url
                                                }
                                                className="size-14"
                                            />
                                            <div className="flex-1">
                                                <div className="font-medium">
                                                    {guardian.member.full_name}
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    {
                                                        guardian.member
                                                            .member_number
                                                    }
                                                </div>
                                                <div className="text-sm">
                                                    {guardian.member.phones
                                                        .length
                                                        ? guardian.member.phones.join(
                                                              ' · ',
                                                          )
                                                        : 'No phone on file'}
                                                </div>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    change(guardian.key, {
                                                        member: null,
                                                    })
                                                }
                                            >
                                                <X /> Change
                                            </Button>
                                        </div>
                                    ) : (
                                        <MemberSearch
                                            invalid={Boolean(
                                                errors[
                                                    `guardians.${i}.member_id`
                                                ],
                                            )}
                                            onPick={(member) =>
                                                change(guardian.key, { member })
                                            }
                                        />
                                    )}
                                    <InputError
                                        message={
                                            errors[`guardians.${i}.member_id`]
                                        }
                                    />
                                </div>
                            ) : (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor={`name-${guardian.key}`}>
                                            Name
                                        </Label>
                                        <Input
                                            id={`name-${guardian.key}`}
                                            value={guardian.name}
                                            onChange={(e) =>
                                                change(guardian.key, {
                                                    name: e.target.value,
                                                })
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[`guardians.${i}.name`]
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`phone-${guardian.key}`}
                                        >
                                            Contact no.
                                        </Label>
                                        <Input
                                            id={`phone-${guardian.key}`}
                                            {...phoneInputProps}
                                            value={guardian.phone}
                                            onChange={(e) =>
                                                change(guardian.key, {
                                                    phone: digitsOnly(
                                                        e.target.value,
                                                    ),
                                                })
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[`guardians.${i}.phone`]
                                            }
                                        />
                                    </div>
                                </>
                            )}

                            <label className="flex items-center gap-2 text-sm font-medium sm:col-span-2">
                                <input
                                    type="radio"
                                    name="primary_guardian"
                                    checked={guardian.is_primary}
                                    onChange={() => markPrimary(guardian.key)}
                                    className="size-4 rounded border"
                                />
                                Primary contact (only one)
                            </label>
                        </div>
                    ))}
                </section>

                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save Changes' : 'Register'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={index()}>Cancel</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}
