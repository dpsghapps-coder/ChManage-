import { Link } from '@inertiajs/react';
import { ExternalLink, FileText, MapPin, Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ClassBadge } from '@/components/class-badge';
import { DetailList } from '@/components/detail-list';
import { mapUrl } from '@/components/gps-capture';
import { MemberSection, sectionTabs } from '@/components/member-related';
import type {
    MemberDetail,
    RelatedData,
    SectionTab,
} from '@/components/member-related';
import { PersonAvatar } from '@/components/person-avatar';
import { ReachButtons } from '@/components/reach-buttons';
import { StatusBadge } from '@/components/staff-status';
import { TabBar } from '@/components/tab-bar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ViewSubject = {
    name: string;
    number: string;
    status: string;
    photoUrl: string | null;
    /** Children Service / Junior Youth class, when there is one. */
    className: string | null;
    details: { label: string; value: string | null }[];
    /** The member's own numbers. */
    contacts: { label: string; phone: string }[];
    guardians: {
        id: number;
        name: string;
        relationship: string;
        isPrimary: boolean;
        memberNumber: string | null;
        photoUrl: string | null;
        phones: string[];
    }[];
    location: { latitude: number; longitude: number } | null;
    links: { show: string; edit: string; report: string };
    /** Adults only: where to load the full record for the section tabs. */
    relatedUrl: string | null;
};

/** A quick look at a member with one-tap Call and WhatsApp, and (for adults) a tab for each section of their record. */
export function MemberViewDialog({
    subject,
    canEdit,
    canReport,
    onClose,
}: {
    subject: ViewSubject | null;
    canEdit: boolean;
    canReport: boolean;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={subject !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                {subject && (
                    <Body
                        key={subject.number}
                        subject={subject}
                        canEdit={canEdit}
                        canReport={canReport}
                        onClose={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function Body({
    subject,
    canEdit,
    canReport,
    onClose,
}: {
    subject: ViewSubject;
    canEdit: boolean;
    canReport: boolean;
    onClose: () => void;
}) {
    const [tab, setTab] = useState<SectionTab>('basic');
    const [detail, setDetail] = useState<{
        member: MemberDetail;
        related: RelatedData;
    } | null>(null);
    const [failed, setFailed] = useState(false);

    // Adults have a tab per section: load their full record once, when the modal opens.
    useEffect(() => {
        if (!subject.relatedUrl) {
            return;
        }

        const controller = new AbortController();

        fetch(subject.relatedUrl, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('load');
                }

                return response.json();
            })
            .then(setDetail)
            .catch((error: Error) => {
                if (error.name !== 'AbortError') {
                    setFailed(true);
                }
            });

        return () => controller.abort();
    }, [subject.relatedUrl]);

    return (
        <>
            <DialogHeader>
                <div className="flex items-center gap-4">
                    <PersonAvatar
                        name={subject.name}
                        photoUrl={subject.photoUrl}
                        className="size-16 text-base"
                    />
                    <div className="space-y-1 text-left">
                        <DialogTitle>{subject.name}</DialogTitle>
                        <DialogDescription>{subject.number}</DialogDescription>
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={subject.status} />
                            {subject.className && (
                                <ClassBadge value={subject.className} />
                            )}
                        </div>
                    </div>
                </div>
            </DialogHeader>

            {detail ? (
                <>
                    <TabBar
                        label="Member details"
                        tabs={sectionTabs(detail.related)}
                        value={tab}
                        onChange={setTab}
                    />
                    <MemberSection
                        tab={tab}
                        member={detail.member}
                        related={detail.related}
                    />
                </>
            ) : (
                <>
                    <Overview subject={subject} />
                    {subject.relatedUrl && (
                        <p className="text-center text-xs text-muted-foreground">
                            {failed
                                ? 'The rest of this record could not be loaded. Close and try again.'
                                : 'Loading the rest of the record…'}
                        </p>
                    )}
                </>
            )}

            <DialogFooter className="gap-2 sm:justify-between">
                <div className="flex flex-wrap gap-2">
                    <Button variant="ghost" asChild>
                        <Link href={subject.links.show}>Open Full Profile</Link>
                    </Button>
                    {canReport && (
                        <Button variant="outline" asChild>
                            <a
                                href={subject.links.report}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <FileText /> Generate Report
                            </a>
                        </Button>
                    )}
                </div>
                <div className="flex gap-2">
                    {canEdit && (
                        <Button variant="outline" asChild>
                            <Link href={subject.links.edit}>
                                <Pencil /> Edit
                            </Link>
                        </Button>
                    )}
                    <Button type="button" onClick={onClose}>
                        Close
                    </Button>
                </div>
            </DialogFooter>
        </>
    );
}

/** The quick view: contact with Call and WhatsApp, details, and (for children) guardians. */
function Overview({ subject }: { subject: ViewSubject }) {
    return (
        <>
            <section className="space-y-3">
                <h3 className="text-sm font-medium">Contact</h3>
                {subject.contacts.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No phone number on file
                        {subject.guardians.length > 0
                            ? '. Use a guardian below.'
                            : '.'}
                    </p>
                )}
                {subject.contacts.map((contact) => (
                    <div
                        key={`${contact.label}-${contact.phone}`}
                        className="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div className="text-sm">
                            <div className="font-medium tabular-nums">
                                {contact.phone}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {contact.label}
                            </div>
                        </div>
                        <ReachButtons
                            phone={contact.phone}
                            name={subject.name}
                        />
                    </div>
                ))}
                {subject.location && (
                    <a
                        href={mapUrl(
                            subject.location.latitude,
                            subject.location.longitude,
                        )}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 text-sm underline-offset-4 hover:underline"
                    >
                        <MapPin className="size-3.5" /> Home Location
                        <ExternalLink className="size-3.5" />
                    </a>
                )}
            </section>

            <section className="space-y-3">
                <h3 className="text-sm font-medium">Details</h3>
                <DetailList items={subject.details} />
            </section>

            {subject.guardians.length > 0 && (
                <section className="space-y-3">
                    <h3 className="text-sm font-medium">Guardians</h3>
                    <div className="space-y-3">
                        {subject.guardians.map((guardian) => (
                            <div
                                key={guardian.id}
                                className="space-y-2 rounded-lg border p-3"
                            >
                                <div className="flex items-center gap-3">
                                    <PersonAvatar
                                        name={guardian.name}
                                        photoUrl={guardian.photoUrl}
                                        className="size-10"
                                    />
                                    <div className="text-sm">
                                        <div className="font-medium">
                                            {guardian.name}
                                            {guardian.isPrimary && (
                                                <span className="ml-2 rounded bg-primary/10 px-1.5 py-0.5 text-xs font-normal text-primary">
                                                    primary
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {guardian.relationship}
                                            {guardian.memberNumber
                                                ? ` · member ${guardian.memberNumber}`
                                                : ''}
                                        </div>
                                    </div>
                                </div>
                                {guardian.phones.length === 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        No phone on file.
                                    </p>
                                )}
                                {guardian.phones.map((phone) => (
                                    <div
                                        key={phone}
                                        className="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <span className="text-sm tabular-nums">
                                            {phone}
                                        </span>
                                        <ReachButtons
                                            phone={phone}
                                            name={guardian.name}
                                        />
                                    </div>
                                ))}
                            </div>
                        ))}
                    </div>
                </section>
            )}
        </>
    );
}
