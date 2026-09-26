import { Badge } from '@/components/ui/badge';

export const REQUEST_STATUS: Record<string, string> = {
    submitted: 'Submitted',
    in_review: 'In review',
    approved: 'Approved',
    declined: 'Declined',
    cancelled: 'Withdrawn',
};

/** A member request's status as a badge: new ones stand out, decided ones are green or red. */
export function RequestStatus({ status }: { status: string }) {
    return (
        <Badge
            variant={status === 'submitted' ? 'default' : 'outline'}
            className={
                status === 'approved'
                    ? 'border-emerald-600 text-emerald-700 dark:text-emerald-400'
                    : status === 'declined'
                      ? 'border-red-500 text-red-600'
                      : undefined
            }
        >
            {REQUEST_STATUS[status] ?? status}
        </Badge>
    );
}
