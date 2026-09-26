import { Head, Link, router } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { RequestStatus } from '@/components/request-status';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { logout } from '@/routes/portal';
import { cancel } from '@/routes/portal/requests';
import { create as newRequest } from '@/routes/portal/requests';

type Kin = {
    name: string;
    relationship: string;
    phone: string;
    residential_address?: string;
};
type Member = {
    member_number: string;
    title: string | null;
    full_name: string;
    sex: string | null;
    date_of_birth: string | null;
    age: number | null;
    place_of_birth: string | null;
    hometown: string | null;
    occupation: string | null;
    mobile: string | null;
    telephone: string | null;
    email: string | null;
    residence: string | null;
    marital_status: string | null;
    marriage_type: string | null;
    marriage_date: string | null;
    marriage_church: string | null;
    spouse_name: string | null;
    father_name: string | null;
    mother_name: string | null;
    joined_on: string | null;
    previous_congregation: string | null;
    generational_group: string | null;
    is_communicant: boolean | null;
    next_of_kin: Kin;
    emergency_contact: Kin;
    sacraments: Record<
        string,
        { date: string; place: string; minister: string }
    >;
    groups: string[];
    service_records: {
        type_label: string;
        name: string;
        position: string;
        started_on: string;
        ended_on: string;
    }[];
    children: {
        name: string;
        member_number: string;
        class: string | null;
        relationship: string;
    }[];
};
type Term = {
    committee: string;
    position: string;
    started_on: string;
    ends_on: string;
    serving: boolean;
};
type Programme = {
    kind: 'event' | 'meeting';
    title: string;
    date: string;
    time: string | null;
    venue: string | null;
    note: string;
    invited: boolean;
};
type Attendance = {
    meetings: { title: string; date: string; mark: string }[];
    counts: { present: number; apologies: number; absent: number };
};

const date = (value: string | null | undefined) =>
    value
        ? new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : null;

const tabs = [
    { key: 'details', label: 'My details' },
    { key: 'coming', label: 'Coming up' },
    { key: 'committees', label: 'Committees' },
    { key: 'attendance', label: 'Attendance' },
    { key: 'requests', label: 'Requests' },
] as const;

type MyRequest = {
    id: number;
    reference: string;
    type_label: string;
    status: string;
    made_on: string;
    answers: { label: string; value: string }[];
    response: string | null;
    can_withdraw: boolean;
};
type RequestType = { key: string; label: string; description: string };

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return (
        <div className="flex justify-between gap-4 border-b py-2 text-sm last:border-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{value}</dd>
        </div>
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
        <section className="rounded-lg border p-4">
            <h2 className="mb-2 font-medium">{title}</h2>
            <dl>{children}</dl>
        </section>
    );
}

export default function PortalHome({
    member,
    committees,
    programmes,
    attendance,
    requests,
    requestTypes,
    tab: startTab,
}: {
    member: Member;
    committees: Term[];
    programmes: Programme[];
    attendance: Attendance;
    requests: MyRequest[];
    requestTypes: RequestType[];
    tab: string | null;
}) {
    const [tab, setTab] = useState<(typeof tabs)[number]['key']>(
        tabs.find((t) => t.key === startTab)?.key ?? 'details',
    );
    const first = member.full_name.split(' ')[0];

    return (
        <div className="min-h-svh bg-background">
            <Head title="My portal" />

            <header className="border-b">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3">
                    <div className="flex items-center gap-3">
                        <AppLogoIcon className="size-7 fill-current text-foreground" />
                        <div className="leading-tight">
                            <p className="font-medium">Welcome, {first}</p>
                            <p className="text-xs text-muted-foreground">
                                {member.member_number}
                            </p>
                        </div>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.post(logout().url)}
                    >
                        <LogOut /> Sign out
                    </Button>
                </div>
                <nav
                    className="mx-auto flex max-w-3xl gap-1 overflow-x-auto px-4"
                    aria-label="Portal sections"
                >
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            onClick={() => setTab(t.key)}
                            aria-current={tab === t.key ? 'page' : undefined}
                            className={cn(
                                'border-b-2 px-3 py-2 text-sm whitespace-nowrap',
                                tab === t.key
                                    ? 'border-primary font-medium'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t.label}
                        </button>
                    ))}
                </nav>
            </header>

            <main className="mx-auto max-w-3xl space-y-4 p-4">
                {tab === 'details' && (
                    <>
                        <Card title="Personal">
                            <Row
                                label="Name"
                                value={`${member.title ? member.title + ' ' : ''}${member.full_name}`}
                            />
                            <Row
                                label="Sex"
                                value={
                                    member.sex &&
                                    member.sex[0].toUpperCase() +
                                        member.sex.slice(1)
                                }
                            />
                            <Row
                                label="Date of birth"
                                value={
                                    date(member.date_of_birth) &&
                                    `${date(member.date_of_birth)} (${member.age})`
                                }
                            />
                            <Row
                                label="Place of birth"
                                value={member.place_of_birth}
                            />
                            <Row label="Hometown" value={member.hometown} />
                            <Row label="Occupation" value={member.occupation} />
                            <Row
                                label="Group"
                                value={member.generational_group}
                            />
                        </Card>
                        <Card title="Contact">
                            <Row label="Mobile" value={member.mobile} />
                            <Row label="Telephone" value={member.telephone} />
                            <Row label="Email" value={member.email} />
                            <Row label="Residence" value={member.residence} />
                        </Card>
                        <Card title="Marriage and family">
                            <Row
                                label="Marital status"
                                value={
                                    member.marital_status &&
                                    member.marital_status[0].toUpperCase() +
                                        member.marital_status.slice(1)
                                }
                            />
                            <Row
                                label="Marriage type"
                                value={member.marriage_type}
                            />
                            <Row
                                label="Marriage date"
                                value={date(member.marriage_date)}
                            />
                            <Row
                                label="Married at"
                                value={member.marriage_church}
                            />
                            <Row label="Spouse" value={member.spouse_name} />
                            <Row label="Father" value={member.father_name} />
                            <Row label="Mother" value={member.mother_name} />
                            {member.children.map((c) => (
                                <Row
                                    key={c.member_number}
                                    label={c.relationship}
                                    value={`${c.name}${c.class ? ` (${c.class})` : ''}`}
                                />
                            ))}
                        </Card>
                        <Card title="In the church">
                            <Row
                                label="Joined"
                                value={date(member.joined_on)}
                            />
                            <Row
                                label="Previous congregation"
                                value={member.previous_congregation}
                            />
                            <Row
                                label="Communicant"
                                value={
                                    member.is_communicant === null
                                        ? null
                                        : member.is_communicant
                                          ? 'Yes'
                                          : 'No'
                                }
                            />
                            {Object.entries(member.sacraments).map(
                                ([kind, s]) => (
                                    <Row
                                        key={kind}
                                        label={
                                            kind[0].toUpperCase() +
                                            kind.slice(1)
                                        }
                                        value={[date(s.date), s.place]
                                            .filter(Boolean)
                                            .join(', ')}
                                    />
                                ),
                            )}
                            <Row
                                label="Groups"
                                value={member.groups.join(', ')}
                            />
                            {member.service_records.map((r, i) => (
                                <Row
                                    key={i}
                                    label={r.type_label}
                                    value={`${r.name}${r.position ? `, ${r.position}` : ''}${r.started_on ? ` (${date(r.started_on)}${r.ended_on ? ` – ${date(r.ended_on)}` : ''})` : ''}`}
                                />
                            ))}
                        </Card>
                        <Card title="Next of kin and emergency contact">
                            <Row
                                label="Next of kin"
                                value={
                                    member.next_of_kin.name &&
                                    `${member.next_of_kin.name}${member.next_of_kin.relationship ? ` (${member.next_of_kin.relationship})` : ''}`
                                }
                            />
                            <Row
                                label="Kin phone"
                                value={member.next_of_kin.phone}
                            />
                            <Row
                                label="Emergency contact"
                                value={
                                    member.emergency_contact.name &&
                                    `${member.emergency_contact.name}${member.emergency_contact.relationship ? ` (${member.emergency_contact.relationship})` : ''}`
                                }
                            />
                            <Row
                                label="Emergency phone"
                                value={member.emergency_contact.phone}
                            />
                        </Card>
                    </>
                )}

                {tab === 'coming' &&
                    (programmes.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                            Nothing coming up for you right now.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {programmes.map((p, i) => (
                                <li key={i} className="flex gap-4 p-3">
                                    <div className="w-24 shrink-0 text-sm">
                                        <p className="font-medium">
                                            {date(p.date)}
                                        </p>
                                        {p.time && (
                                            <p className="text-muted-foreground">
                                                {p.time}
                                            </p>
                                        )}
                                    </div>
                                    <div className="min-w-0 text-sm">
                                        <p className="font-medium">{p.title}</p>
                                        <p className="text-muted-foreground">
                                            {[p.note, p.venue]
                                                .filter(Boolean)
                                                .join(' · ')}
                                            {p.kind === 'event' &&
                                                p.invited &&
                                                ' · You are on the list'}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ))}

                {tab === 'committees' &&
                    (committees.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                            You are not on any committee.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {committees.map((t, i) => (
                                <li key={i} className="p-3 text-sm">
                                    <p className="font-medium">
                                        {t.committee}{' '}
                                        <span className="font-normal text-muted-foreground">
                                            · {t.position}
                                        </span>
                                    </p>
                                    <p className="text-muted-foreground">
                                        {date(t.started_on)} –{' '}
                                        {t.ends_on
                                            ? date(t.ends_on)
                                            : 'no end date'}
                                        {t.serving
                                            ? ' · Serving now'
                                            : ' · Ended'}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    ))}

                {tab === 'attendance' && (
                    <>
                        <div className="grid grid-cols-3 gap-3 text-center">
                            {(['present', 'apologies', 'absent'] as const).map(
                                (k) => (
                                    <div
                                        key={k}
                                        className="rounded-lg border p-3"
                                    >
                                        <p className="text-2xl font-semibold">
                                            {attendance.counts[k]}
                                        </p>
                                        <p className="text-xs text-muted-foreground capitalize">
                                            {k}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>
                        {attendance.meetings.length === 0 ? (
                            <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                No meeting attendance recorded yet.
                            </p>
                        ) : (
                            <ul className="divide-y rounded-lg border">
                                {attendance.meetings.map((m, i) => (
                                    <li
                                        key={i}
                                        className="flex justify-between gap-3 p-3 text-sm"
                                    >
                                        <span>
                                            <span className="font-medium">
                                                {m.title}
                                            </span>{' '}
                                            <span className="text-muted-foreground">
                                                · {date(m.date)}
                                            </span>
                                        </span>
                                        <span className="capitalize">
                                            {m.mark}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Meetings are recorded as present, apologies or
                            absent. Attendance at services and events will
                            appear here once it is being taken.
                        </p>
                    </>
                )}

                {tab === 'requests' && (
                    <>
                        <section className="rounded-lg border p-4">
                            <h2 className="mb-1 font-medium">Make a request</h2>
                            <p className="mb-3 text-sm text-muted-foreground">
                                Choose what you need. The church office will see
                                it and reply here.
                            </p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {requestTypes.map((t) => (
                                    <Link
                                        key={t.key}
                                        href={newRequest([t.key])}
                                        className="rounded-md border p-3 text-sm hover:border-primary/50 hover:bg-accent/50"
                                    >
                                        <span className="block font-medium">
                                            {t.label}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {t.description}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        </section>

                        <section className="space-y-2">
                            <h2 className="font-medium">My requests</h2>
                            {requests.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    You have not made any requests.
                                </p>
                            ) : (
                                <ul className="divide-y rounded-lg border">
                                    {requests.map((r) => (
                                        <li
                                            key={r.id}
                                            className="space-y-2 p-3 text-sm"
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <p className="font-medium">
                                                    {r.type_label}{' '}
                                                    <span className="font-normal text-muted-foreground">
                                                        · {r.reference} ·{' '}
                                                        {date(r.made_on)}
                                                    </span>
                                                </p>
                                                <RequestStatus
                                                    status={r.status}
                                                />
                                            </div>
                                            {r.answers.length > 0 && (
                                                <ul className="text-muted-foreground">
                                                    {r.answers.map((a) => (
                                                        <li key={a.label}>
                                                            {a.label}: {a.value}
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                            {r.response && (
                                                <p className="rounded-md bg-muted p-2">
                                                    <span className="text-xs text-muted-foreground uppercase">
                                                        Reply
                                                    </span>
                                                    <span className="block whitespace-pre-line">
                                                        {r.response}
                                                    </span>
                                                </p>
                                            )}
                                            {r.can_withdraw && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            cancel(r.id).url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Withdraw
                                                </Button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </>
                )}
            </main>
        </div>
    );
}
