import { Head, Link, router } from '@inertiajs/react';
import { Pencil, ShieldAlert, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { day } from '@/lib/day';
import { show as showMember } from '@/routes/members';
import { destroy, edit, index } from '@/routes/speaking';
import { show as showService } from '@/routes/communion';
import type { Note } from './index';

export default function SpeakingShow({
    note,
    outcomes,
}: {
    note: Note & { notes: string };
    outcomes: Record<string, string>;
}) {
    const { can } = usePermission();
    const [confirming, setConfirming] = useState(false);

    return (
        <>
            <Head title={`Speaking: ${note.member.full_name}`} />

            <div className="max-w-3xl space-y-6 p-4">
                <PageHeader
                    title={note.member.full_name}
                    description={`${day(note.spoken_on)}${note.spoken_by ? ` · spoken to by ${note.spoken_by}` : ''}`}
                    actions={
                        <>
                            <Badge
                                variant={
                                    note.outcome === 'cleared'
                                        ? 'outline'
                                        : 'default'
                                }
                            >
                                {outcomes[note.outcome] ?? note.outcome}
                            </Badge>
                            {can('speaking.manage') &&
                                (confirming ? (
                                    <span className="flex items-center gap-2 text-sm">
                                        Delete this note?
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() =>
                                                router.delete(
                                                    destroy(note.id).url,
                                                )
                                            }
                                        >
                                            Yes
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setConfirming(false)}
                                        >
                                            No
                                        </Button>
                                    </span>
                                ) : (
                                    <>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link href={edit(note.id)}>
                                                <Pencil /> Edit
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setConfirming(true)}
                                        >
                                            <Trash2 /> Delete
                                        </Button>
                                    </>
                                ))}
                        </>
                    }
                />

                <p className="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3 text-sm">
                    <ShieldAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />
                    Confidential. Opening this note was recorded in the audit
                    log.
                </p>

                <section className="rounded-lg border p-4 text-sm">
                    <dl className="mb-4 grid gap-2 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Member
                            </dt>
                            <dd>
                                {can('members.view') ? (
                                    <Link
                                        href={showMember(note.member.id)}
                                        className="underline underline-offset-4"
                                    >
                                        {note.member.full_name}
                                    </Link>
                                ) : (
                                    note.member.full_name
                                )}{' '}
                                <span className="text-muted-foreground">
                                    {note.member.member_number}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Communion service
                            </dt>
                            <dd>
                                {note.service ? (
                                    can('communion.view') ? (
                                        <Link
                                            href={showService(note.service.id)}
                                            className="underline underline-offset-4"
                                        >
                                            {note.service.title}
                                        </Link>
                                    ) : (
                                        note.service.title
                                    )
                                ) : (
                                    'None'
                                )}
                            </dd>
                        </div>
                    </dl>
                    <p className="text-xs text-muted-foreground">Notes</p>
                    <p className="whitespace-pre-line">{note.notes}</p>
                </section>
            </div>
        </>
    );
}

SpeakingShow.layout = {
    breadcrumbs: [{ title: 'Speaking', href: index() }],
};
