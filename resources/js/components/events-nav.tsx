import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { calendar, index } from '@/routes/events';
import { index as venues } from '@/routes/events/venues';

export type EventRow = {
    id: number;
    title: string;
    host_type: 'church' | 'group' | 'committee';
    host: string;
    scope: 'internal' | 'external';
    visibility: 'public' | 'private';
    status: 'scheduled' | 'cancelled';
    starts_on: string;
    ends_on: string;
    is_all_day: boolean;
    starts_at: string | null;
    ends_at: string | null;
    venue: string | null;
    organizer: string | null;
};

/** The tabs across the top of the Events section. Venues are a setting, so they need `settings.manage`. */
export function EventsNav() {
    const { can } = usePermission();
    const { isCurrentUrl } = useCurrentUrl();

    const tabs = [
        { title: 'Calendar', href: calendar(), show: can('events.view') },
        { title: 'Events', href: index(), show: can('events.view') },
        { title: 'Venues', href: venues(), show: can('settings.manage') },
    ].filter((tab) => tab.show);

    return (
        <nav
            aria-label="Events"
            className="flex gap-1 overflow-x-auto border-b"
        >
            {tabs.map((tab) => (
                <Link
                    key={tab.title}
                    href={tab.href}
                    prefetch
                    aria-current={isCurrentUrl(tab.href) ? 'page' : undefined}
                    className={cn(
                        '-mb-px border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                        isCurrentUrl(tab.href)
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.title}
                </Link>
            ))}
        </nav>
    );
}

/** One colour per kind of host, so the calendar reads at a glance. */
export const hostTone: Record<EventRow['host_type'], string> = {
    church: 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950 dark:text-indigo-200',
    group: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    committee:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
};

export const hostLabel: Record<EventRow['host_type'], string> = {
    church: 'Church',
    group: 'Group',
    committee: 'Committee',
};

export function EventBadges({ event }: { event: EventRow }) {
    return (
        <span className="flex flex-wrap gap-1">
            {event.status === 'cancelled' && (
                <Badge variant="destructive">Cancelled</Badge>
            )}
            {event.scope === 'external' && (
                <Badge variant="outline">External</Badge>
            )}
            {event.visibility === 'private' && (
                <Badge variant="secondary">Private</Badge>
            )}
        </span>
    );
}

const day = (iso: string, options: Intl.DateTimeFormatOptions) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', options);

/** "Sun, 5 Oct 2026" for one day, "5 Oct – 7 Oct 2026" for several. */
export function eventDates(e: Pick<EventRow, 'starts_on' | 'ends_on'>) {
    if (e.starts_on === e.ends_on) {
        return day(e.starts_on, {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    }

    return `${day(e.starts_on, { day: 'numeric', month: 'short' })} – ${day(e.ends_on, { day: 'numeric', month: 'short', year: 'numeric' })}`;
}

/** "All day", "08:00–10:00", "From 08:00" or nothing when no time was set. */
export function eventTimes(
    e: Pick<EventRow, 'is_all_day' | 'starts_at' | 'ends_at'>,
) {
    if (e.is_all_day) {
        return 'All day';
    }

    if (e.starts_at && e.ends_at) {
        return `${e.starts_at}–${e.ends_at}`;
    }

    return e.starts_at ? `From ${e.starts_at}` : null;
}
