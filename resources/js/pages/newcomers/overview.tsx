import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { NewcomersNav, StageBadge } from '@/components/newcomers-nav';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { create, index, overview, show } from '@/routes/newcomers';

type Person = {
    id: number;
    name: string;
    stage: string;
    first_visit_on: string;
    counsellor: string | null;
    photo_url: string | null;
    last_activity?: string;
    days_quiet?: number;
};

type Panel = { total: number; people: Person[] };

type Props = {
    stages: Record<string, string>;
    counts: Record<string, number>;
    on_hold: number;
    inactive: number;
    follow_up: Panel & { days: number };
    ready: Panel;
    waiting: Panel;
};

const caption: Record<string, string> = {
    visitor: 'Came to worship and filled in the form',
    newcomer: 'Has a counsellor and is being followed up',
    catechumen: 'In the class, taking the lessons',
    member: 'Made a member and moved to the register',
};

const formatDate = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

export default function NewcomersOverview({
    stages,
    counts,
    on_hold,
    inactive,
    follow_up,
    ready,
    waiting,
}: Props) {
    const { can } = usePermission();
    const keys = Object.keys(stages);

    return (
        <>
            <Head title="Newcomers Overview" />

            <div className="max-w-6xl space-y-6 p-4">
                <PageHeader
                    title="Visitors & Newcomers"
                    description="The journey from first visit to membership, and who needs attention today."
                    actions={
                        can('newcomers.manage') && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> Register Visitor
                                </Link>
                            </Button>
                        )
                    }
                />

                <NewcomersNav />

                <section aria-label="The journey">
                    <ol className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {keys.map((key, i) => (
                            <li key={key} className="relative">
                                <Link
                                    href={index({ query: { stage: key } })}
                                    className="block h-full space-y-2 rounded-lg border p-4 transition-colors hover:border-primary/60"
                                >
                                    <div className="flex items-center justify-between">
                                        <StageBadge stage={key} />
                                        <span className="text-3xl font-semibold tabular-nums">
                                            {counts[key] ?? 0}
                                        </span>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {caption[key]}
                                    </p>
                                </Link>
                                {i < keys.length - 1 && (
                                    <ArrowRight
                                        aria-hidden
                                        className="absolute top-1/2 -right-[1.35rem] z-10 hidden size-5 -translate-y-1/2 text-muted-foreground lg:block"
                                    />
                                )}
                            </li>
                        ))}
                    </ol>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Not counted above:{' '}
                        <Link
                            href={index({ query: { status: 'on_hold' } })}
                            className="underline"
                        >
                            {on_hold} on hold
                        </Link>
                        {' · '}
                        <Link
                            href={index({ query: { status: 'inactive' } })}
                            className="underline"
                        >
                            {inactive} inactive
                        </Link>
                        .
                    </p>
                </section>

                <div className="grid gap-4 lg:grid-cols-3">
                    <AttentionPanel
                        title="Needs follow-up"
                        note={`No visit or lesson for ${follow_up.days} days or more.`}
                        panel={follow_up}
                        empty="No one has gone quiet. Everyone has been seen recently."
                        detail={(p) =>
                            `Quiet ${p.days_quiet} days · last seen ${formatDate(p.last_activity!)}`
                        }
                    />
                    <AttentionPanel
                        title="Waiting for a counsellor"
                        note="Visitors not yet given a counsellor, oldest first."
                        panel={waiting}
                        empty="Every visitor has a counsellor."
                        detail={(p) =>
                            `First visit ${formatDate(p.first_visit_on)}`
                        }
                        href={index({
                            query: { stage: 'visitor', counsellor: 'none' },
                        })}
                    />
                    <AttentionPanel
                        title="Ready to become members"
                        note="Catechumens who have finished every lesson."
                        panel={ready}
                        empty="No one has finished the class yet."
                        detail={(p) =>
                            p.counsellor
                                ? `Counsellor: ${p.counsellor}`
                                : 'No counsellor'
                        }
                        href={index({ query: { stage: 'catechumen' } })}
                    />
                </div>
            </div>
        </>
    );
}

function AttentionPanel({
    title,
    note,
    panel,
    empty,
    detail,
    href,
}: {
    title: string;
    note: string;
    panel: Panel;
    empty: string;
    detail: (person: Person) => ReactNode;
    href?: ReturnType<typeof index>;
}) {
    return (
        <section className="space-y-3 rounded-lg border p-4">
            <div>
                <h2 className="flex items-center justify-between font-medium">
                    {title}
                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs tabular-nums">
                        {panel.total}
                    </span>
                </h2>
                <p className="text-xs text-muted-foreground">{note}</p>
            </div>

            {panel.people.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">
                    {empty}
                </p>
            ) : (
                <ul className="divide-y">
                    {panel.people.map((p) => (
                        <li key={p.id}>
                            <Link
                                href={show(p.id)}
                                className="flex items-center gap-3 py-2 hover:bg-accent/50"
                            >
                                <PersonAvatar
                                    name={p.name}
                                    photoUrl={p.photo_url}
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-medium">
                                        {p.name}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {detail(p)}
                                    </span>
                                </span>
                                <StageBadge stage={p.stage} />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {href && panel.total > panel.people.length && (
                <Link href={href} className="block text-sm underline">
                    See all {panel.total}
                </Link>
            )}
        </section>
    );
}

NewcomersOverview.layout = {
    breadcrumbs: [{ title: 'Newcomers', href: overview() }],
};
