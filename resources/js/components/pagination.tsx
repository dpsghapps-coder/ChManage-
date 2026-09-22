import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type Paginated<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

export function Pagination({ page }: { page: Paginated<unknown> }) {
    if (page.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col items-center justify-between gap-3 pt-4 text-sm text-muted-foreground sm:flex-row">
            <p>
                Showing {page.from}–{page.to} of {page.total}
            </p>

            {page.links.length > 3 && (
                <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                    {page.links.map((link, index) => {
                        // Laravel labels are HTML ("&laquo; Previous"); strip entities for display.
                        const label = link.label
                            .replace('&laquo;', '‹')
                            .replace('&raquo;', '›')
                            .replace(/&[a-z]+;/g, '');
                        const classes = cn(
                            'min-w-9 rounded-md border px-3 py-1.5 text-center',
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:bg-accent',
                            !link.url && 'pointer-events-none opacity-40',
                        );

                        return link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={classes}
                            >
                                {label}
                            </Link>
                        ) : (
                            <span key={index} className={classes}>
                                {label}
                            </span>
                        );
                    })}
                </nav>
            )}
        </div>
    );
}
