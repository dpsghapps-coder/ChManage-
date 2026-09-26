import { Head, Link, router } from '@inertiajs/react';
import { Ban, Pencil, RotateCcw, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { DetailList } from '@/components/detail-list';
import {
    EventBadges,
    eventDates,
    eventTimes,
    hostTone,
} from '@/components/events-nav';
import type { EventRow } from '@/components/events-nav';
import { MemberPicker } from '@/components/member-picker';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { show as showMember } from '@/routes/members';
import { calendar, destroy, edit, index, status } from '@/routes/events';
import {
    clear as clearParticipants,
    destroy as removeParticipant,
    store as addParticipant,
} from '@/routes/events/participants';

type Detail = EventRow & {
    description: string | null;
    purpose: string | null;
    notes: string | null;
    organizer_phone: string | null;
    organizer_member_id: number | null;
};

type Props = {
    event: Detail;
    hosts: Record<string, string>;
    scopes: Record<string, string>;
    visibilities: Record<string, string>;
    participants: Participant[];
    groups: { id: number; name: string }[];
    committees: { id: number; name: string }[];
};

type Participant = {
    id: number;
    member_id: number | null;
    name: string;
    source: string | null;
};

export default function EventShow({
    event: e,
    hosts,
    scopes,
    visibilities,
    participants,
    groups,
    committees,
}: Props) {
    const { can } = usePermission();
    const manage = can('events.manage');
    const [confirming, setConfirming] = useState(false);
    const cancelled = e.status === 'cancelled';

    return (
        <>
            <Head title={e.title} />

            <div className="max-w-4xl space-y-6 p-4">
                <PageHeader
                    title={e.title}
                    description={[eventDates(e), eventTimes(e), e.venue]
                        .filter(Boolean)
                        .join(' · ')}
                    actions={
                        <>
                            <span
                                className={cn(
                                    'rounded px-2 py-1 text-xs',
                                    hostTone[e.host_type],
                                )}
                            >
                                {e.host}
                            </span>
                            <EventBadges event={e} />
                            {manage && (
                                <>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.put(
                                                status(e.id).url,
                                                {
                                                    status: cancelled
                                                        ? 'scheduled'
                                                        : 'cancelled',
                                                },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {cancelled ? <RotateCcw /> : <Ban />}{' '}
                                        {cancelled
                                            ? 'Reinstate'
                                            : 'Cancel event'}
                                    </Button>
                                    <Button size="sm" asChild>
                                        <Link href={edit(e.id)}>
                                            <Pencil /> Edit
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </>
                    }
                />

                {cancelled && (
                    <p className="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm">
                        This event has been cancelled. It stays on the calendar,
                        struck through.
                    </p>
                )}

                <section className="space-y-3 rounded-lg border p-5">
                    <h2 className="font-medium">Details</h2>
                    <DetailList
                        items={[
                            { label: 'Purpose', value: e.purpose },
                            {
                                label: 'Host',
                                value: `${hosts[e.host_type]}${e.host_type === 'church' ? '' : `: ${e.host}`}`,
                            },
                            {
                                label: 'Internal or external',
                                value: scopes[e.scope],
                            },
                            {
                                label: 'Public or private',
                                value: visibilities[e.visibility],
                            },
                            { label: 'Date', value: eventDates(e) },
                            { label: 'Time', value: eventTimes(e) },
                            { label: 'Venue', value: e.venue },
                            {
                                label: 'Organizer',
                                value: e.organizer_member_id ? (
                                    <Link
                                        href={showMember(e.organizer_member_id)}
                                        className="underline"
                                    >
                                        {e.organizer}
                                    </Link>
                                ) : (
                                    e.organizer
                                ),
                            },
                            {
                                label: 'Organizer phone',
                                value: e.organizer_phone,
                            },
                        ]}
                    />
                    {e.description && (
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Description
                            </p>
                            <p className="mt-0.5 text-sm whitespace-pre-line">
                                {e.description}
                            </p>
                        </div>
                    )}
                    {e.notes && (
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Notes
                            </p>
                            <p className="mt-0.5 text-sm whitespace-pre-line">
                                {e.notes}
                            </p>
                        </div>
                    )}
                </section>

                <ParticipantsCard
                    eventId={e.id}
                    participants={participants}
                    groups={groups}
                    committees={committees}
                    hostGroup={e.host_type === 'group' ? e.host : null}
                    hostCommittee={e.host_type === 'committee' ? e.host : null}
                    editable={manage}
                />

                <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    Attendance, budget, tasks, expenses and documents will
                    appear here as those parts of the system are built.
                </p>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Button variant="outline" asChild>
                        <Link
                            href={calendar({
                                query: { month: e.starts_on.slice(0, 7) },
                            })}
                        >
                            Back to the calendar
                        </Link>
                    </Button>
                    {manage &&
                        (confirming ? (
                            <span className="flex flex-wrap items-center gap-2 text-sm">
                                Delete {e.title}?
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(destroy(e.id).url)
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
                                <Trash2 /> Delete
                            </Button>
                        ))}
                </div>
            </div>
        </>
    );
}

/** Who the event is for: a whole group or committee at once, or people one by one. It is copied onto the event when added. */
function ParticipantsCard({
    eventId,
    participants,
    groups,
    committees,
    hostGroup,
    hostCommittee,
    editable,
}: {
    eventId: number;
    participants: Participant[];
    groups: { id: number; name: string }[];
    committees: { id: number; name: string }[];
    hostGroup: string | null;
    hostCommittee: string | null;
    editable: boolean;
}) {
    // The host's own group or committee is offered first.
    const [groupId, setGroupId] = useState<string>(
        String(groups.find((g) => g.name === hostGroup)?.id ?? ''),
    );
    const [committeeId, setCommitteeId] = useState<string>(
        String(committees.find((c) => c.name === hostCommittee)?.id ?? ''),
    );
    const [name, setName] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [clearing, setClearing] = useState(false);

    const add = (
        payload: Record<string, string | number>,
        reset?: () => void,
    ) =>
        router.post(addParticipant(eventId).url, payload, {
            preserveScroll: true,
            onError: (errors) => setError(errors.member_id ?? null),
            onSuccess: () => {
                setError(null);
                reset?.();
            },
        });

    return (
        <section className="space-y-3 rounded-lg border p-5">
            <div className="flex items-center justify-between gap-2">
                <h2 className="font-medium">
                    Participants ({participants.length})
                </h2>
                {editable &&
                    participants.length > 0 &&
                    (clearing ? (
                        <span className="flex items-center gap-2 text-sm">
                            Remove everyone?
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() =>
                                    router.delete(
                                        clearParticipants(eventId).url,
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => setClearing(false),
                                        },
                                    )
                                }
                            >
                                Yes
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setClearing(false)}
                            >
                                No
                            </Button>
                        </span>
                    ) : (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setClearing(true)}
                        >
                            Clear list
                        </Button>
                    ))}
            </div>

            {participants.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No one has been added to this event yet.
                </p>
            ) : (
                <ul className="grid gap-x-6 sm:grid-cols-2">
                    {participants.map((p) => (
                        <li
                            key={p.id}
                            className="flex items-center justify-between gap-2 border-b py-1.5 text-sm"
                        >
                            <span className="min-w-0 truncate">
                                {p.member_id ? (
                                    <Link
                                        href={showMember(p.member_id)}
                                        className="hover:underline"
                                    >
                                        {p.name}
                                    </Link>
                                ) : (
                                    p.name
                                )}
                                {p.source && (
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        {p.source}
                                    </span>
                                )}
                            </span>
                            {editable && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-6"
                                    aria-label={`Remove ${p.name}`}
                                    onClick={() =>
                                        router.delete(
                                            removeParticipant([eventId, p.id])
                                                .url,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <X />
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {editable && (
                <div className="grid gap-4 border-t pt-4 sm:grid-cols-2">
                    <div className="grid content-start gap-2">
                        <Label htmlFor="participant-group">Add a group</Label>
                        <div className="flex gap-2">
                            <NativeSelect
                                id="participant-group"
                                value={groupId}
                                onChange={(ev) => setGroupId(ev.target.value)}
                            >
                                <option value="">Select a group…</option>
                                {groups.map((g) => (
                                    <option key={g.id} value={g.id}>
                                        {g.name}
                                    </option>
                                ))}
                            </NativeSelect>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={!groupId}
                                onClick={() =>
                                    add({ group_id: Number(groupId) })
                                }
                            >
                                Add
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Everyone who has the group ticked on their bio.
                        </p>
                    </div>
                    <div className="grid content-start gap-2">
                        <Label htmlFor="participant-committee">
                            Add a committee
                        </Label>
                        <div className="flex gap-2">
                            <NativeSelect
                                id="participant-committee"
                                value={committeeId}
                                onChange={(ev) =>
                                    setCommitteeId(ev.target.value)
                                }
                            >
                                <option value="">Select a committee…</option>
                                {committees.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </NativeSelect>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={!committeeId}
                                onClick={() =>
                                    add({ committee_id: Number(committeeId) })
                                }
                            >
                                Add
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Those serving on it on the day of the event.
                        </p>
                    </div>
                    <div className="grid content-start gap-2 sm:col-span-2">
                        <Label>Add one person</Label>
                        <MemberPicker
                            invalid={Boolean(error)}
                            onPick={(member) => add({ member_id: member.id })}
                        />
                        <div className="flex gap-2">
                            <Input
                                value={name}
                                onChange={(ev) => setName(ev.target.value)}
                                placeholder="Or type the name of someone outside the church"
                                maxLength={150}
                                aria-label="Name of a guest"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                disabled={!name.trim()}
                                onClick={() => add({ name }, () => setName(''))}
                            >
                                Add
                            </Button>
                        </div>
                        {error && (
                            <p className="text-sm text-destructive">{error}</p>
                        )}
                    </div>
                </div>
            )}
        </section>
    );
}

EventShow.layout = {
    breadcrumbs: [{ title: 'Events', href: index() }],
};
