import { Head, Link, router } from '@inertiajs/react';
import { CheckCheck, Pencil, Trash2, UserPlus, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { MemberPicker } from '@/components/member-picker';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermission } from '@/hooks/use-permission';
import { day } from '@/lib/day';
import {
    attendance as saveAttendance,
    communicants as loadCommunicants,
    destroy,
    edit,
    index,
} from '@/routes/communion';
import {
    destroy as removeAttendee,
    store as addAttendee,
} from '@/routes/communion/attendees';
import { create as writeNote } from '@/routes/speaking';

type Attendee = {
    id: number;
    member_id: number;
    member_number: string | null;
    name: string | null;
    group: string | null;
    status: string;
    /** The outcome of speaking to them for this service; null when not spoken to; false when the person may not see it. */
    spoken: string | null | false;
};

type Props = {
    service: {
        id: number;
        title: string;
        held_on: string;
        venue: string | null;
        status: string;
        note: string | null;
    };
    attendees: Attendee[];
    attendance: Record<string, string>;
    statuses: Record<string, string>;
    communicants: number;
    showsSpeaking: boolean;
};

const SPOKEN: Record<string, string> = {
    cleared: 'Spoken to',
    follow_up: 'Follow-up',
    deferred: 'Deferred',
};

export default function CommunionShow({
    service,
    attendees,
    attendance,
    statuses,
    communicants,
    showsSpeaking,
}: Props) {
    const { can } = usePermission();
    const manage = can('communion.manage');

    const [marks, setMarks] = useState<Record<number, string>>(() =>
        Object.fromEntries(attendees.map((a) => [a.id, a.status])),
    );
    const [term, setTerm] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const shown = useMemo(() => {
        const t = term.trim().toLowerCase();

        return t
            ? attendees.filter((a) =>
                  `${a.name} ${a.member_number}`.toLowerCase().includes(t),
              )
            : attendees;
    }, [attendees, term]);

    const counts = useMemo(() => {
        const c: Record<string, number> = { present: 0, absent: 0, excused: 0 };
        attendees.forEach((a) => (c[marks[a.id] ?? a.status] += 1));

        return c;
    }, [attendees, marks]);

    const dirty = attendees.some((a) => marks[a.id] !== a.status);

    const save = () =>
        router.put(
            saveAttendance(service.id).url,
            { statuses: marks },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={service.title} />

            <div className="space-y-6 p-4">
                <PageHeader
                    title={service.title}
                    description={`${day(service.held_on)}${service.venue ? ` · ${service.venue}` : ''}`}
                    actions={
                        <>
                            <Badge
                                variant={
                                    service.status === 'scheduled'
                                        ? 'default'
                                        : 'outline'
                                }
                            >
                                {statuses[service.status] ?? service.status}
                            </Badge>
                            {manage && (
                                <>
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={edit(service.id)}>
                                            <Pencil /> Edit
                                        </Link>
                                    </Button>
                                    {confirmDelete ? (
                                        <span className="flex items-center gap-2 text-sm">
                                            Delete this service?
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() =>
                                                    router.delete(
                                                        destroy(service.id).url,
                                                    )
                                                }
                                            >
                                                Yes
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setConfirmDelete(false)
                                                }
                                            >
                                                No
                                            </Button>
                                        </span>
                                    ) : (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setConfirmDelete(true)
                                            }
                                        >
                                            <Trash2 /> Delete
                                        </Button>
                                    )}
                                </>
                            )}
                        </>
                    }
                />

                {service.note && (
                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                        {service.note}
                    </p>
                )}

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        ['On the list', attendees.length],
                        ['Received', counts.present],
                        ['Absent', counts.absent],
                        ['Excused', counts.excused],
                    ].map(([label, value]) => (
                        <div
                            key={label}
                            className="rounded-lg border p-3 text-center"
                        >
                            <p className="text-2xl font-semibold tabular-nums">
                                {value}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {label}
                            </p>
                        </div>
                    ))}
                </div>

                {manage && (
                    <div className="space-y-3 rounded-lg border p-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        loadCommunicants(service.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <UserPlus /> Load the active communicants (
                                {communicants})
                            </Button>
                            <span className="text-xs text-muted-foreground">
                                Members marked as non-communicants are left out.
                            </span>
                        </div>
                        <div className="grid gap-1">
                            <p className="text-sm font-medium">
                                Add someone else
                            </p>
                            <MemberPicker
                                invalid={Boolean(error)}
                                onPick={(member) =>
                                    router.post(
                                        addAttendee(service.id).url,
                                        { member_id: member.id },
                                        {
                                            preserveScroll: true,
                                            onError: (e) =>
                                                setError(e.member_id ?? null),
                                            onSuccess: () => setError(null),
                                        },
                                    )
                                }
                            />
                            <InputError message={error ?? undefined} />
                        </div>
                    </div>
                )}

                {attendees.length === 0 ? (
                    <p className="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground">
                        {manage
                            ? 'Load the communicants to start taking attendance.'
                            : 'No list has been made for this service.'}
                    </p>
                ) : (
                    <div className="space-y-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <Input
                                value={term}
                                onChange={(e) => setTerm(e.target.value)}
                                placeholder="Find a member"
                                className="max-w-xs"
                                aria-label="Find a member"
                            />
                            {manage && (
                                <>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setMarks({
                                                ...marks,
                                                ...Object.fromEntries(
                                                    shown.map((a) => [
                                                        a.id,
                                                        'present',
                                                    ]),
                                                ),
                                            })
                                        }
                                    >
                                        <CheckCheck /> Mark{' '}
                                        {term ? 'these' : 'everyone'} as
                                        received
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={!dirty}
                                        onClick={save}
                                    >
                                        Save attendance
                                    </Button>
                                    {dirty && (
                                        <span className="text-xs text-amber-600">
                                            Not saved yet
                                        </span>
                                    )}
                                </>
                            )}
                        </div>

                        <ul className="divide-y rounded-lg border">
                            {shown.map((a) => (
                                <li
                                    key={a.id}
                                    className="flex flex-wrap items-center justify-between gap-2 p-3"
                                >
                                    <span className="min-w-0">
                                        <span className="block text-sm font-medium">
                                            {a.name}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {[a.member_number, a.group]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    </span>
                                    <span className="flex items-center gap-2">
                                        {showsSpeaking &&
                                            (a.spoken ? (
                                                <Badge variant="outline">
                                                    {SPOKEN[a.spoken] ??
                                                        a.spoken}
                                                </Badge>
                                            ) : (
                                                can('speaking.manage') && (
                                                    <Link
                                                        href={writeNote({
                                                            query: {
                                                                member: a.member_id,
                                                                service:
                                                                    service.id,
                                                            },
                                                        })}
                                                        className="text-xs text-muted-foreground underline underline-offset-4"
                                                    >
                                                        Not spoken to
                                                    </Link>
                                                )
                                            ))}
                                        <span
                                            role="group"
                                            aria-label={`Attendance for ${a.name}`}
                                            className="flex overflow-hidden rounded-md border"
                                        >
                                            {Object.entries(attendance).map(
                                                ([value, label]) => (
                                                    <button
                                                        key={value}
                                                        type="button"
                                                        disabled={!manage}
                                                        aria-pressed={
                                                            marks[a.id] ===
                                                            value
                                                        }
                                                        onClick={() =>
                                                            setMarks({
                                                                ...marks,
                                                                [a.id]: value,
                                                            })
                                                        }
                                                        className={
                                                            marks[a.id] ===
                                                            value
                                                                ? value ===
                                                                  'present'
                                                                    ? 'bg-emerald-600 px-3 py-1 text-xs text-white'
                                                                    : 'bg-primary px-3 py-1 text-xs text-primary-foreground'
                                                                : 'px-3 py-1 text-xs text-muted-foreground hover:bg-accent disabled:hover:bg-transparent'
                                                        }
                                                    >
                                                        {label}
                                                    </button>
                                                ),
                                            )}
                                        </span>
                                        {manage && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7"
                                                aria-label={`Take ${a.name} off the list`}
                                                onClick={() =>
                                                    router.delete(
                                                        removeAttendee([
                                                            service.id,
                                                            a.id,
                                                        ]).url,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <X />
                                            </Button>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </>
    );
}

CommunionShow.layout = {
    breadcrumbs: [{ title: 'Communion', href: index() }],
};
