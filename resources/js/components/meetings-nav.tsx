import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { index as actions } from '@/routes/actions';
import { index as decisions } from '@/routes/decisions';
import { index } from '@/routes/meetings';

export type ActionRow = {
    id: number;
    decision_id: number;
    description: string;
    responsible: string | null;
    responsible_member_id: number | null;
    deadline: string | null;
    status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
    shown: 'pending' | 'in_progress' | 'completed' | 'cancelled' | 'overdue';
    days_overdue: number | null;
    completed_on: string | null;
    note: string | null;
};

/** The tabs across the top of the Meetings section. */
export function MeetingsNav() {
    const { can } = usePermission();
    const { isCurrentUrl } = useCurrentUrl();

    const tabs = [
        { title: 'Meetings', href: index() },
        { title: 'Decisions', href: decisions() },
        { title: 'Actions', href: actions() },
    ].filter(() => can('meetings.view'));

    return (
        <nav
            aria-label="Meetings"
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

export const actionLabel: Record<ActionRow['shown'], string> = {
    pending: 'Pending',
    in_progress: 'In progress',
    completed: 'Completed',
    overdue: 'Overdue',
    cancelled: 'Cancelled',
};

const actionStyle: Record<ActionRow['shown'], string> = {
    pending: 'bg-slate-500 text-white hover:bg-slate-500',
    in_progress: 'bg-sky-600 text-white hover:bg-sky-600',
    completed: 'bg-emerald-600 text-white hover:bg-emerald-600',
    overdue: 'bg-red-600 text-white hover:bg-red-600',
    cancelled: 'bg-muted text-muted-foreground hover:bg-muted line-through',
};

export function ActionBadge({ status }: { status: ActionRow['shown'] }) {
    return <Badge className={actionStyle[status]}>{actionLabel[status]}</Badge>;
}

export function MeetingStatusBadge({ status }: { status: string }) {
    if (status === 'scheduled') {
        return <Badge variant="outline">Scheduled</Badge>;
    }

    return status === 'held' ? (
        <Badge className="bg-emerald-600 text-white hover:bg-emerald-600">
            Held
        </Badge>
    ) : (
        <Badge variant="destructive">Cancelled</Badge>
    );
}

export const shortDate = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

/** "3 days overdue", "due 12 Oct 2026" or "no deadline". */
export function deadlineText(
    a: Pick<ActionRow, 'deadline' | 'days_overdue' | 'status'>,
) {
    if (!a.deadline) {
        return 'No deadline';
    }

    if (a.days_overdue) {
        return `${a.days_overdue} day${a.days_overdue === 1 ? '' : 's'} overdue`;
    }

    return `${a.status === 'completed' ? 'Was due' : 'Due'} ${shortDate(a.deadline)}`;
}
