import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarPlus,
    GraduationCap,
    Pencil,
    UserCheck,
    UserCog,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ChoiceOrOther } from '@/components/choice-or-other';
import { DetailList } from '@/components/detail-list';
import InputError from '@/components/input-error';
import { mapUrl } from '@/components/gps-capture';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { PersonStatusBadge, StageBadge } from '@/components/newcomers-nav';
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
import { usePermission } from '@/hooks/use-permission';
import { show as showMember } from '@/routes/members';
import { show as showYoung } from '@/routes/members/young';
import {
    edit,
    enrol,
    index,
    promote,
    status as setStatus,
} from '@/routes/newcomers';
import { update as updateProgress } from '@/routes/newcomers/progress';
import { store as storeVisit } from '@/routes/newcomers/visits';

type Person = {
    id: number;
    name: string;
    title: string | null;
    sex: string | null;
    age: number | null;
    date_of_birth: string | null;
    mobile: string | null;
    other_numbers: string | null;
    whatsapp: string | null;
    email: string | null;
    emergency_number: string | null;
    residential_address: string | null;
    postal_address: string | null;
    stage: string;
    status: string;
    inactive_reason: string | null;
    first_visit_on: string | null;
    first_service: string | null;
    purpose: string | null;
    heard_via: string | null;
    heard_contact: string | null;
    current_status: string | null;
    marital_status: string | null;
    marriage_type: string | null;
    religious_background: string | null;
    religious_other: string | null;
    former_church: string | null;
    is_baptized: boolean;
    is_confirmed: boolean;
    is_minor: boolean;
    guardian_name: string | null;
    guardian_relationship: string | null;
    guardian_phone: string | null;
    counsellor: string | null;
    counsellor_phone: string | null;
    remarks: string | null;
    member: { id: number; member_number: string; full_name: string } | null;
    made_member_on: string | null;
    young_member: {
        id: number;
        member_number: string;
        full_name: string;
    } | null;
    photo_url: string | null;
    latitude: number | null;
    longitude: number | null;
    location_accuracy: number | null;
};

type Visit = {
    id: number;
    visited_on: string;
    service: string | null;
    note: string | null;
};

type Change = {
    id: number;
    kind: 'stage' | 'status';
    from: string | null;
    to: string;
    on: string;
    note: string | null;
    by: string | null;
};

type Props = {
    newcomer: Person;
    visits: Visit[];
    history: Change[];
    stages: Record<string, string>;
    statuses: Record<string, string>;
    inactiveReasons: string[];
    services: string[];
    lessons: Lesson[];
    promotion: { target: string | null; blockers: string[] };
};

type Lesson = {
    id: number;
    title: string;
    description: string | null;
    status: 'not_started' | 'in_progress' | 'completed' | 'skipped';
    completed_on: string | null;
    note: string | null;
};

const lessonStatuses: Record<Lesson['status'], string> = {
    not_started: 'Not started',
    in_progress: 'In progress',
    completed: 'Completed',
    skipped: 'Skipped',
};

const formatDate = (iso: string | null) =>
    iso
        ? new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : null;

const words = (value: string | null) =>
    value
        ? value.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase())
        : null;

export default function NewcomerShow({
    newcomer: p,
    visits,
    history,
    stages,
    statuses,
    inactiveReasons,
    services,
    lessons,
    promotion,
}: Props) {
    const { can } = usePermission();
    const [dialog, setDialog] = useState<
        'visit' | 'status' | 'enrol' | 'promote' | null
    >(null);
    const manage = can('newcomers.manage');

    return (
        <>
            <Head title={p.name} />

            <div className="max-w-5xl space-y-6 p-4">
                <PersonAvatar
                    name={p.name}
                    photoUrl={p.photo_url}
                    className="size-24 text-2xl"
                />
                <PageHeader
                    title={`${p.title ? `${p.title} ` : ''}${p.name}`}
                    description={`First visit ${formatDate(p.first_visit_on) ?? '—'}${p.age !== null ? ` · ${p.age} years old` : ''}`}
                    actions={
                        <>
                            <StageBadge stage={p.stage} />
                            <PersonStatusBadge status={p.status} />
                            {manage && (
                                <>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setDialog('visit')}
                                    >
                                        <CalendarPlus /> Returning visit
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setDialog('status')}
                                    >
                                        <UserCog /> Status
                                    </Button>
                                    {p.stage === 'newcomer' && (
                                        <Button
                                            size="sm"
                                            onClick={() => setDialog('enrol')}
                                        >
                                            <GraduationCap /> Enrol in class
                                        </Button>
                                    )}
                                    {p.stage === 'catechumen' &&
                                        can('newcomers.promote') && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    setDialog('promote')
                                                }
                                            >
                                                <UserCheck /> Make member
                                            </Button>
                                        )}
                                    <Button size="sm" asChild>
                                        <Link href={edit(p.id)}>
                                            <Pencil /> Edit
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </>
                    }
                />

                {p.status === 'inactive' && (
                    <p className="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm">
                        No longer active
                        {p.inactive_reason ? `: ${p.inactive_reason}` : ''}.
                        Change the status to bring them back.
                    </p>
                )}

                {p.member && (
                    <p className="rounded-lg border bg-muted/40 p-3 text-sm">
                        Now a member:{' '}
                        <Link
                            href={showMember(p.member.id)}
                            className="font-medium underline"
                        >
                            {p.member.full_name} ({p.member.member_number})
                        </Link>
                        {p.made_member_on
                            ? `, since ${formatDate(p.made_member_on)}`
                            : ''}
                        .
                    </p>
                )}

                {p.young_member && (
                    <p className="rounded-lg border bg-muted/40 p-3 text-sm">
                        Now a member:{' '}
                        <Link
                            href={showYoung(p.young_member.id)}
                            className="font-medium underline"
                        >
                            {p.young_member.full_name} (
                            {p.young_member.member_number})
                        </Link>
                        {p.made_member_on
                            ? `, since ${formatDate(p.made_member_on)}`
                            : ''}
                        .
                    </p>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card title="Counsellor">
                            <DetailList
                                items={[
                                    {
                                        label: 'Counsellor',
                                        value: p.counsellor ?? 'None yet',
                                    },
                                    {
                                        label: 'Counsellor phone',
                                        value: p.counsellor_phone,
                                    },
                                ]}
                            />
                        </Card>

                        {lessons.length > 0 && (
                            <LessonsCard
                                personId={p.id}
                                lessons={lessons}
                                editable={manage && p.stage === 'catechumen'}
                            />
                        )}

                        <Card title="Basic and contact information">
                            <DetailList
                                items={[
                                    { label: 'Gender', value: words(p.sex) },
                                    {
                                        label: 'Date of birth',
                                        value: formatDate(p.date_of_birth),
                                    },
                                    { label: 'Mobile', value: p.mobile },
                                    { label: 'WhatsApp', value: p.whatsapp },
                                    {
                                        label: 'Other numbers',
                                        value: p.other_numbers,
                                    },
                                    {
                                        label: 'Emergency number',
                                        value: p.emergency_number,
                                    },
                                    { label: 'Email', value: p.email },
                                    {
                                        label: 'Residential address',
                                        value: p.residential_address,
                                    },
                                    {
                                        label: 'Postal address',
                                        value: p.postal_address,
                                    },
                                    {
                                        label: 'Residence location',
                                        value:
                                            p.latitude !== null &&
                                            p.longitude !== null ? (
                                                <a
                                                    href={mapUrl(
                                                        p.latitude,
                                                        p.longitude,
                                                    )}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="underline"
                                                >
                                                    Open on map
                                                    {p.location_accuracy !==
                                                    null
                                                        ? ` (±${p.location_accuracy} m)`
                                                        : ''}
                                                </a>
                                            ) : null,
                                    },
                                ]}
                            />
                        </Card>

                        {p.is_minor && (
                            <Card title="Parent or guardian">
                                <DetailList
                                    items={[
                                        {
                                            label: 'Name',
                                            value: p.guardian_name,
                                        },
                                        {
                                            label: 'Relationship',
                                            value: p.guardian_relationship,
                                        },
                                        {
                                            label: 'Phone',
                                            value: p.guardian_phone,
                                        },
                                    ]}
                                />
                            </Card>
                        )}

                        <Card title="Other information">
                            <DetailList
                                items={[
                                    {
                                        label: 'Current status',
                                        value: p.current_status,
                                    },
                                    {
                                        label: 'Marital status',
                                        value: words(p.marital_status),
                                    },
                                    ...(p.marital_status === 'married'
                                        ? [
                                              {
                                                  label: 'Marriage',
                                                  value: words(p.marriage_type),
                                              },
                                          ]
                                        : []),
                                    {
                                        label: 'Religious background',
                                        value:
                                            p.religious_background === 'other'
                                                ? p.religious_other
                                                : words(p.religious_background),
                                    },
                                    {
                                        label: 'Former church',
                                        value: p.former_church,
                                    },
                                    {
                                        label: 'Sacramental status',
                                        value:
                                            [
                                                p.is_baptized && 'Baptized',
                                                p.is_confirmed && 'Confirmed',
                                            ]
                                                .filter(Boolean)
                                                .join(', ') || null,
                                    },
                                    {
                                        label: 'Purpose of the visit',
                                        value: p.purpose,
                                    },
                                    {
                                        label: 'First service',
                                        value: p.first_service,
                                    },
                                    {
                                        label: 'How they heard of us',
                                        value: p.heard_via,
                                    },
                                    {
                                        label: 'Source contact',
                                        value: p.heard_contact,
                                    },
                                    { label: 'Remarks', value: p.remarks },
                                ]}
                            />
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card title={`Visits (${visits.length})`}>
                            <ul className="space-y-2 text-sm">
                                {visits.map((v) => (
                                    <li
                                        key={v.id}
                                        className="flex justify-between gap-2"
                                    >
                                        <span>{formatDate(v.visited_on)}</span>
                                        <span className="text-right text-muted-foreground">
                                            {[v.service, v.note]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Card>

                        <Card title="History">
                            <ol className="space-y-3 border-l pl-4 text-sm">
                                {history.map((c) => (
                                    <li key={c.id} className="relative">
                                        <span className="absolute top-1.5 -left-[1.3rem] size-2 rounded-full bg-primary" />
                                        <p className="font-medium">
                                            {c.kind === 'stage'
                                                ? c.from
                                                    ? `${stages[c.from]} → ${stages[c.to]}`
                                                    : `Registered as ${stages[c.to].toLowerCase()}`
                                                : `Status: ${statuses[c.to]}`}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatDate(c.on)}
                                            {c.by ? ` · ${c.by}` : ''}
                                            {c.note ? ` · ${c.note}` : ''}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </Card>
                    </div>
                </div>
            </div>

            {dialog === 'enrol' && (
                <EnrolDialog
                    id={p.id}
                    name={p.name}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog === 'promote' && (
                <PromoteDialog
                    id={p.id}
                    name={p.name}
                    promotion={promotion}
                    unfinished={
                        lessons.filter(
                            (l) =>
                                l.status === 'not_started' ||
                                l.status === 'in_progress',
                        ).length
                    }
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog === 'visit' && (
                <VisitDialog
                    id={p.id}
                    services={services}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog === 'status' && (
                <StatusDialog
                    id={p.id}
                    status={p.status}
                    statuses={statuses}
                    reasons={inactiveReasons}
                    onClose={() => setDialog(null)}
                />
            )}
        </>
    );
}

/** Where they stand in each lesson of the class: a bar for the count, then a row per lesson. */
function LessonsCard({
    personId,
    lessons,
    editable,
}: {
    personId: number;
    lessons: Lesson[];
    editable: boolean;
}) {
    const counted = lessons.filter((l) => l.status !== 'skipped');
    const done = counted.filter((l) => l.status === 'completed').length;
    const percent = counted.length
        ? Math.round((done / counted.length) * 100)
        : 0;

    return (
        <Card title="Class lessons">
            <div className="space-y-1.5">
                <p className="text-sm">
                    <span className="font-medium tabular-nums">
                        {done} of {counted.length}
                    </span>{' '}
                    <span className="text-muted-foreground">
                        lessons completed
                    </span>
                </p>
                <div
                    role="progressbar"
                    aria-valuenow={percent}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    className="h-2 overflow-hidden rounded-full bg-muted"
                >
                    <div
                        className="h-full bg-emerald-600 transition-all"
                        style={{ width: `${percent}%` }}
                    />
                </div>
            </div>
            <ol className="divide-y">
                {lessons.map((lesson, i) => (
                    <LessonRow
                        key={lesson.id}
                        personId={personId}
                        number={i + 1}
                        lesson={lesson}
                        editable={editable}
                    />
                ))}
            </ol>
        </Card>
    );
}

const lessonTone: Record<Lesson['status'], string> = {
    not_started: 'text-muted-foreground',
    in_progress: 'text-amber-600',
    completed: 'text-emerald-600',
    skipped: 'text-muted-foreground line-through',
};

function LessonRow({
    personId,
    number,
    lesson,
    editable,
}: {
    personId: number;
    number: number;
    lesson: Lesson;
    editable: boolean;
}) {
    const [note, setNote] = useState(lesson.note ?? '');
    const save = (
        changes: Partial<{
            status: string;
            completed_on: string | null;
            note: string;
        }>,
    ) =>
        router.put(
            updateProgress([personId, lesson.id]).url,
            {
                status: lesson.status,
                completed_on: lesson.completed_on,
                note,
                ...changes,
            },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <li className="space-y-2 py-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium">
                        <span className="mr-2 text-muted-foreground tabular-nums">
                            {number}.
                        </span>
                        {lesson.title}
                    </p>
                    {lesson.description && (
                        <p className="pl-6 text-xs text-muted-foreground">
                            {lesson.description}
                        </p>
                    )}
                </div>
                {editable ? (
                    <NativeSelect
                        aria-label={`Status of ${lesson.title}`}
                        className="w-auto"
                        value={lesson.status}
                        onChange={(e) =>
                            save({
                                status: e.target.value,
                                completed_on:
                                    e.target.value === 'completed'
                                        ? new Date().toISOString().slice(0, 10)
                                        : null,
                            })
                        }
                    >
                        {Object.entries(lessonStatuses).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </NativeSelect>
                ) : (
                    <span className={`text-sm ${lessonTone[lesson.status]}`}>
                        {lessonStatuses[lesson.status]}
                    </span>
                )}
            </div>
            {editable && (
                <div className="flex flex-wrap items-center gap-2 pl-6">
                    {lesson.status === 'completed' && (
                        <Input
                            type="date"
                            aria-label={`Date ${lesson.title} was completed`}
                            className="w-auto"
                            max={new Date().toISOString().slice(0, 10)}
                            value={lesson.completed_on ?? ''}
                            onChange={(e) =>
                                e.target.value &&
                                save({ completed_on: e.target.value })
                            }
                        />
                    )}
                    <Input
                        aria-label={`Note on ${lesson.title}`}
                        placeholder="Note"
                        className="min-w-40 flex-1"
                        maxLength={250}
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        onBlur={() =>
                            note !== (lesson.note ?? '') && save({ note })
                        }
                    />
                </div>
            )}
            {!editable && (lesson.completed_on || lesson.note) && (
                <p className="pl-6 text-xs text-muted-foreground">
                    {[
                        lesson.completed_on
                            ? formatDate(lesson.completed_on)
                            : null,
                        lesson.note,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
            )}
        </li>
    );
}

function EnrolDialog({
    id,
    name,
    onClose,
}: {
    id: number;
    name: string;
    onClose: () => void;
}) {
    const [busy, setBusy] = useState(false);

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Enrol {name} in the class?</DialogTitle>
                    <DialogDescription>
                        They become a catechumen and get every lesson in use,
                        all not started. You can then record their progress on
                        this page.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        disabled={busy}
                        onClick={() => {
                            setBusy(true);
                            router.post(
                                enrol(id).url,
                                {},
                                {
                                    preserveScroll: true,
                                    onSuccess: onClose,
                                    onFinish: () => setBusy(false),
                                },
                            );
                        }}
                    >
                        Enrol in class
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function PromoteDialog({
    id,
    name,
    promotion,
    unfinished,
    onClose,
}: {
    id: number;
    name: string;
    promotion: { target: string | null; blockers: string[] };
    unfinished: number;
    onClose: () => void;
}) {
    const form = useForm({ joined_on: new Date().toISOString().slice(0, 10) });
    const blocked = promotion.blockers.length > 0;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(promote(id).url);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Make {name} a member?</DialogTitle>
                        <DialogDescription>
                            {promotion.target
                                ? `Their details are copied to ${promotion.target}, then the member form opens so you can fill in the rest. This record stays as their history.`
                                : 'Their date of birth decides which members list they join.'}
                        </DialogDescription>
                    </DialogHeader>

                    {blocked && (
                        <ul className="list-disc space-y-1 rounded-lg border border-destructive/40 bg-destructive/10 p-3 pl-7 text-sm">
                            {promotion.blockers.map((b) => (
                                <li key={b}>{b}</li>
                            ))}
                        </ul>
                    )}
                    {!blocked && unfinished > 0 && (
                        <p className="rounded-lg border border-amber-500/50 bg-amber-500/10 p-3 text-sm">
                            {unfinished} lesson
                            {unfinished === 1 ? ' is' : 's are'} not finished
                            yet. You can still make them a member.
                        </p>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="joined_on">
                            Date they became a member
                        </Label>
                        <Input
                            id="joined_on"
                            type="date"
                            max={new Date().toISOString().slice(0, 10)}
                            value={form.data.joined_on}
                            onChange={(e) =>
                                form.setData('joined_on', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.joined_on} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={blocked || form.processing}
                        >
                            Make member
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Card({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-3 rounded-lg border p-5">
            <h2 className="font-medium">{title}</h2>
            {children}
        </section>
    );
}

function VisitDialog({
    id,
    services,
    onClose,
}: {
    id: number;
    services: string[];
    onClose: () => void;
}) {
    const form = useForm({
        visited_on: new Date().toISOString().slice(0, 10),
        service: '',
        note: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeVisit(id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Returning visit</DialogTitle>
                        <DialogDescription>
                            Adds another visit to this person, with no new form.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="visited_on">Date</Label>
                        <Input
                            id="visited_on"
                            type="date"
                            max={new Date().toISOString().slice(0, 10)}
                            value={form.data.visited_on}
                            onChange={(e) =>
                                form.setData('visited_on', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.visited_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="visit-service">Service</Label>
                        <ChoiceOrOther
                            id="visit-service"
                            value={form.data.service}
                            options={services}
                            onChange={(v) => form.setData('service', v)}
                            placeholder="The service"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="visit-note">Note</Label>
                        <Input
                            id="visit-note"
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                            maxLength={250}
                        />
                    </div>
                    <DialogFooter>
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
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function StatusDialog({
    id,
    status,
    statuses,
    reasons,
    onClose,
}: {
    id: number;
    status: string;
    statuses: Record<string, string>;
    reasons: string[];
    onClose: () => void;
}) {
    const form = useForm({ status, reason: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(setStatus(id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Status</DialogTitle>
                        <DialogDescription>
                            Someone who stops coming goes inactive; nothing is
                            deleted, and they can be brought back.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="person-status">Status</Label>
                        <NativeSelect
                            id="person-status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {Object.entries(statuses).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    {form.data.status === 'inactive' && (
                        <div className="grid gap-2">
                            <Label htmlFor="person-reason">Why?</Label>
                            <ChoiceOrOther
                                id="person-reason"
                                value={form.data.reason}
                                options={reasons}
                                onChange={(v) => form.setData('reason', v)}
                                placeholder="The reason"
                                required
                            />
                            <InputError message={form.errors.reason} />
                        </div>
                    )}
                    <DialogFooter>
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
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

NewcomerShow.layout = {
    breadcrumbs: [{ title: 'Newcomers', href: index() }],
};
