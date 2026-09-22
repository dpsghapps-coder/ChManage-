import { Badge } from '@/components/ui/badge';

export const statusLabel = (status: string) =>
    status.replace('_', ' ').replace(/^./, (c) => c.toUpperCase());

export function StatusBadge({ status }: { status: string }) {
    if (status === 'active') {
        return (
            <Badge className="bg-emerald-600 text-white hover:bg-emerald-600">
                Active
            </Badge>
        );
    }

    return (
        <Badge variant={status === 'terminated' ? 'destructive' : 'secondary'}>
            {statusLabel(status)}
        </Badge>
    );
}
