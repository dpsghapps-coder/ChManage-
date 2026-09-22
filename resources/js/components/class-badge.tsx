import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/** One color per class. Full class names are written out so Tailwind can see them. */
const colors: Record<string, string> = {
    'CS Class 1':
        'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
    'CS Class 2':
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    'CS Class 3':
        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    'JY Junior Class':
        'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
    'JY Intermediate Class':
        'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-900/40 dark:text-fuchsia-200',
    'JY Senior Class':
        'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
};

/** "JY Senior Class (Ages 16–17)" shows as a colored "JY Senior Class" badge with the ages underneath, centred on it. */
export function ClassBadge({ value }: { value: string }) {
    const [name, ages] = value.split(' (');

    return (
        <div className="inline-flex flex-col items-center gap-0.5 whitespace-nowrap">
            <Badge
                variant="outline"
                className={cn(
                    'border-transparent whitespace-nowrap',
                    colors[name] ?? 'bg-muted text-muted-foreground',
                )}
            >
                {name}
            </Badge>
            {ages && (
                <span className="text-xs leading-none text-muted-foreground tabular-nums">
                    {ages.replace(')', '')}
                </span>
            )}
        </div>
    );
}
