import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { index as counsellors } from '@/routes/newcomers/counsellors';
import { index as lessons } from '@/routes/newcomers/lessons';
import { index as lists } from '@/routes/newcomers/lists';
import { dashboard, index, overview } from '@/routes/newcomers';

/** The tabs across the top of the Newcomers section. Counsellors, lessons and lists are settings, so they need `settings.manage`. */
export function NewcomersNav() {
    const { can } = usePermission();
    const { isCurrentUrl } = useCurrentUrl();

    const tabs = [
        { title: 'Overview', href: overview(), show: can('newcomers.view') },
        { title: 'Dashboard', href: dashboard(), show: can('newcomers.view') },
        { title: 'People', href: index(), show: can('newcomers.view') },
        {
            title: 'Counsellors',
            href: counsellors(),
            show: can('settings.manage'),
        },
        { title: 'Lessons', href: lessons(), show: can('settings.manage') },
        { title: 'Form lists', href: lists(), show: can('settings.manage') },
    ].filter((tab) => tab.show);

    return (
        <nav
            aria-label="Newcomers"
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

const stageStyle: Record<string, string> = {
    visitor: 'bg-slate-500 text-white hover:bg-slate-500',
    newcomer: 'bg-sky-600 text-white hover:bg-sky-600',
    catechumen: 'bg-amber-600 text-white hover:bg-amber-600',
    member: 'bg-emerald-600 text-white hover:bg-emerald-600',
};

export const stageLabel = (stage: string) =>
    stage.replace(/^./, (c) => c.toUpperCase());

export function StageBadge({ stage }: { stage: string }) {
    return <Badge className={stageStyle[stage]}>{stageLabel(stage)}</Badge>;
}

/** Only shown when they are not active, so the common case stays quiet. */
export function PersonStatusBadge({ status }: { status: string }) {
    if (status === 'active') {
        return null;
    }

    return (
        <Badge variant={status === 'inactive' ? 'destructive' : 'secondary'}>
            {status === 'on_hold' ? 'On hold' : 'Inactive'}
        </Badge>
    );
}
