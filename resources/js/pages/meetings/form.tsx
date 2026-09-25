import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { ChoiceOrOther } from '@/components/choice-or-other';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PersonField } from '@/components/person-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/meetings';

type Shown = { member_number: string; full_name: string } | null;

type MeetingData = {
    id: number;
    committee_id: number;
    title: string | null;
    meeting_date: string;
    starts_at: string | null;
    ends_at: string | null;
    venue: string | null;
    chairperson_member_id: number | null;
    chairperson_name: string | null;
    chairperson: Shown;
    secretary_member_id: number | null;
    secretary_name: string | null;
    secretary: Shown;
    agenda: string | null;
} | null;

type Props = {
    meeting: MeetingData;
    date: string;
    committeeId: number | null;
    committees: { id: number; name: string }[];
    venues: string[];
};

export default function MeetingForm({
    meeting,
    date,
    committeeId,
    committees,
    venues,
}: Props) {
    const editing = meeting !== null;
    const form = useForm({
        committee_id:
            meeting?.committee_id ?? committeeId ?? ('' as number | ''),
        title: meeting?.title ?? '',
        meeting_date: meeting?.meeting_date ?? date,
        starts_at: meeting?.starts_at ?? '',
        ends_at: meeting?.ends_at ?? '',
        venue: meeting?.venue ?? '',
        chairperson_member_id: (meeting?.chairperson_member_id ?? '') as
            | number
            | '',
        chairperson_name: meeting?.chairperson_name ?? '',
        secretary_member_id: (meeting?.secretary_member_id ?? '') as
            | number
            | '',
        secretary_name: meeting?.secretary_name ?? '',
        agenda: meeting?.agenda ?? '',
        repeat: 'none',
        repeat_until: '',
    });
    const { data, setData, errors } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (meeting) {
            form.put(update(meeting.id).url);
        } else {
            form.post(store().url);
        }
    };

    return (
        <>
            <Head title={editing ? 'Edit Meeting' : 'Add Meeting'} />

            <form onSubmit={submit} className="max-w-4xl space-y-6 p-4">
                <PageHeader
                    title={editing ? 'Edit Meeting' : 'Add a Meeting'}
                />

                <Section title="The meeting">
                    <Field
                        id="committee_id"
                        label="Committee"
                        error={errors.committee_id}
                    >
                        <NativeSelect
                            id="committee_id"
                            value={data.committee_id}
                            onChange={(e) =>
                                setData(
                                    'committee_id',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                            required
                        >
                            <option value="">Select…</option>
                            {committees.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </NativeSelect>
                        <p className="text-xs text-muted-foreground">
                            To hold a meeting for Session or another body, add
                            it on the Committees page first.
                        </p>
                    </Field>
                    <Field
                        id="title"
                        label="Title (optional)"
                        error={errors.title}
                    >
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            maxLength={200}
                            placeholder="For example: Special meeting on the roof"
                        />
                    </Field>
                    <Field
                        id="meeting_date"
                        label="Date"
                        error={errors.meeting_date}
                    >
                        <Input
                            id="meeting_date"
                            type="date"
                            value={data.meeting_date}
                            onChange={(e) =>
                                setData('meeting_date', e.target.value)
                            }
                            required
                        />
                    </Field>
                    <div className="grid grid-cols-2 gap-4">
                        <Field
                            id="starts_at"
                            label="Start time"
                            error={errors.starts_at}
                        >
                            <Input
                                id="starts_at"
                                type="time"
                                value={data.starts_at}
                                onChange={(e) =>
                                    setData('starts_at', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="ends_at"
                            label="End time"
                            error={errors.ends_at}
                        >
                            <Input
                                id="ends_at"
                                type="time"
                                value={data.ends_at}
                                onChange={(e) =>
                                    setData('ends_at', e.target.value)
                                }
                            />
                        </Field>
                    </div>
                    <Field id="venue" label="Venue" error={errors.venue} wide>
                        <ChoiceOrOther
                            id="venue"
                            value={data.venue}
                            options={venues}
                            onChange={(v) => setData('venue', v)}
                            placeholder="The venue"
                        />
                    </Field>
                </Section>

                {!editing && (
                    <section className="space-y-4 rounded-lg border p-5">
                        <h2 className="font-medium">Repeat</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid content-start gap-2">
                                <Label htmlFor="repeat">Repeats</Label>
                                <NativeSelect
                                    id="repeat"
                                    value={data.repeat}
                                    onChange={(e) =>
                                        setData('repeat', e.target.value)
                                    }
                                >
                                    <option value="none">
                                        Does not repeat
                                    </option>
                                    <option value="weekly">Every week</option>
                                    <option value="fortnightly">
                                        Every two weeks
                                    </option>
                                    <option value="monthly">Every month</option>
                                </NativeSelect>
                            </div>
                            {data.repeat !== 'none' && (
                                <div className="grid content-start gap-2">
                                    <Label htmlFor="repeat_until">Until</Label>
                                    <Input
                                        id="repeat_until"
                                        type="date"
                                        min={data.meeting_date}
                                        value={data.repeat_until}
                                        onChange={(e) =>
                                            setData(
                                                'repeat_until',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError message={errors.repeat_until} />
                                </div>
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Each date is created on its own, so any one can be
                            changed, cancelled or deleted later.
                        </p>
                    </section>
                )}

                <Section title="Officers">
                    <PersonField
                        id="chairperson"
                        label="Chairperson"
                        value={{
                            member_id: data.chairperson_member_id,
                            name: data.chairperson_name,
                        }}
                        shown={meeting?.chairperson}
                        onChange={(v) =>
                            setData((d) => ({
                                ...d,
                                chairperson_member_id: v.member_id,
                                chairperson_name: v.name,
                            }))
                        }
                        error={
                            errors.chairperson_member_id ??
                            errors.chairperson_name
                        }
                    />
                    <PersonField
                        id="secretary"
                        label="Secretary"
                        value={{
                            member_id: data.secretary_member_id,
                            name: data.secretary_name,
                        }}
                        shown={meeting?.secretary}
                        onChange={(v) =>
                            setData((d) => ({
                                ...d,
                                secretary_member_id: v.member_id,
                                secretary_name: v.name,
                            }))
                        }
                        error={
                            errors.secretary_member_id ?? errors.secretary_name
                        }
                    />
                </Section>

                <Section title="Agenda">
                    <Field
                        id="agenda"
                        label="Agenda"
                        error={errors.agenda}
                        wide
                    >
                        <Textarea
                            id="agenda"
                            rows={8}
                            value={data.agenda}
                            onChange={(e) => setData('agenda', e.target.value)}
                            maxLength={10000}
                            placeholder={
                                '1. Opening prayer\n2. Minutes of the last meeting\n3. Matters arising'
                            }
                        />
                    </Field>
                </Section>

                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save' : 'Add Meeting'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={meeting ? show(meeting.id) : index()}>
                            Cancel
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-4 rounded-lg border p-5">
            <h2 className="font-medium">{title}</h2>
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

MeetingForm.layout = {
    breadcrumbs: [{ title: 'Meetings', href: index() }],
};
