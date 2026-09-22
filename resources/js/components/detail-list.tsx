import type { ReactNode } from 'react';

/** Label / value pairs for a profile page. Empty values show a dash. */
export function DetailList({
    items,
}: {
    items: { label: string; value: ReactNode }[];
}) {
    return (
        <dl className="grid gap-4 sm:grid-cols-2">
            {items.map(({ label, value }) => (
                <div key={label}>
                    <dt className="text-xs text-muted-foreground">{label}</dt>
                    <dd className="mt-0.5 text-sm">
                        {value === null ||
                        value === undefined ||
                        value === '' ? (
                            <span className="text-muted-foreground">—</span>
                        ) : (
                            value
                        )}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
