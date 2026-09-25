import { Head, Link, router } from '@inertiajs/react';
import { Ban, Pencil, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DetailList } from '@/components/detail-list';
import {
    EventBadges,
    eventDates,
    eventTimes,
    hostTone,
} from '@/components/events-nav';
import type { EventRow } from '@/components/events-nav';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { show as showMember } from '@/routes/members';
import { calendar, destroy, edit, index, status } from '@/routes/events';

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
};

export default function EventShow({
    event: e,
    hosts,
    scopes,
    visibilities,
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

                <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    Participants, attendance, budget, tasks, expenses and
                    documents will appear here as those parts of the system are
                    built.
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

EventShow.layout = {
    breadcrumbs: [{ title: 'Events', href: index() }],
};
