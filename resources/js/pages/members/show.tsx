import { Head, Link } from '@inertiajs/react';
import { FileText, Pencil } from 'lucide-react';
import { useState } from 'react';
import { MemberSection, sectionTabs } from '@/components/member-related';
import type {
    MemberDetail,
    RelatedData,
    SectionTab,
} from '@/components/member-related';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { StatusBadge } from '@/components/staff-status';
import { TabBar } from '@/components/tab-bar';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { edit, index, report } from '@/routes/members';

export default function ShowMember({
    member,
    related,
}: {
    member: MemberDetail;
    related: RelatedData;
}) {
    const { can } = usePermission();
    const [tab, setTab] = useState<SectionTab>('basic');
    const name = `${member.title ? `${member.title} ` : ''}${member.full_name}`;

    return (
        <>
            <Head title={member.full_name} />

            <div className="max-w-4xl space-y-6 p-4">
                <PageHeader
                    title={name}
                    actions={
                        <>
                            {can('members.export') && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={report(member.id).url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <FileText /> Generate Report
                                    </a>
                                </Button>
                            )}
                            {can('members.edit') && (
                                <Button asChild>
                                    <Link href={edit(member.id)}>
                                        <Pencil /> Edit
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="flex items-center gap-4 rounded-lg border p-5">
                    <PersonAvatar
                        name={member.full_name}
                        photoUrl={member.photo_url}
                        className="size-20 text-lg"
                    />
                    <div className="space-y-1">
                        <div className="text-sm text-muted-foreground">
                            {member.member_number}
                        </div>
                        <StatusBadge status={member.status} />
                    </div>
                </div>

                <TabBar
                    label="Member details"
                    tabs={sectionTabs(related)}
                    value={tab}
                    onChange={setTab}
                />

                <MemberSection tab={tab} member={member} related={related} />
            </div>
        </>
    );
}

ShowMember.layout = { breadcrumbs: [{ title: 'Members', href: index() }] };
