import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { ChoiceOrOther } from '@/components/choice-or-other';
import InputError from '@/components/input-error';
import { MemberPicker } from '@/components/member-picker';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/events';

type EventData = {
    id: number;
    title: string;
    description: string | null;
    purpose: string | null;
    host_type: 'church' | 'group' | 'committee';
    member_group_id: number | null;
    committee_id: number | null;
    scope: 'internal' | 'external';
    visibility: 'public' | 'private';
    is_all_day: boolean;
    starts_on: string;
    ends_on: string;
    starts_at: string | null;
    ends_at: string | null;
    venue: string | null;
    organizer_member_id: number | null;
    organizer_name: string | null;
    organizer: { id: number; member_number: string; full_name: string } | null;
    notes: string | null;
} | null;

type Props = {
    event: EventData;
    date: string;
    hosts: Record<string, string>;
    scopes: Record<string, string>;
    visibilities: Record<string, string>;
    venues: string[];
    groups: { id: number; name: string }[];
    committees: { id: number; name: string }[];
};

export default function EventForm({
    event,
    date,
    hosts,
    scopes,
    visibilities,
    venues,
    groups,
    committees,
}: Props) {
    const editing = event !== null;
    const form = useForm({
        title: event?.title ?? '',
        description: event?.description ?? '',
        purpose: event?.purpose ?? '',
        host_type: event?.host_type ?? 'church',
        member_group_id: event?.member_group_id
            ? String(event.member_group_id)
            : '',
        committee_id: event?.committee_id ? String(event.committee_id) : '',
        scope: event?.scope ?? 'internal',
        visibility: event?.visibility ?? 'public',
        starts_on: event?.starts_on ?? date,
        ends_on: event?.ends_on ?? '',
        is_all_day: event?.is_all_day ?? false,
        starts_at: event?.starts_at ?? '',
        ends_at: event?.ends_at ?? '',
        venue: event?.venue ?? '',
        organizer_member_id: (event?.organizer_member_id ?? '') as number | '',
        organizer_name: event?.organizer_name ?? '',
        notes: event?.notes ?? '',
        repeat: 'none',
        repeat_until: '',
    });
    const { data, setData, errors } = form;
    const [organizer, setOrganizer] = useState<{
        member_number: string;
        full_name: string;
    } | null>(event?.organizer ?? null);

    const set =
        (name: keyof typeof data) => (e: { target: { value: string } }) =>
            setData(name, e.target.value as never);

    const submit = (submitEvent: FormEvent) => {
        submitEvent.preventDefault();

        if (event) {
            form.put(update(event.id).url);
        } else {
            form.post(store().url);
        }
    };

    return (
        <>
            <Head title={editing ? 'Edit Event' : 'Add Event'} />

            <form onSubmit={submit} className="max-w-4xl space-y-6 p-4">
                <PageHeader title={editing ? 'Edit Event' : 'Add an Event'} />

                <Section title="The event">
                    <Field id="title" label="Title" error={errors.title} wide>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={set('title')}
                            maxLength={200}
                            required
                        />
                    </Field>
                    <Field
                        id="purpose"
                        label="Purpose"
                        error={errors.purpose}
                        wide
                    >
                        <Input
                            id="purpose"
                            value={data.purpose}
                            onChange={set('purpose')}
                            maxLength={250}
                            placeholder="Why it is being held"
                        />
                    </Field>
                    <Field
                        id="description"
                        label="Description"
                        error={errors.description}
                        wide
                    >
                        <Textarea
                            id="description"
                            rows={4}
                            value={data.description}
                            onChange={set('description')}
                            maxLength={5000}
                        />
                    </Field>
                </Section>

                <Section title="Host and type">
                    <Field id="host_type" label="Host" error={errors.host_type}>
                        <NativeSelect
                            id="host_type"
                            value={data.host_type}
                            onChange={set('host_type')}
                        >
                            {Object.entries(hosts).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    {data.host_type === 'group' && (
                        <Field
                            id="member_group_id"
                            label="Group"
                            error={errors.member_group_id}
                        >
                            <NativeSelect
                                id="member_group_id"
                                value={data.member_group_id}
                                onChange={set('member_group_id')}
                                required
                            >
                                <option value="">Select…</option>
                                {groups.map((g) => (
                                    <option key={g.id} value={g.id}>
                                        {g.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                    )}
                    {data.host_type === 'committee' && (
                        <Field
                            id="committee_id"
                            label="Committee"
                            error={errors.committee_id}
                        >
                            <NativeSelect
                                id="committee_id"
                                value={data.committee_id}
                                onChange={set('committee_id')}
                                required
                            >
                                <option value="">Select…</option>
                                {committees.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                    )}
                    <Field
                        id="scope"
                        label="Internal or external"
                        error={errors.scope}
                    >
                        <NativeSelect
                            id="scope"
                            value={data.scope}
                            onChange={set('scope')}
                        >
                            {Object.entries(scopes).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </NativeSelect>
                        <p className="text-xs text-muted-foreground">
                            External: held by or with another church or body.
                        </p>
                    </Field>
                    <Field
                        id="visibility"
                        label="Public or private"
                        error={errors.visibility}
                    >
                        <NativeSelect
                            id="visibility"
                            value={data.visibility}
                            onChange={set('visibility')}
                        >
                            {Object.entries(visibilities).map(
                                ([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </NativeSelect>
                        <p className="text-xs text-muted-foreground">
                            Public: open to visitors. Private: members or
                            invited people only.
                        </p>
                    </Field>
                </Section>

                <Section title="When and where">
                    <Field
                        id="starts_on"
                        label="Starts on"
                        error={errors.starts_on}
                    >
                        <Input
                            id="starts_on"
                            type="date"
                            value={data.starts_on}
                            onChange={set('starts_on')}
                            required
                        />
                    </Field>
                    <Field id="ends_on" label="Ends on" error={errors.ends_on}>
                        <Input
                            id="ends_on"
                            type="date"
                            min={data.starts_on}
                            value={data.ends_on}
                            onChange={set('ends_on')}
                        />
                        <p className="text-xs text-muted-foreground">
                            Leave empty for a one-day event.
                        </p>
                    </Field>
                    <label className="flex items-center gap-2 text-sm sm:col-span-2">
                        <input
                            type="checkbox"
                            checked={data.is_all_day}
                            onChange={(e) =>
                                setData('is_all_day', e.target.checked)
                            }
                        />
                        All day
                    </label>
                    {!data.is_all_day && (
                        <>
                            <Field
                                id="starts_at"
                                label="Start time"
                                error={errors.starts_at}
                            >
                                <Input
                                    id="starts_at"
                                    type="time"
                                    value={data.starts_at}
                                    onChange={set('starts_at')}
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
                                    onChange={set('ends_at')}
                                />
                            </Field>
                        </>
                    )}
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
                                        min={data.starts_on}
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

                <Section title="Organizer">
                    <div className="grid gap-2 sm:col-span-2">
                        {organizer ? (
                            <p className="flex flex-wrap items-center gap-2 text-sm">
                                <span className="font-medium">
                                    {organizer.full_name}
                                </span>
                                <span className="text-muted-foreground">
                                    {organizer.member_number}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setOrganizer(null);
                                        setData('organizer_member_id', '');
                                    }}
                                >
                                    Change
                                </Button>
                            </p>
                        ) : (
                            <>
                                <Label>A member of the church</Label>
                                <MemberPicker
                                    invalid={Boolean(
                                        errors.organizer_member_id,
                                    )}
                                    onPick={(member) => {
                                        setOrganizer(member);
                                        setData((current) => ({
                                            ...current,
                                            organizer_member_id: member.id,
                                            organizer_name: '',
                                        }));
                                    }}
                                />
                                <Label
                                    htmlFor="organizer_name"
                                    className="mt-2"
                                >
                                    Or someone outside the church
                                </Label>
                                <Input
                                    id="organizer_name"
                                    value={data.organizer_name}
                                    onChange={set('organizer_name')}
                                    maxLength={150}
                                    placeholder="Name"
                                />
                            </>
                        )}
                        <InputError
                            message={
                                errors.organizer_member_id ??
                                errors.organizer_name
                            }
                        />
                    </div>
                </Section>

                <Section title="Notes">
                    <Field id="notes" label="Notes" error={errors.notes} wide>
                        <Textarea
                            id="notes"
                            rows={3}
                            value={data.notes}
                            onChange={set('notes')}
                            maxLength={5000}
                        />
                    </Field>
                </Section>

                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save' : 'Add Event'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={event ? show(event.id) : index()}>
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

EventForm.layout = {
    breadcrumbs: [{ title: 'Events', href: index() }],
};
