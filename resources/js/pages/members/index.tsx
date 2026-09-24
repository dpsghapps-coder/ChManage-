import { Head, Link, router } from '@inertiajs/react';
import { Eye, MapPin, Pencil, Plus, Trash2, Undo2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { ClassBadge } from '@/components/class-badge';
import { mapUrl } from '@/components/gps-capture';
import { MemberSearchBox } from '@/components/member-search-box';
import { MemberViewDialog } from '@/components/member-view-dialog';
import type { ViewSubject } from '@/components/member-view-dialog';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import type { Paginated } from '@/components/pagination';
import { PersonAvatar } from '@/components/person-avatar';
import { StatusBadge, statusLabel } from '@/components/staff-status';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';
import {
    create,
    destroy as destroyMember,
    edit as editMember,
    index,
    related as relatedMember,
    report as reportMember,
    restore as restoreMember,
    show as showMember,
} from '@/routes/members';
import { create as createAdult } from '@/routes/members/adult';
import {
    destroy as destroyYoung,
    edit as editYoung,
    report as reportYoung,
    restore as restoreYoung,
    show as showYoung,
} from '@/routes/members/young';

type AdultRow = {
    id: number;
    member_number: string;
    title: string | null;
    full_name: string;
    sex: 'male' | 'female' | null;
    age: number | null;
    date_of_birth: string | null;
    marital_status: string | null;
    hometown: string | null;
    email: string | null;
    mobile: string | null;
    telephone: string | null;
    joined_on: string | null;
    status: string;
    is_communicant: boolean | null;
    photo_url: string | null;
    latitude: number | null;
    longitude: number | null;
    location_accuracy: number | null;
};

type YoungRow = {
    id: number;
    member_number: string;
    first_name: string | null;
    last_name: string | null;
    other_names: string | null;
    date_of_birth: string | null;
    joined_on: string | null;
    class: string | null;
    guardians: {
        id: number;
        relationship: string;
        name: string;
        phones: string[];
        photo_url: string | null;
        member_number: string | null;
        is_primary: boolean;
    }[];
    mobile: string | null;
    telephone: string | null;
    status: string;
    photo_url: string | null;
    latitude: number | null;
    longitude: number | null;
    location_accuracy: number | null;
};

type Props = {
    members: Paginated<AdultRow | YoungRow>;
    category: string;
    counts: Record<string, number>;
    filters: { q?: string; status?: string; sex?: string };
    statuses: string[];
};

/** Where a row's View, Edit, Delete and Restore actions go. */
type RowLinks = {
    show: string;
    edit: string;
    report: string;
    destroy: string;
    restore: string;
};

const categories = [
    {
        key: 'adults',
        label: 'Adults',
        hint: '18 and over, or no date of birth on file',
    },
    { key: 'junior_youth', label: 'Junior Youth', hint: 'Ages 12 to 17' },
    { key: 'children', label: 'Children Service', hint: 'Ages 0 to 11' },
] as const;

const dash = <span className="text-muted-foreground">—</span>;
const yesNo = (value: boolean) => (value ? 'Yes' : 'No');

const adultLinks = (id: number): RowLinks => ({
    show: showMember(id).url,
    edit: editMember(id).url,
    report: reportMember(id).url,
    destroy: destroyMember(id).url,
    restore: restoreMember(id).url,
});

const youngLinks = (id: number): RowLinks => ({
    show: showYoung(id).url,
    edit: editYoung(id).url,
    report: reportYoung(id).url,
    destroy: destroyYoung(id).url,
    restore: restoreYoung(id).url,
});

const contactsOf = (numbers: [string, string | null][]) =>
    numbers.flatMap(([label, phone]) => (phone ? [{ label, phone }] : []));

const adultSubject = (m: AdultRow): ViewSubject => ({
    name: `${m.title ? `${m.title} ` : ''}${m.full_name}`,
    number: m.member_number,
    status: m.status,
    photoUrl: m.photo_url,
    className: null,
    details: [
        { label: 'Sex', value: m.sex ? statusLabel(m.sex) : null },
        {
            label: 'Date of Birth',
            value: m.date_of_birth
                ? `${m.date_of_birth}${m.age !== null ? ` (${m.age} years)` : ''}`
                : null,
        },
        {
            label: 'Marital Status',
            value: m.marital_status ? statusLabel(m.marital_status) : null,
        },
        { label: 'Hometown', value: m.hometown },
        { label: 'Email', value: m.email },
        { label: 'Date Joined', value: m.joined_on },
        {
            label: 'Communicant',
            value: m.is_communicant === null ? null : yesNo(m.is_communicant),
        },
    ],
    contacts: contactsOf([
        ['Mobile', m.mobile],
        ['Telephone', m.telephone],
    ]),
    guardians: [],
    location:
        m.latitude !== null && m.longitude !== null
            ? { latitude: m.latitude, longitude: m.longitude }
            : null,
    links: adultLinks(m.id),
    relatedUrl: relatedMember(m.id).url,
});

const youngSubject = (c: YoungRow): ViewSubject => ({
    name: youngName(c),
    number: c.member_number,
    status: c.status,
    photoUrl: c.photo_url,
    className: c.class,
    details: [
        { label: 'First Name', value: c.first_name },
        { label: 'Surname', value: c.last_name },
        { label: 'Other Names', value: c.other_names },
        { label: 'Date of Birth', value: c.date_of_birth },
        { label: 'Date Joined', value: c.joined_on },
    ],
    contacts: contactsOf([
        ['Contact', c.mobile],
        ['Contact 2', c.telephone],
    ]),
    guardians: c.guardians.map((g) => ({
        id: g.id,
        name: g.name,
        relationship: g.relationship,
        isPrimary: g.is_primary,
        memberNumber: g.member_number,
        photoUrl: g.photo_url,
        phones: g.phones,
    })),
    location:
        c.latitude !== null && c.longitude !== null
            ? { latitude: c.latitude, longitude: c.longitude }
            : null,
    links: youngLinks(c.id),
    relatedUrl: null,
});

const youngName = (child: YoungRow) =>
    `${child.first_name ?? ''} ${child.last_name ?? ''}`.trim();

export default function MembersIndex({
    members,
    category,
    counts,
    filters,
    statuses,
}: Props) {
    const { can } = usePermission();
    const [deleting, setDeleting] = useState<{
        name: string;
        url: string;
    } | null>(null);
    const [viewing, setViewing] = useState<ViewSubject | null>(null);
    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [sex, setSex] = useState(filters.sex ?? '');
    const young = category !== 'adults';

    // The search text the list is currently showing, so typing does not re-run a search that already ran.
    const applied = useRef((filters.q ?? '').trim());

    const apply = (next: Record<string, string>) => {
        applied.current = (next.q ?? q).trim();
        router.get(
            index().url,
            { category, q, status, sex, ...next },
            { preserveState: true, replace: true },
        );
    };

    // The list follows the search box as you type (after a short pause), so a first name narrows the table straight away.
    useEffect(() => {
        const term = q.trim();

        // Wait for a second letter; clearing the box shows everyone again.
        if (term === applied.current || term.length === 1) {
            return;
        }

        const timer = setTimeout(() => apply({ q: term }), 350);

        return () => clearTimeout(timer);
        // apply() only reads state that is already part of the search.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({});
    };

    const actions = {
        canEdit: can('members.edit'),
        canDelete: can('members.delete'),
        onView: setViewing,
        onDelete: (name: string, url: string) => setDeleting({ name, url }),
        onRestore: (url: string) =>
            router.post(url, {}, { preserveScroll: true }),
    };

    return (
        <>
            <Head title="Members" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Members"
                    description="The church membership register. Deleted records are hidden unless you filter for them."
                    actions={
                        can('members.create') && (
                            <Button asChild>
                                <Link href={young ? create() : createAdult()}>
                                    <Plus /> Add New Member
                                </Link>
                            </Button>
                        )
                    }
                />

                <div
                    role="tablist"
                    aria-label="Member category"
                    className="-mx-4 flex gap-1 overflow-x-auto border-b px-4 sm:mx-0 sm:px-0"
                >
                    {categories.map((tab) => (
                        <button
                            key={tab.key}
                            type="button"
                            role="tab"
                            aria-selected={category === tab.key}
                            title={tab.hint}
                            onClick={() => apply({ category: tab.key })}
                            className={`-mb-px shrink-0 border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors ${category === tab.key ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                        >
                            {tab.label}
                            <span className="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs tabular-nums">
                                {counts[tab.key]?.toLocaleString()}
                            </span>
                        </button>
                    ))}
                </div>

                <form onSubmit={submit} className="flex flex-wrap gap-2">
                    <MemberSearchBox
                        value={q}
                        onChange={setQ}
                        onPick={(suggestion) => {
                            // Show exactly that member: their number, on the tab they belong to, with no other filter in the way.
                            setQ(suggestion.member_number);
                            setStatus('');
                            setSex('');
                            apply({
                                category: suggestion.category,
                                q: suggestion.member_number,
                                status: '',
                                sex: '',
                            });
                        }}
                    />
                    <NativeSelect
                        value={status}
                        onChange={(e) => {
                            setStatus(e.target.value);
                            apply({ status: e.target.value });
                        }}
                        className="w-full sm:w-44"
                        aria-label="Filter by status"
                    >
                        <option value="">Any status</option>
                        {statuses.map((s) => (
                            <option key={s} value={s}>
                                {statusLabel(s)}
                            </option>
                        ))}
                    </NativeSelect>
                    {!young && (
                        <NativeSelect
                            value={sex}
                            onChange={(e) => {
                                setSex(e.target.value);
                                apply({ sex: e.target.value });
                            }}
                            className="w-full sm:w-36"
                            aria-label="Filter by sex"
                        >
                            <option value="">Any sex</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </NativeSelect>
                    )}
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                {/* Phones: cards. */}
                <div className="grid gap-3 md:hidden">
                    {young ? (
                        <YoungCards
                            rows={members.data as YoungRow[]}
                            actions={actions}
                        />
                    ) : (
                        <AdultCards
                            rows={members.data as AdultRow[]}
                            actions={actions}
                        />
                    )}
                </div>

                <div className="hidden rounded-lg border md:block">
                    {young ? (
                        <YoungTable
                            rows={members.data as YoungRow[]}
                            actions={actions}
                        />
                    ) : (
                        <AdultTable
                            rows={members.data as AdultRow[]}
                            actions={actions}
                        />
                    )}
                </div>

                <Pagination page={members} />

                <MemberViewDialog
                    subject={viewing}
                    canEdit={can('members.edit')}
                    canReport={can('members.export')}
                    onClose={() => setViewing(null)}
                />

                <Dialog
                    open={deleting !== null}
                    onOpenChange={(open) => !open && setDeleting(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete {deleting?.name}?</DialogTitle>
                            <DialogDescription>
                                The record is kept but hidden from the register.
                                To bring it back, filter the list by Deleted
                                status and restore it.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDeleting(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => {
                                    if (deleting) {
                                        router.delete(deleting.url, {
                                            preserveScroll: true,
                                        });
                                    }

                                    setDeleting(null);
                                }}
                            >
                                Delete
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

type Actions = {
    onView: (subject: ViewSubject) => void;
    canEdit: boolean;
    canDelete: boolean;
    onDelete: (name: string, url: string) => void;
    onRestore: (url: string) => void;
};

function Empty({ columns }: { columns: number }) {
    return (
        <TableRow>
            <TableCell
                colSpan={columns}
                className="py-10 text-center text-muted-foreground"
            >
                No members match those filters.
            </TableCell>
        </TableRow>
    );
}

/** View, Edit and Delete (or Restore, for a deleted record) as icon buttons at the end of a row. */
function RowActions({
    name,
    status,
    links,
    actions,
    subject,
}: {
    name: string;
    status: string;
    links: RowLinks;
    actions: Actions;
    subject: ViewSubject;
}) {
    return (
        <div className="flex items-center justify-end gap-0.5">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                title="View"
                aria-label={`View ${name}`}
                onClick={() => actions.onView(subject)}
            >
                <Eye />
            </Button>
            {actions.canEdit && (
                <Button
                    variant="ghost"
                    size="icon"
                    asChild
                    title="Edit"
                    aria-label={`Edit ${name}`}
                >
                    <Link href={links.edit}>
                        <Pencil />
                    </Link>
                </Button>
            )}
            {actions.canDelete &&
                (status === 'deleted' ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title="Restore"
                        aria-label={`Restore ${name}`}
                        onClick={() => actions.onRestore(links.restore)}
                    >
                        <Undo2 />
                    </Button>
                ) : (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title="Delete"
                        aria-label={`Delete ${name}`}
                        className="text-destructive hover:text-destructive"
                        onClick={() => actions.onDelete(name, links.destroy)}
                    >
                        <Trash2 />
                    </Button>
                ))}
        </div>
    );
}

function MapLink({
    latitude,
    longitude,
}: {
    latitude: number | null;
    longitude: number | null;
}) {
    if (latitude === null || longitude === null) {
        return dash;
    }

    return (
        <a
            href={mapUrl(latitude, longitude)}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1 text-sm underline-offset-4 hover:underline"
        >
            <MapPin className="size-3.5" /> Map
        </a>
    );
}

function AdultTable({ rows, actions }: { rows: AdultRow[]; actions: Actions }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Full Name</TableHead>
                    <TableHead>Sex</TableHead>
                    <TableHead>Age</TableHead>
                    <TableHead>Mobile</TableHead>
                    <TableHead>Joined</TableHead>
                    <TableHead>Communicant</TableHead>
                    <TableHead>Location</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.length === 0 && <Empty columns={9} />}
                {rows.map((member) => (
                    <TableRow key={member.id}>
                        <TableCell>
                            <div className="flex items-center gap-3">
                                <PersonAvatar
                                    name={member.full_name}
                                    photoUrl={member.photo_url}
                                />
                                <div>
                                    <div className="font-medium">
                                        {member.title ? `${member.title} ` : ''}
                                        {member.full_name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {member.member_number}
                                    </div>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            {member.sex ? statusLabel(member.sex) : dash}
                        </TableCell>
                        <TableCell>{member.age ?? dash}</TableCell>
                        <TableCell>{member.mobile ?? dash}</TableCell>
                        <TableCell>{member.joined_on ?? dash}</TableCell>
                        <TableCell>
                            {member.is_communicant === null
                                ? dash
                                : yesNo(member.is_communicant)}
                        </TableCell>
                        <TableCell>
                            <MapLink
                                latitude={member.latitude}
                                longitude={member.longitude}
                            />
                        </TableCell>
                        <TableCell>
                            <StatusBadge status={member.status} />
                        </TableCell>
                        <TableCell>
                            <RowActions
                                name={member.full_name}
                                status={member.status}
                                links={adultLinks(member.id)}
                                actions={actions}
                                subject={adultSubject(member)}
                            />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

/** The simplified register used for Junior Youth and Children Service. */
function YoungTable({ rows, actions }: { rows: YoungRow[]; actions: Actions }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Full Name</TableHead>
                    <TableHead>Date of Birth</TableHead>
                    <TableHead>Date Joined</TableHead>
                    <TableHead>Class</TableHead>
                    <TableHead>Contact</TableHead>
                    <TableHead>Location</TableHead>
                    <TableHead>Guardians</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.length === 0 && <Empty columns={9} />}
                {rows.map((child) => (
                    <TableRow key={child.id}>
                        <TableCell>
                            <div className="flex items-center gap-3">
                                <PersonAvatar
                                    name={youngName(child)}
                                    photoUrl={child.photo_url}
                                />
                                <div>
                                    <div className="font-medium">
                                        {youngName(child) || dash}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {child.member_number}
                                    </div>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>{child.date_of_birth ?? dash}</TableCell>
                        <TableCell>{child.joined_on ?? dash}</TableCell>
                        <TableCell>
                            {child.class ? (
                                <ClassBadge value={child.class} />
                            ) : (
                                dash
                            )}
                        </TableCell>
                        <TableCell>{child.mobile ?? dash}</TableCell>
                        <TableCell>
                            <MapLink
                                latitude={child.latitude}
                                longitude={child.longitude}
                            />
                        </TableCell>
                        <TableCell>
                            {child.guardians.length === 0 && dash}
                            {child.guardians.map((g) => (
                                <div
                                    key={g.id}
                                    className="flex items-center gap-3 py-1"
                                >
                                    <PersonAvatar
                                        name={g.name}
                                        photoUrl={g.photo_url}
                                    />
                                    <div className="text-sm">
                                        <div>
                                            <span className="font-medium">
                                                {g.name}
                                            </span>
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                {g.relationship}
                                            </span>
                                            {g.is_primary && (
                                                <span className="ml-1 rounded bg-primary/10 px-1 text-xs text-primary">
                                                    primary
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {g.phones.length
                                                ? g.phones.join(' · ')
                                                : 'No phone on file'}
                                            {g.member_number
                                                ? ` · member ${g.member_number}`
                                                : ''}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </TableCell>
                        <TableCell>
                            <StatusBadge status={child.status} />
                        </TableCell>
                        <TableCell>
                            <RowActions
                                name={youngName(child)}
                                status={child.status}
                                links={youngLinks(child.id)}
                                actions={actions}
                                subject={youngSubject(child)}
                            />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

/** Phone layout for adults: one card per member. Tapping the card opens the View window. */
function AdultCards({ rows, actions }: { rows: AdultRow[]; actions: Actions }) {
    if (rows.length === 0) {
        return <EmptyCard />;
    }

    return rows.map((member) => {
        const subject = adultSubject(member);
        const facts = [
            member.sex ? statusLabel(member.sex) : null,
            member.age !== null ? `${member.age} yrs` : null,
            member.is_communicant ? 'Communicant' : null,
        ].filter(Boolean);

        return (
            <MemberCard
                key={member.id}
                name={`${member.title ? `${member.title} ` : ''}${member.full_name}`}
                number={member.member_number}
                photoUrl={member.photo_url}
                status={member.status}
                onOpen={() => actions.onView(subject)}
                footer={
                    <>
                        <MapLink
                            latitude={member.latitude}
                            longitude={member.longitude}
                        />
                        <RowActions
                            name={member.full_name}
                            status={member.status}
                            links={adultLinks(member.id)}
                            actions={actions}
                            subject={subject}
                        />
                    </>
                }
            >
                {facts.length > 0 && <p>{facts.join(' · ')}</p>}
                {member.mobile && (
                    <p className="text-muted-foreground">{member.mobile}</p>
                )}
            </MemberCard>
        );
    });
}

/** Phone layout for Junior Youth and Children Service. */
function YoungCards({ rows, actions }: { rows: YoungRow[]; actions: Actions }) {
    if (rows.length === 0) {
        return <EmptyCard />;
    }

    return rows.map((child) => {
        const subject = youngSubject(child);
        const primary =
            child.guardians.find((g) => g.is_primary) ?? child.guardians[0];

        return (
            <MemberCard
                key={child.id}
                name={youngName(child)}
                number={child.member_number}
                photoUrl={child.photo_url}
                status={child.status}
                onOpen={() => actions.onView(subject)}
                footer={
                    <>
                        <MapLink
                            latitude={child.latitude}
                            longitude={child.longitude}
                        />
                        <RowActions
                            name={youngName(child)}
                            status={child.status}
                            links={youngLinks(child.id)}
                            actions={actions}
                            subject={subject}
                        />
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    {child.class && <ClassBadge value={child.class} />}
                    {child.date_of_birth && (
                        <span className="text-muted-foreground">
                            Born {child.date_of_birth}
                        </span>
                    )}
                </div>
                {primary && (
                    <p className="text-muted-foreground">
                        {primary.relationship}: {primary.name}
                        {primary.phones[0] ? ` · ${primary.phones[0]}` : ''}
                        {child.guardians.length > 1
                            ? ` (+${child.guardians.length - 1})`
                            : ''}
                    </p>
                )}
            </MemberCard>
        );
    });
}

function MemberCard({
    name,
    number,
    photoUrl,
    status,
    onOpen,
    footer,
    children,
}: {
    name: string;
    number: string;
    photoUrl: string | null;
    status: string;
    onOpen: () => void;
    footer: ReactNode;
    children?: ReactNode;
}) {
    return (
        <div className="rounded-lg border">
            <button
                type="button"
                onClick={onOpen}
                className="flex w-full items-start gap-3 p-4 text-left transition-colors active:bg-muted/60"
            >
                <PersonAvatar name={name} photoUrl={photoUrl} />
                <div className="min-w-0 flex-1 space-y-1 text-sm">
                    <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                            <p className="truncate font-medium">
                                {name || '—'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {number}
                            </p>
                        </div>
                        <StatusBadge status={status} />
                    </div>
                    {children}
                </div>
            </button>
            <div className="flex items-center justify-between gap-2 border-t py-1 pr-2 pl-4">
                {footer}
            </div>
        </div>
    );
}

function EmptyCard() {
    return (
        <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
            No members match those filters.
        </p>
    );
}

MembersIndex.layout = { breadcrumbs: [{ title: 'Members', href: index() }] };
