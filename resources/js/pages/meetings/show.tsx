import { Head, Link, router, useForm } from '@inertiajs/react';
import { Ban, CheckCheck, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { ActionDialog } from '@/components/meeting-action-dialog';
import {
    ActionBadge,
    MeetingStatusBadge,
    MeetingsNav,
    deadlineText,
    shortDate,
} from '@/components/meetings-nav';
import type { ActionRow } from '@/components/meetings-nav';
import InputError from '@/components/input-error';
import { MemberPicker } from '@/components/member-picker';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { show as showMember } from '@/routes/members';
import { destroy, edit, index, minutes, status } from '@/routes/meetings';
import {
    destroy as removeAttendee,
    store as addAttendee,
    update as setAttendance,
} from '@/routes/meetings/attendees';
import { store as addDecision } from '@/routes/meetings/decisions';
import {
    destroy as removeDecision,
    update as updateDecision,
} from '@/routes/decisions';

type Meeting = {
    id: number;
    heading: string;
    committee: string;
    meeting_date: string;
    starts_at: string | null;
    ends_at: string | null;
    venue: string | null;
    status: 'scheduled' | 'held' | 'cancelled';
    chairperson: string | null;
    chairperson_member_id: number | null;
    secretary: string | null;
    secretary_member_id: number | null;
    agenda: string | null;
    minutes: string | null;
    minutes_status: 'draft' | 'confirmed';
    minutes_confirmed_on: string | null;
    confirmed_at: { id: number; date: string } | null;
};

type Attendee = {
    id: number;
    member_id: number | null;
    name: string;
    attendance: string;
};

type Decision = {
    id: number;
    kind: string;
    text: string;
    actions: ActionRow[];
};

type Props = {
    meeting: Meeting;
    attendees: Attendee[];
    decisions: Decision[];
    otherMeetings: { id: number; date: string }[];
    statuses: Record<string, string>;
    attendance: Record<string, string>;
    minutesStatuses: Record<string, string>;
    kinds: Record<string, string>;
    actionStatuses: Record<string, string>;
};

export default function MeetingShow({
    meeting: m,
    attendees,
    decisions,
    otherMeetings,
    attendance,
    kinds,
    actionStatuses,
}: Props) {
    const { can } = usePermission();
    const manage = can('meetings.manage');
    const [confirming, setConfirming] = useState(false);
    const times = [m.starts_at, m.ends_at].filter(Boolean).join('–');

    return (
        <>
            <Head title={m.heading} />

            <div className="max-w-5xl space-y-6 p-4">
                <PageHeader
                    title={m.heading}
                    description={[
                        m.committee !== m.heading ? m.committee : null,
                        shortDate(m.meeting_date),
                        times,
                        m.venue,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                    actions={
                        <>
                            <MeetingStatusBadge status={m.status} />
                            {manage && (
                                <>
                                    {m.status === 'scheduled' && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.put(
                                                    status(m.id).url,
                                                    { status: 'held' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <CheckCheck /> Mark held
                                        </Button>
                                    )}
                                    {m.status !== 'cancelled' ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.put(
                                                    status(m.id).url,
                                                    { status: 'cancelled' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Ban /> Cancel
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.put(
                                                    status(m.id).url,
                                                    { status: 'scheduled' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Reinstate
                                        </Button>
                                    )}
                                    <Button size="sm" asChild>
                                        <Link href={edit(m.id)}>
                                            <Pencil /> Edit
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </>
                    }
                />
                <MeetingsNav />

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card title="Agenda">
                            {m.agenda ? (
                                <p className="text-sm whitespace-pre-line">
                                    {m.agenda}
                                </p>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No agenda recorded.
                                </p>
                            )}
                        </Card>

                        <MinutesCard
                            meeting={m}
                            otherMeetings={otherMeetings}
                            editable={manage}
                        />

                        <DecisionsCard
                            decisions={decisions}
                            kinds={kinds}
                            actionStatuses={actionStatuses}
                            meetingId={m.id}
                            editable={manage}
                        />
                    </div>

                    <div className="space-y-6">
                        <Card title="Officers">
                            <dl className="space-y-3 text-sm">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Chairperson
                                    </dt>
                                    <dd>
                                        {m.chairperson_member_id ? (
                                            <Link
                                                href={showMember(
                                                    m.chairperson_member_id,
                                                )}
                                                className="underline"
                                            >
                                                {m.chairperson}
                                            </Link>
                                        ) : (
                                            (m.chairperson ?? '—')
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Secretary
                                    </dt>
                                    <dd>
                                        {m.secretary_member_id ? (
                                            <Link
                                                href={showMember(
                                                    m.secretary_member_id,
                                                )}
                                                className="underline"
                                            >
                                                {m.secretary}
                                            </Link>
                                        ) : (
                                            (m.secretary ?? '—')
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </Card>

                        <AttendeesCard
                            meetingId={m.id}
                            attendees={attendees}
                            attendance={attendance}
                            editable={manage}
                        />
                    </div>
                </div>

                {manage && (
                    <div className="flex justify-end">
                        {confirming ? (
                            <span className="flex flex-wrap items-center gap-2 text-sm">
                                Delete this meeting with its minutes, decisions
                                and actions?
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(destroy(m.id).url)
                                    }
                                >
                                    Yes, delete
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setConfirming(false)}
                                >
                                    Keep
                                </Button>
                            </span>
                        ) : (
                            <Button
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                onClick={() => setConfirming(true)}
                            >
                                <Trash2 /> Delete meeting
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

function Card({
    title,
    actions,
    children,
}: {
    title: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="space-y-3 rounded-lg border p-5">
            <div className="flex items-center justify-between gap-2">
                <h2 className="font-medium">{title}</h2>
                {actions}
            </div>
            {children}
        </section>
    );
}

/** Who came: members are searched for; each is marked present, sent apologies, or absent. */
function AttendeesCard({
    meetingId,
    attendees,
    attendance,
    editable,
}: {
    meetingId: number;
    attendees: Attendee[];
    attendance: Record<string, string>;
    editable: boolean;
}) {
    const [name, setName] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const counts = Object.keys(attendance).map(
        (key) =>
            [
                key,
                attendees.filter((a) => a.attendance === key).length,
            ] as const,
    );

    const add = (extra: { member_id?: number; name?: string }) =>
        router.post(
            addAttendee(meetingId).url,
            { attendance: 'present', ...extra },
            {
                preserveScroll: true,
                onError: setErrors,
                onSuccess: () => {
                    setName('');
                    setErrors({});
                },
            },
        );

    return (
        <Card title={`Attendees (${attendees.length})`}>
            <p className="text-xs text-muted-foreground">
                {counts
                    .map(([key, n]) => `${n} ${attendance[key].toLowerCase()}`)
                    .join(' · ')}
            </p>
            {attendees.length > 0 && (
                <ul className="divide-y">
                    {attendees.map((a) => (
                        <li
                            key={a.id}
                            className="flex items-center justify-between gap-2 py-2 text-sm"
                        >
                            <span className="min-w-0 truncate">{a.name}</span>
                            {editable ? (
                                <span className="flex items-center gap-1">
                                    <NativeSelect
                                        aria-label={`Attendance of ${a.name}`}
                                        className="h-8 w-auto py-0 text-xs"
                                        value={a.attendance}
                                        onChange={(e) =>
                                            router.put(
                                                setAttendance([meetingId, a.id])
                                                    .url,
                                                { attendance: e.target.value },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {Object.entries(attendance).map(
                                            ([key, label]) => (
                                                <option key={key} value={key}>
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </NativeSelect>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        aria-label={`Remove ${a.name}`}
                                        onClick={() =>
                                            router.delete(
                                                removeAttendee([
                                                    meetingId,
                                                    a.id,
                                                ]).url,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <X />
                                    </Button>
                                </span>
                            ) : (
                                <span className="text-muted-foreground">
                                    {attendance[a.attendance]}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {editable && (
                <div className="space-y-2 border-t pt-3">
                    <Label>Add an attendee</Label>
                    <MemberPicker
                        invalid={Boolean(errors.member_id)}
                        onPick={(member) => add({ member_id: member.id })}
                    />
                    <div className="flex gap-2">
                        <Input
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Or type a name"
                            maxLength={150}
                            aria-label="Name of an attendee outside the church"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            disabled={!name.trim()}
                            onClick={() => add({ name })}
                        >
                            Add
                        </Button>
                    </div>
                    <InputError message={errors.member_id} />
                </div>
            )}
        </Card>
    );
}

/** The minutes: written here, kept as a draft, then confirmed once the committee has adopted them. */
function MinutesCard({
    meeting: m,
    otherMeetings,
    editable,
}: {
    meeting: Meeting;
    otherMeetings: { id: number; date: string }[];
    editable: boolean;
}) {
    const form = useForm({
        minutes: m.minutes ?? '',
        minutes_status: m.minutes_status,
        minutes_confirmed_on: m.minutes_confirmed_on ?? '',
        minutes_confirmed_at_meeting_id: (m.confirmed_at?.id ?? '') as
            | number
            | '',
    });
    const { data, setData, errors } = form;
    const confirmed = data.minutes_status === 'confirmed';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(minutes(m.id).url, { preserveScroll: true });
    };

    if (!editable) {
        return (
            <Card title="Minutes">
                {m.minutes ? (
                    <>
                        <p className="text-sm whitespace-pre-line">
                            {m.minutes}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {m.minutes_status === 'confirmed'
                                ? `Confirmed ${shortDate(m.minutes_confirmed_on!)}${m.confirmed_at ? ` at the meeting of ${shortDate(m.confirmed_at.date)}` : ''}.`
                                : 'Draft: not yet confirmed.'}
                        </p>
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No minutes recorded.
                    </p>
                )}
            </Card>
        );
    }

    return (
        <Card title="Minutes">
            <form onSubmit={submit} className="space-y-3">
                <Textarea
                    aria-label="Minutes"
                    rows={12}
                    value={data.minutes}
                    onChange={(e) => setData('minutes', e.target.value)}
                    maxLength={100000}
                    placeholder="Write the minutes here."
                />
                <InputError message={errors.minutes} />
                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="minutes_status">Status</Label>
                        <NativeSelect
                            id="minutes_status"
                            className="w-auto"
                            value={data.minutes_status}
                            onChange={(e) =>
                                setData(
                                    'minutes_status',
                                    e.target.value as Meeting['minutes_status'],
                                )
                            }
                        >
                            <option value="draft">Draft</option>
                            <option value="confirmed">Confirmed</option>
                        </NativeSelect>
                    </div>
                    {confirmed && (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="minutes_confirmed_on">
                                    Confirmed on
                                </Label>
                                <Input
                                    id="minutes_confirmed_on"
                                    type="date"
                                    max={new Date().toISOString().slice(0, 10)}
                                    className="w-auto"
                                    value={data.minutes_confirmed_on}
                                    onChange={(e) =>
                                        setData(
                                            'minutes_confirmed_on',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="minutes_confirmed_at">
                                    At the meeting of
                                </Label>
                                <NativeSelect
                                    id="minutes_confirmed_at"
                                    className="w-auto"
                                    value={data.minutes_confirmed_at_meeting_id}
                                    onChange={(e) =>
                                        setData(
                                            'minutes_confirmed_at_meeting_id',
                                            e.target.value
                                                ? Number(e.target.value)
                                                : '',
                                        )
                                    }
                                >
                                    <option value="">Not recorded</option>
                                    {otherMeetings.map((o) => (
                                        <option key={o.id} value={o.id}>
                                            {shortDate(o.date)}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                        </>
                    )}
                    <Button type="submit" disabled={form.processing}>
                        Save minutes
                    </Button>
                </div>
                <InputError message={errors.minutes_confirmed_on} />
            </form>
        </Card>
    );
}

/** Decisions and resolutions, each with the actions that follow from it. */
function DecisionsCard({
    decisions,
    kinds,
    actionStatuses,
    meetingId,
    editable,
}: {
    decisions: Decision[];
    kinds: Record<string, string>;
    actionStatuses: Record<string, string>;
    meetingId: number;
    editable: boolean;
}) {
    const [decision, setDecision] = useState<Decision | 'new' | null>(null);
    const [action, setAction] = useState<{
        decisionId: number;
        action: ActionRow | null;
    } | null>(null);

    return (
        <Card
            title={`Decisions and resolutions (${decisions.length})`}
            actions={
                editable && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setDecision('new')}
                    >
                        <Plus /> Add
                    </Button>
                )
            }
        >
            {decisions.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Nothing recorded yet.
                </p>
            ) : (
                <ol className="space-y-5">
                    {decisions.map((d, i) => (
                        <li key={d.id} className="space-y-2">
                            <div className="flex items-start justify-between gap-2">
                                <p className="text-sm">
                                    <span className="mr-2 font-medium tabular-nums">
                                        {i + 1}.
                                    </span>
                                    <span className="mr-2 rounded bg-muted px-1.5 py-0.5 text-xs">
                                        {kinds[d.kind]}
                                    </span>
                                    {d.text}
                                </p>
                                {editable && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-7 shrink-0"
                                        aria-label="Edit this decision"
                                        onClick={() => setDecision(d)}
                                    >
                                        <Pencil />
                                    </Button>
                                )}
                            </div>

                            <ul className="ml-6 space-y-1.5 border-l pl-3">
                                {d.actions.map((a) => (
                                    <li
                                        key={a.id}
                                        className="space-y-1 rounded-md bg-muted/40 p-2 text-sm"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <span>{a.description}</span>
                                            <ActionBadge status={a.shown} />
                                        </div>
                                        <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                                            <span
                                                className={cn(
                                                    a.shown === 'overdue' &&
                                                        'font-medium text-red-600',
                                                )}
                                            >
                                                {[
                                                    a.responsible ??
                                                        'No one assigned',
                                                    deadlineText(a),
                                                ].join(' · ')}
                                            </span>
                                            {editable && (
                                                <button
                                                    type="button"
                                                    className="underline hover:text-foreground"
                                                    onClick={() =>
                                                        setAction({
                                                            decisionId: d.id,
                                                            action: a,
                                                        })
                                                    }
                                                >
                                                    Update
                                                </button>
                                            )}
                                        </div>
                                        {a.note && (
                                            <p className="text-xs">{a.note}</p>
                                        )}
                                    </li>
                                ))}
                                {editable && (
                                    <li>
                                        <button
                                            type="button"
                                            className="text-xs text-muted-foreground underline hover:text-foreground"
                                            onClick={() =>
                                                setAction({
                                                    decisionId: d.id,
                                                    action: null,
                                                })
                                            }
                                        >
                                            + Add an action
                                        </button>
                                    </li>
                                )}
                            </ul>
                        </li>
                    ))}
                </ol>
            )}

            {decision && (
                <DecisionDialog
                    meetingId={meetingId}
                    decision={decision === 'new' ? null : decision}
                    kinds={kinds}
                    onClose={() => setDecision(null)}
                />
            )}
            {action && (
                <ActionDialog
                    decisionId={action.decisionId}
                    action={action.action}
                    statuses={actionStatuses}
                    onClose={() => setAction(null)}
                />
            )}
        </Card>
    );
}

function DecisionDialog({
    meetingId,
    decision,
    kinds,
    onClose,
}: {
    meetingId: number;
    decision: Decision | null;
    kinds: Record<string, string>;
    onClose: () => void;
}) {
    const [confirming, setConfirming] = useState(false);
    const form = useForm({
        kind: decision?.kind ?? 'decision',
        text: decision?.text ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (decision) {
            form.put(updateDecision(decision.id).url, options);
        } else {
            form.post(addDecision(meetingId).url, options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {decision ? 'Edit' : 'Record'} a decision or
                            resolution
                        </DialogTitle>
                        <DialogDescription>
                            Add the actions that follow from it afterwards.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="decision-kind">Kind</Label>
                        <NativeSelect
                            id="decision-kind"
                            value={form.data.kind}
                            onChange={(e) =>
                                form.setData('kind', e.target.value)
                            }
                        >
                            {Object.entries(kinds).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="decision-text">What was decided</Label>
                        <Textarea
                            id="decision-text"
                            rows={4}
                            value={form.data.text}
                            onChange={(e) =>
                                form.setData('text', e.target.value)
                            }
                            maxLength={5000}
                            required
                        />
                        <InputError message={form.errors.text} />
                    </div>
                    <DialogFooter className="gap-2 sm:justify-between">
                        {decision ? (
                            confirming ? (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm">
                                        Remove it and its{' '}
                                        {decision.actions.length} action(s)?
                                    </span>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(
                                                removeDecision(decision.id).url,
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: onClose,
                                                },
                                            )
                                        }
                                    >
                                        Yes, remove
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setConfirming(false)}
                                    >
                                        Keep
                                    </Button>
                                </div>
                            ) : (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="text-destructive hover:text-destructive"
                                    onClick={() => setConfirming(true)}
                                >
                                    <Trash2 /> Remove
                                </Button>
                            )
                        ) : (
                            <span />
                        )}
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Save
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

MeetingShow.layout = {
    breadcrumbs: [{ title: 'Meetings', href: index() }],
};
