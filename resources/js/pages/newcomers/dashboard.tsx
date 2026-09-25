import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { NewcomersNav } from '@/components/newcomers-nav';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes/newcomers';

type Row = { label: string; count: number };

type Props = {
    totals: {
        registered: number;
        working: number;
        members: number;
        inactive: number;
    };
    months: { month: string; label: string; year: string; count: number }[];
    sources: Row[];
    funnel: { stage: string; label: string; count: number; percent: number }[];
    time_to_member: { count: number; average_days: number | null };
    lessons: { title: string; completed: number; total: number }[];
    class: { catechumens: number; average_percent: number | null };
    counsellors: {
        name: string;
        is_active: boolean;
        newcomer: number;
        catechumen: number;
        total: number;
    }[];
    inactive_reasons: Row[];
};

export default function NewcomersDashboard(props: Props) {
    const {
        totals,
        months,
        sources,
        funnel,
        time_to_member,
        lessons,
        counsellors,
        inactive_reasons,
    } = props;
    const peak = Math.max(1, ...months.map((m) => m.count));
    const sourceMax = Math.max(1, ...sources.map((s) => s.count));

    return (
        <>
            <Head title="Newcomers Dashboard" />

            <div className="max-w-6xl space-y-6 p-4">
                <PageHeader
                    title="Newcomers Dashboard"
                    description="How visitors find us and how far they go."
                />
                <NewcomersNav />

                <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Tile label="Registered in all" value={totals.registered} />
                    <Tile
                        label="Still coming"
                        value={totals.working}
                        note="Visitors, newcomers and catechumens"
                    />
                    <Tile label="Became members" value={totals.members} />
                    <Tile label="Stopped coming" value={totals.inactive} />
                </dl>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card
                        title="Registrations by month"
                        note="Visitors registered in each of the last twelve months."
                    >
                        <div
                            className="flex h-44 items-end gap-1.5"
                            role="img"
                            aria-label="Registrations by month"
                        >
                            {months.map((m) => (
                                <div
                                    key={m.month}
                                    className="flex h-full min-w-0 flex-1 flex-col justify-end gap-1 text-center"
                                >
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {m.count || ''}
                                    </span>
                                    <div
                                        className="w-full rounded-t bg-primary"
                                        style={{
                                            height: `${(m.count / peak) * 100}%`,
                                            minHeight: m.count ? 4 : 0,
                                        }}
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="flex gap-1.5 border-t pt-1 text-center text-[11px] text-muted-foreground">
                            {months.map((m) => (
                                <span key={m.month} className="min-w-0 flex-1">
                                    {m.label}
                                    {m.label === 'Jan' && (
                                        <span className="block">{m.year}</span>
                                    )}
                                </span>
                            ))}
                        </div>
                    </Card>

                    <Card
                        title="How far people get"
                        note="Of everyone registered, how many reached each stage."
                    >
                        <ul className="space-y-3">
                            {funnel.map((f) => (
                                <BarRow
                                    key={f.stage}
                                    label={f.label}
                                    value={f.count}
                                    max={Math.max(1, funnel[0].count)}
                                    suffix={`${f.percent}%`}
                                />
                            ))}
                        </ul>
                        <p className="text-sm text-muted-foreground">
                            {time_to_member.average_days === null
                                ? 'No one has been made a member yet, so there is no average time to membership.'
                                : `On average ${time_to_member.average_days} days from first visit to membership (${time_to_member.count} ${time_to_member.count === 1 ? 'person' : 'people'}).`}
                        </p>
                    </Card>

                    <Card title="How they heard of us">
                        {sources.length === 0 ? (
                            <Empty>No one registered yet.</Empty>
                        ) : (
                            <ul className="space-y-3">
                                {sources.map((s) => (
                                    <BarRow
                                        key={s.label}
                                        label={s.label}
                                        value={s.count}
                                        max={sourceMax}
                                    />
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card
                        title="Class progress"
                        note={
                            props.class.average_percent === null
                                ? 'No one is in the class yet.'
                                : `${props.class.catechumens} in the class, ${props.class.average_percent}% of lessons done on average.`
                        }
                    >
                        {lessons.length > 0 && (
                            <ol className="space-y-3">
                                {lessons.map((l) => (
                                    <BarRow
                                        key={l.title}
                                        label={l.title}
                                        value={l.completed}
                                        max={Math.max(1, l.total)}
                                        suffix={`${l.completed}/${l.total}`}
                                        tone="done"
                                    />
                                ))}
                            </ol>
                        )}
                    </Card>

                    <Card
                        title="Counsellor workload"
                        note="People each counsellor is following now."
                    >
                        {counsellors.length === 0 ? (
                            <Empty>No counsellors yet.</Empty>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="pb-2 font-medium">
                                            Counsellor
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Newcomers
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            In class
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {counsellors.map((c) => (
                                        <tr key={c.name}>
                                            <td className="py-2">
                                                {c.name}{' '}
                                                {!c.is_active && (
                                                    <Badge variant="secondary">
                                                        Inactive
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {c.newcomer}
                                            </td>
                                            <td className="py-2 text-right tabular-nums">
                                                {c.catechumen}
                                            </td>
                                            <td className="py-2 text-right font-medium tabular-nums">
                                                {c.total}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Card>

                    <Card title="Why people stopped coming">
                        {inactive_reasons.length === 0 ? (
                            <Empty>No one has gone inactive.</Empty>
                        ) : (
                            <ul className="space-y-3">
                                {inactive_reasons.map((r) => (
                                    <BarRow
                                        key={r.label}
                                        label={r.label}
                                        value={r.count}
                                        max={Math.max(
                                            1,
                                            ...inactive_reasons.map(
                                                (x) => x.count,
                                            ),
                                        )}
                                    />
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </>
    );
}

function Tile({
    label,
    value,
    note,
}: {
    label: string;
    value: number;
    note?: string;
}) {
    return (
        <div className="rounded-lg border p-4">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-3xl font-semibold tabular-nums">{value}</dd>
            {note && <p className="text-xs text-muted-foreground">{note}</p>}
        </div>
    );
}

function Card({
    title,
    note,
    children,
}: {
    title: string;
    note?: string;
    children?: ReactNode;
}) {
    return (
        <section className="space-y-3 rounded-lg border p-5">
            <div>
                <h2 className="font-medium">{title}</h2>
                {note && (
                    <p className="text-xs text-muted-foreground">{note}</p>
                )}
            </div>
            {children}
        </section>
    );
}

function Empty({ children }: { children: ReactNode }) {
    return (
        <p className="py-4 text-center text-sm text-muted-foreground">
            {children}
        </p>
    );
}

/** A label, a bar as wide as value is of max, and the figure on the right. */
function BarRow({
    label,
    value,
    max,
    suffix,
    tone,
}: {
    label: string;
    value: number;
    max: number;
    suffix?: string;
    tone?: 'done';
}) {
    return (
        <li className="space-y-1">
            <div className="flex justify-between gap-3 text-sm">
                <span className="min-w-0 truncate">{label}</span>
                <span className="shrink-0 text-muted-foreground tabular-nums">
                    {suffix ? suffix : value}
                    {suffix && !suffix.includes('/') ? ` · ${value}` : ''}
                </span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    className={`h-full rounded-full ${tone === 'done' ? 'bg-emerald-600' : 'bg-primary'}`}
                    style={{ width: `${Math.min(100, (value / max) * 100)}%` }}
                />
            </div>
        </li>
    );
}

NewcomersDashboard.layout = {
    breadcrumbs: [{ title: 'Newcomers dashboard', href: dashboard() }],
};
