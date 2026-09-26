import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { MemberPicker } from '@/components/member-picker';
import { PageHeader } from '@/components/page-header';
import { PersonField } from '@/components/person-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/speaking';

type Person = { id: number; full_name: string; member_number: string };

type Note = {
    id: number;
    member_id: number;
    member: Person;
    communion_service_id: number | null;
    spoken_on: string;
    spoken_by_member_id: number | '';
    spoken_by: { full_name: string; member_number: string } | null;
    spoken_by_name: string;
    outcome: string;
    notes: string;
} | null;

type Props = {
    note: Note;
    presetMember: Person | null;
    presetService: number | null;
    services: { id: number; title: string; held_on: string }[];
    outcomes: Record<string, string>;
};

export default function SpeakingForm({
    note,
    presetMember,
    presetService,
    services,
    outcomes,
}: Props) {
    const start = note?.member ?? presetMember;
    const [member, setMember] = useState<Person | null>(start);

    const form = useForm({
        member_id: (note?.member_id ?? presetMember?.id ?? '') as number | '',
        communion_service_id: String(
            note?.communion_service_id ??
                presetService ??
                services[0]?.id ??
                '',
        ),
        spoken_on: note?.spoken_on ?? new Date().toISOString().slice(0, 10),
        spoken_by_member_id: (note?.spoken_by_member_id ?? '') as number | '',
        spoken_by_name: note?.spoken_by_name ?? '',
        outcome: note?.outcome ?? 'cleared',
        notes: note?.notes ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (note) {
            form.put(update(note.id).url);
        } else {
            form.post(store().url);
        }
    };

    return (
        <>
            <Head title={note ? 'Edit speaking note' : 'New speaking note'} />

            <form onSubmit={submit} className="max-w-2xl space-y-5 p-4">
                <PageHeader
                    title={note ? 'Edit speaking note' : 'New speaking note'}
                    description="Confidential. Only people with the Speaking permission can read this."
                />

                <div className="grid gap-2">
                    <Label>Member *</Label>
                    {member ? (
                        <p className="flex flex-wrap items-center gap-2 text-sm">
                            <span className="font-medium">
                                {member.full_name}
                            </span>
                            <span className="text-muted-foreground">
                                {member.member_number}
                            </span>
                            {!note && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setMember(null);
                                        form.setData('member_id', '');
                                    }}
                                >
                                    Change
                                </Button>
                            )}
                        </p>
                    ) : (
                        <MemberPicker
                            invalid={Boolean(form.errors.member_id)}
                            onPick={(m) => {
                                setMember({
                                    id: m.id,
                                    full_name: m.full_name,
                                    member_number: m.member_number,
                                });
                                form.setData('member_id', m.id);
                            }}
                        />
                    )}
                    <InputError message={form.errors.member_id} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="communion_service_id">
                            Communion service *
                        </Label>
                        <NativeSelect
                            id="communion_service_id"
                            value={form.data.communion_service_id}
                            onChange={(e) =>
                                form.setData(
                                    'communion_service_id',
                                    e.target.value,
                                )
                            }
                        >
                            {services.length === 0 && (
                                <option value="">
                                    Create a communion service first
                                </option>
                            )}
                            {services.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.title} ({s.held_on})
                                </option>
                            ))}
                        </NativeSelect>
                        <InputError
                            message={form.errors.communion_service_id}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="spoken_on">Date spoken *</Label>
                        <Input
                            id="spoken_on"
                            type="date"
                            max={new Date().toISOString().slice(0, 10)}
                            value={form.data.spoken_on}
                            onChange={(e) =>
                                form.setData('spoken_on', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.spoken_on} />
                    </div>
                </div>

                <PersonField
                    id="spoken_by"
                    label="Spoken to by"
                    value={{
                        member_id: form.data.spoken_by_member_id,
                        name: form.data.spoken_by_name,
                    }}
                    shown={note?.spoken_by ?? null}
                    onChange={(v) => {
                        form.setData('spoken_by_member_id', v.member_id);
                        form.setData('spoken_by_name', v.name);
                    }}
                    error={
                        form.errors.spoken_by_member_id ??
                        form.errors.spoken_by_name
                    }
                />

                <div className="grid gap-2">
                    <Label htmlFor="outcome">Outcome *</Label>
                    <NativeSelect
                        id="outcome"
                        value={form.data.outcome}
                        onChange={(e) =>
                            form.setData('outcome', e.target.value)
                        }
                    >
                        {Object.entries(outcomes).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </NativeSelect>
                    <InputError message={form.errors.outcome} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="notes">Notes *</Label>
                    <Textarea
                        id="notes"
                        rows={8}
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        maxLength={10000}
                        required
                    />
                    <InputError message={form.errors.notes} />
                </div>

                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Save
                    </Button>
                    <Button asChild variant="ghost">
                        <Link href={note ? show(note.id) : index()}>
                            Cancel
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

SpeakingForm.layout = {
    breadcrumbs: [{ title: 'Speaking', href: index() }],
};
