import { Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import type { ReactNode } from 'react';
import { ClassBadge } from '@/components/class-badge';
import { DetailList } from '@/components/detail-list';
import { FamilyTree } from '@/components/family-tree';
import { mapUrl } from '@/components/gps-capture';
import { PersonAvatar } from '@/components/person-avatar';
import { ReachButtons } from '@/components/reach-buttons';
import { StatusBadge, statusLabel } from '@/components/staff-status';
import { Badge } from '@/components/ui/badge';
import { show as showMember } from '@/routes/members';
import { show as showYoung } from '@/routes/members/young';

/** An adult member's own fields. */
export type MemberDetail = {
    id: number;
    member_number: string;
    title: string | null;
    first_name: string | null;
    last_name: string | null;
    other_names: string;
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
    facebook_id: string | null;
    instagram_id: string | null;
    twitter_id: string | null;
    tiktok_id: string | null;
    residence: string | null;
    latitude: number | null;
    longitude: number | null;
    location_accuracy: number | null;
    marital_status: string | null;
    marriage_type: string | null;
    maiden_name: string | null;
    marriage_date: string | null;
    marriage_church: string | null;
    spouse_name: string | null;
    spouse_member: {
        id: number;
        member_number: string;
        full_name: string;
    } | null;
    father_name: string | null;
    father_member: LinkedMember;
    mother_name: string | null;
    mother_member: LinkedMember;
    joined_on: string | null;
    previous_congregation: string | null;
    generational_group: string | null;
    is_communicant: boolean | null;
    non_communicant: boolean;
    non_communicant_reason: string | null;
    status: string;
    photo_url: string | null;
};

type LinkedMember = {
    id: number;
    member_number: string;
    full_name: string;
} | null;

/** A relative's name, linked to their own record when they are a member. */
function linkedName(linked: LinkedMember, name: string | null) {
    if (!linked) {
        return name;
    }

    return (
        <Link
            href={showMember(linked.id)}
            className="underline-offset-4 hover:underline"
        >
            {linked.full_name}{' '}
            <span className="text-xs text-muted-foreground">
                ({linked.member_number})
            </span>
        </Link>
    );
}

/** The records that hang off a member. Children are not stored on the member: they come from the Children Service / Junior Youth register. */
export type RelatedData = {
    next_of_kin: {
        name: string;
        relationship: string;
        phone: string;
        residential_address: string;
        postal_address: string;
        member_id: number | null;
        member: LinkedMember;
    };
    emergency_contact: {
        name: string;
        phone: string;
        relationship: string;
        member_id: number | null;
        member: LinkedMember;
    };
    sacraments: Record<
        'baptism' | 'confirmation',
        {
            date: string;
            presbytery: string;
            district: string;
            /** The congregation. */
            place: string;
            minister: string;
        }
    >;
    groups: { id: number; name: string; short_name: string | null }[];
    committee_terms?: {
        committee: string;
        position: string;
        started_on: string;
        ends_on: string;
        serving: boolean;
    }[];
    service_records: {
        type: string;
        type_label: string;
        name: string;
        position: string;
        started_on: string;
        ended_on: string;
    }[];
    /** Adult members who name this member as their father or mother. */
    adult_children: {
        id: number;
        name: string;
        member_number: string;
        relationship: string;
        photo_url: string | null;
    }[];
    children: {
        id: number;
        name: string;
        member_number: string;
        class: string | null;
        status: string;
        relationship: string;
        photo_url: string | null;
    }[];
};

export type SectionTab =
    | 'basic'
    | 'contact'
    | 'family'
    | 'church'
    | 'service'
    | 'sacraments';

/** The sections of the member details, in the order they are read. Counts appear once the data is known. */
export const sectionTabs = (related: RelatedData | null) => [
    { key: 'basic' as const, label: 'Basic Info' },
    { key: 'contact' as const, label: 'Contact' },
    { key: 'family' as const, label: 'Family' },
    { key: 'church' as const, label: 'Church' },
    {
        key: 'service' as const,
        label: 'Service',
        count: related?.service_records.length,
    },
    { key: 'sacraments' as const, label: 'Sacraments' },
];

const empty = (text: string) => (
    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
        {text}
    </p>
);

const link = (href: string, text: string) => (
    <a
        href={href}
        target="_blank"
        rel="noopener noreferrer"
        className="inline-flex items-center gap-1 underline-offset-4 hover:underline"
    >
        {text} <ExternalLink className="size-3.5" />
    </a>
);

/** A social account as a link when it looks like a handle; a name with spaces in it is shown as plain text. */
const social = (
    kind: 'facebook' | 'instagram' | 'twitter' | 'tiktok',
    value: string | null,
): ReactNode => {
    if (!value) {
        return null;
    }

    const handle = value.replace(/^@/, '');
    const urls = {
        facebook: `https://facebook.com/${handle}`,
        instagram: `https://instagram.com/${handle}`,
        twitter: `https://x.com/${handle}`,
        tiktok: `https://tiktok.com/@${handle}`,
    };

    if (/^https?:\/\//.test(value)) {
        return link(value, value);
    }

    return /\s/.test(value) ? value : link(urls[kind], value);
};

const date = (value: string | null | undefined, age?: number | null) =>
    value
        ? `${value}${age !== null && age !== undefined ? ` (${age} years)` : ''}`
        : null;

/** A person's name, linked to their own member record when they are also a member. */
function LinkedName({ name, member }: { name: string; member: LinkedMember }) {
    if (!member) {
        return name || '—';
    }

    return (
        <Link
            href={showMember(member.id)}
            className="underline-offset-4 hover:underline"
        >
            {name || member.full_name}{' '}
            <span className="text-xs text-muted-foreground">
                ({member.member_number})
            </span>
        </Link>
    );
}

function ContactLine({
    label,
    phone,
    name,
}: {
    label: string;
    phone: string | null;
    name: string;
}) {
    if (!phone) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="text-sm">
                <div className="font-medium tabular-nums">{phone}</div>
                <div className="text-xs text-muted-foreground">{label}</div>
            </div>
            <ReachButtons phone={phone} name={name} />
        </div>
    );
}

/** The content of one section of the member details. */
export function MemberSection({
    tab,
    member,
    related,
}: {
    tab: SectionTab;
    member: MemberDetail;
    related: RelatedData;
}) {
    if (tab === 'basic') {
        return (
            <div className="space-y-5">
                <DetailList
                    items={[
                        { label: 'Member ID', value: member.member_number },
                        { label: 'Title', value: member.title },
                        { label: 'First Name', value: member.first_name },
                        { label: 'Surname', value: member.last_name },
                        { label: 'Other Names', value: member.other_names },
                        {
                            label: 'Sex',
                            value: member.sex ? statusLabel(member.sex) : null,
                        },
                        {
                            label: 'Date of Birth',
                            value: date(member.date_of_birth, member.age),
                        },
                        {
                            label: 'Place of Birth',
                            value: member.place_of_birth,
                        },
                        { label: 'Home Town', value: member.hometown },
                        {
                            label: 'Occupation / Profession',
                            value: member.occupation,
                        },
                    ]}
                />
            </div>
        );
    }

    if (tab === 'contact') {
        const contact = related.emergency_contact;
        const hasContact = contact.name || contact.phone;

        return (
            <div className="space-y-5">
                {!member.mobile && !member.telephone && (
                    <p className="text-sm text-muted-foreground">
                        No phone number on file.
                    </p>
                )}
                <ContactLine
                    label="Primary Mobile"
                    phone={member.mobile}
                    name={member.full_name}
                />
                <ContactLine
                    label="Secondary Mobile"
                    phone={member.telephone}
                    name={member.full_name}
                />
                <DetailList
                    items={[
                        {
                            label: 'Email',
                            value: member.email
                                ? link(`mailto:${member.email}`, member.email)
                                : null,
                        },
                        {
                            label: 'Facebook',
                            value: social('facebook', member.facebook_id),
                        },
                        {
                            label: 'Instagram',
                            value: social('instagram', member.instagram_id),
                        },
                        {
                            label: 'Twitter / X',
                            value: social('twitter', member.twitter_id),
                        },
                        {
                            label: 'TikTok',
                            value: social('tiktok', member.tiktok_id),
                        },
                        { label: 'Residence', value: member.residence },
                        {
                            label: 'Home Location',
                            value:
                                member.latitude !== null &&
                                member.longitude !== null
                                    ? link(
                                          mapUrl(
                                              member.latitude,
                                              member.longitude,
                                          ),
                                          'Open Map',
                                      )
                                    : null,
                        },
                    ]}
                />
                {hasContact ? (
                    <div className="space-y-4 rounded-lg border p-4">
                        <h3 className="font-medium">Emergency Contact</h3>
                        <DetailList
                            items={[
                                {
                                    label: 'Name',
                                    value: (
                                        <LinkedName
                                            name={contact.name}
                                            member={contact.member}
                                        />
                                    ),
                                },
                                { label: 'Phone', value: contact.phone },
                                {
                                    label: 'Relationship',
                                    value: contact.relationship,
                                },
                            ]}
                        />
                        {contact.phone && (
                            <ReachButtons
                                phone={contact.phone}
                                name={contact.name || member.full_name}
                            />
                        )}
                    </div>
                ) : (
                    empty('No emergency contact recorded.')
                )}
            </div>
        );
    }

    if (tab === 'family') {
        const kin = related.next_of_kin;
        const hasKin =
            kin.name ||
            kin.phone ||
            kin.residential_address ||
            kin.postal_address;

        return (
            <div className="space-y-5">
                <DetailList
                    items={[
                        {
                            label: "Father's Name",
                            value: linkedName(
                                member.father_member,
                                member.father_name,
                            ),
                        },
                        {
                            label: "Mother's Name",
                            value: linkedName(
                                member.mother_member,
                                member.mother_name,
                            ),
                        },
                        {
                            label: 'Marital Status',
                            value: member.marital_status
                                ? statusLabel(member.marital_status)
                                : null,
                        },
                        // Marriage details are shown for married members only.
                        ...(member.marital_status !== 'married'
                            ? []
                            : [
                                  {
                                      label: 'Marriage Type',
                                      value: member.marriage_type
                                          ? statusLabel(member.marriage_type)
                                          : null,
                                  },
                                  {
                                      label: 'Date of Marriage',
                                      value: member.marriage_date,
                                  },
                                  {
                                      label: 'Church of Marriage',
                                      value: member.marriage_church,
                                  },
                                  {
                                      label: 'Maiden Name',
                                      value: member.maiden_name,
                                  },
                                  {
                                      label: 'Spouse',
                                      value: linkedName(
                                          member.spouse_member,
                                          member.spouse_name,
                                      ),
                                  },
                              ]),
                    ]}
                />
                <div className="space-y-2">
                    <div className="text-xs text-muted-foreground">
                        Children
                    </div>
                    {related.children.length === 0 &&
                    related.adult_children.length === 0 ? (
                        empty(
                            'No member names this member as a parent, and no children on the Children Service or Junior Youth register list them as a guardian.',
                        )
                    ) : (
                        <div className="space-y-2">
                            {related.adult_children.map((child) => (
                                <div
                                    key={`adult-${child.id}`}
                                    className="flex items-center gap-3 rounded-lg border p-3"
                                >
                                    <PersonAvatar
                                        name={child.name}
                                        photoUrl={child.photo_url}
                                        className="size-10"
                                    />
                                    <div className="text-sm">
                                        <Link
                                            href={showMember(child.id)}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {child.name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {child.member_number} ·{' '}
                                            {child.relationship}
                                        </div>
                                    </div>
                                </div>
                            ))}
                            {related.children.map((child) => (
                                <div
                                    key={child.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                                >
                                    <div className="flex items-center gap-3">
                                        <PersonAvatar
                                            name={child.name}
                                            photoUrl={child.photo_url}
                                            className="size-10"
                                        />
                                        <div className="text-sm">
                                            <Link
                                                href={showYoung(child.id)}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {child.name}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {child.member_number}
                                                {child.relationship
                                                    ? ` · ${child.relationship}`
                                                    : ''}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {child.class && (
                                            <ClassBadge value={child.class} />
                                        )}
                                        <StatusBadge status={child.status} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
                {hasKin ? (
                    <div className="space-y-4 rounded-lg border p-4">
                        <h3 className="font-medium">Next of Kin</h3>
                        <DetailList
                            items={[
                                {
                                    label: 'Name',
                                    value: (
                                        <LinkedName
                                            name={kin.name}
                                            member={kin.member}
                                        />
                                    ),
                                },
                                {
                                    label: 'Relationship',
                                    value: kin.relationship,
                                },
                                { label: 'Phone', value: kin.phone },
                                {
                                    label: 'Residential Address',
                                    value: kin.residential_address,
                                },
                                {
                                    label: 'Postal Address',
                                    value: kin.postal_address,
                                },
                            ]}
                        />
                        {kin.phone && (
                            <ReachButtons
                                phone={kin.phone}
                                name={kin.name || member.full_name}
                            />
                        )}
                    </div>
                ) : (
                    empty('No next of kin recorded.')
                )}
                <FamilyTree member={member} related={related} />
            </div>
        );
    }

    if (tab === 'church') {
        return (
            <div className="space-y-5">
                <DetailList
                    items={[
                        { label: 'Date Joined', value: member.joined_on },
                        {
                            label: 'Previous Congregation',
                            value: member.previous_congregation,
                        },
                        {
                            label: 'Generational Group',
                            value: member.generational_group,
                        },
                    ]}
                />
                <div className="space-y-2">
                    <div className="text-xs text-muted-foreground">
                        Service Groups
                    </div>
                    {related.groups.length === 0 ? (
                        <p className="text-sm text-muted-foreground">None</p>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            {related.groups.map((group) => (
                                <Badge
                                    key={group.id}
                                    variant="secondary"
                                    className="px-3 py-1 text-sm"
                                >
                                    {group.name}
                                    {group.short_name &&
                                    group.short_name !== group.name
                                        ? ` (${group.short_name})`
                                        : ''}
                                </Badge>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        );
    }

    if (tab === 'service') {
        const terms = related.committee_terms ?? [];

        return related.service_records.length === 0 && terms.length === 0 ? (
            empty('No service records.')
        ) : (
            <div className="space-y-4">
                {related.service_records.length > 0 && (
                    <div className="space-y-2">
                        {related.service_records.map((record, i) => (
                            <div key={i} className="rounded-lg border p-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {record.name}
                                    </span>
                                    <Badge variant="secondary">
                                        {record.type_label}
                                    </Badge>
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {record.position}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {record.started_on || '—'} –{' '}
                                    {record.ended_on || 'Present'}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
                {terms.length > 0 && (
                    <div className="space-y-2">
                        <h4 className="text-xs font-medium text-muted-foreground">
                            Committee terms, from the committees&apos; own lists
                        </h4>
                        {terms.map((term, i) => (
                            <div key={i} className="rounded-lg border p-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {term.committee}
                                    </span>
                                    <Badge
                                        variant={
                                            term.serving
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {term.serving ? 'Serving' : 'Past'}
                                    </Badge>
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {term.position}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {term.started_on} –{' '}
                                    {term.ends_on || 'no fixed end'}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    }

    if (tab === 'sacraments') {
        return (
            <div className="space-y-4">
                <DetailList
                    items={[
                        {
                            label: 'Non-Communicant',
                            value:
                                member.is_communicant === null
                                    ? null
                                    : member.non_communicant
                                      ? 'Yes'
                                      : 'No',
                        },
                        ...(member.non_communicant
                            ? [
                                  {
                                      label: 'Reason',
                                      value: member.non_communicant_reason,
                                  },
                              ]
                            : []),
                    ]}
                />
                <div className="grid gap-3 sm:grid-cols-2">
                    {(['baptism', 'confirmation'] as const).map((kind) => {
                        const sacrament = related.sacraments[kind];
                        const recorded =
                            sacrament.date ||
                            sacrament.presbytery ||
                            sacrament.district ||
                            sacrament.place ||
                            sacrament.minister;

                        return (
                            <div
                                key={kind}
                                className="space-y-3 rounded-lg border p-4"
                            >
                                <h3 className="font-medium capitalize">
                                    {kind}
                                </h3>
                                {recorded ? (
                                    <DetailList
                                        items={[
                                            {
                                                label: 'Date',
                                                value: sacrament.date,
                                            },
                                            {
                                                label: 'Congregation',
                                                value: sacrament.place,
                                            },
                                            {
                                                label: 'District',
                                                value: sacrament.district,
                                            },
                                            {
                                                label: 'Presbytery',
                                                value: sacrament.presbytery,
                                            },
                                            {
                                                label: 'Minister',
                                                value: sacrament.minister,
                                            },
                                        ]}
                                    />
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Not recorded.
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        );
    }

    return null;
}
