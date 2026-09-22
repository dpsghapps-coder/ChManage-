import { Head, Link } from '@inertiajs/react';
import { ExternalLink, FileText, Pencil } from 'lucide-react';
import { ClassBadge } from '@/components/class-badge';
import { DetailList } from '@/components/detail-list';
import { mapUrl } from '@/components/gps-capture';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { StatusBadge } from '@/components/staff-status';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { index } from '@/routes/members';
import { edit, report } from '@/routes/members/young';

type Child = {
    id: number;
    member_number: string;
    first_name: string | null;
    last_name: string | null;
    other_names: string | null;
    date_of_birth: string | null;
    age: number | null;
    class: string | null;
    joined_on: string | null;
    mobile: string | null;
    telephone: string | null;
    status: string;
    photo_url: string | null;
    latitude: number | null;
    longitude: number | null;
    location_accuracy: number | null;
    guardians: {
        id: number;
        relationship: string;
        name: string;
        phones: string[];
        photo_url: string | null;
        member_number: string | null;
        is_primary: boolean;
    }[];
};

export default function ShowYoungMember({ child }: { child: Child }) {
    const { can } = usePermission();
    const name = `${child.first_name ?? ''} ${child.last_name ?? ''}`.trim();

    return (
        <>
            <Head title={name} />

            <div className="max-w-4xl space-y-6 p-4">
                <PageHeader
                    title={name}
                    actions={
                        <>
                            {can('members.export') && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={report(child.id).url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <FileText /> Generate Report
                                    </a>
                                </Button>
                            )}
                            {can('members.edit') && (
                                <Button asChild>
                                    <Link href={edit(child.id)}>
                                        <Pencil /> Edit
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="flex items-center gap-4 rounded-lg border p-5">
                    <PersonAvatar
                        name={name}
                        photoUrl={child.photo_url}
                        className="size-20 text-lg"
                    />
                    <div className="space-y-2">
                        <div className="text-sm text-muted-foreground">
                            {child.member_number}
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={child.status} />
                            {child.class && <ClassBadge value={child.class} />}
                        </div>
                    </div>
                </div>

                <section className="space-y-4 rounded-lg border p-5">
                    <h2 className="font-medium">Personal Details</h2>
                    <DetailList
                        items={[
                            { label: 'First Name', value: child.first_name },
                            { label: 'Surname', value: child.last_name },
                            { label: 'Other Names', value: child.other_names },
                            {
                                label: 'Date of Birth',
                                value: child.date_of_birth
                                    ? `${child.date_of_birth}${child.age !== null ? ` (${child.age} years)` : ''}`
                                    : null,
                            },
                            { label: 'Date Joined', value: child.joined_on },
                        ]}
                    />
                </section>

                <section className="space-y-4 rounded-lg border p-5">
                    <h2 className="font-medium">Contact</h2>
                    <DetailList
                        items={[
                            { label: 'Contact', value: child.mobile },
                            { label: 'Contact 2', value: child.telephone },
                            {
                                label: 'Home Location',
                                value:
                                    child.latitude !== null &&
                                    child.longitude !== null ? (
                                        <a
                                            href={mapUrl(
                                                child.latitude,
                                                child.longitude,
                                            )}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1 underline-offset-4 hover:underline"
                                        >
                                            {child.latitude.toFixed(5)},{' '}
                                            {child.longitude.toFixed(5)}
                                            <ExternalLink className="size-3.5" />
                                        </a>
                                    ) : null,
                            },
                        ]}
                    />
                </section>

                <section className="space-y-4">
                    <h2 className="font-medium">Guardians</h2>
                    {child.guardians.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No guardians recorded yet.
                        </p>
                    )}
                    <div className="grid gap-3 sm:grid-cols-2">
                        {child.guardians.map((guardian) => (
                            <div
                                key={guardian.id}
                                className="flex items-center gap-3 rounded-lg border p-4"
                            >
                                <PersonAvatar
                                    name={guardian.name}
                                    photoUrl={guardian.photo_url}
                                    className="size-12"
                                />
                                <div className="text-sm">
                                    <div className="font-medium">
                                        {guardian.name}
                                        {guardian.is_primary && (
                                            <span className="ml-2 rounded bg-primary/10 px-1.5 py-0.5 text-xs font-normal text-primary">
                                                primary
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {guardian.relationship}
                                        {guardian.member_number
                                            ? ` · member ${guardian.member_number}`
                                            : ''}
                                    </div>
                                    <div>
                                        {guardian.phones.length
                                            ? guardian.phones.join(' · ')
                                            : 'No phone on file'}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}

ShowYoungMember.layout = {
    breadcrumbs: [{ title: 'Members', href: index() }],
};
