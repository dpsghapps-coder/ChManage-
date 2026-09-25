import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarX2, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { ChoiceOrOther } from '@/components/choice-or-other';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PersonField } from '@/components/person-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermission } from '@/hooks/use-permission';
import { termTiming } from '@/lib/committee-terms';
import { cn } from '@/lib/utils';
import { overview, rules } from '@/routes/committees';
import { store } from '@/routes/committees/members';
import { destroy, end, renew, update } from '@/routes/committees/terms';
import { show as showMember } from '@/routes/members';

type Term = {
    id: number;
    member_id: number | null;
    name: string;
    member_number: string | null;
    phone: string | null;
    position: string | null;
    started_on: string;
    ends_on: string | null;
    end_note: string | null;
    days_left: number | null;
    ending: boolean;
    term_number: number;
    over_limit: boolean;
    renewed: boolean;
};

type Props = {
    committee: {
        id: number;
        name: string;
        term_years: number | null;
        max_terms: number | null;
    };
    current: Term[];
    past: Term[];
    positions: string[];
    warnDays: number;
};

const day = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

const today = () => new Date().toISOString().slice(0, 10);

export default function CommitteeMembers({
    committee,
    current,
    past,
    positions,
    warnDays,
}: Props) {
    const { can } = usePermission();
    const manage = can('committees.manage');
    const [dialog, setDialog] = useState<
        { kind: 'add' } | { kind: 'edit' | 'end'; term: Term } | null
    >(null);
    const [removing, setRemoving] = useState<number | null>(null);

    return (
        <>
            <Head title={committee.name} />

            <div className="max-w-5xl space-y-6 p-4">
                <PageHeader
                    title={committee.name}
                    description={`${current.length} serving now. Terms ending within ${warnDays} days are flagged.`}
                    actions={
                        manage && (
                            <Button onClick={() => setDialog({ kind: 'add' })}>
                                <Plus /> Add Member
                            </Button>
                        )
                    }
                />

                <RulesCard committee={committee} editable={manage} />

                <section className="space-y-3">
                    <h2 className="font-medium">Serving now</h2>
                    {current.length === 0 ? (
                        <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                            No one is serving yet.{' '}
                            {manage && 'Add the first member.'}
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {current.map((t) => (
                                <li
                                    key={t.id}
                                    className="flex flex-wrap items-center justify-between gap-3 p-4"
                                >
                                    <div className="min-w-0 space-y-0.5">
                                        <p className="font-medium">
                                            {t.member_id ? (
                                                <Link
                                                    href={showMember(
                                                        t.member_id,
                                                    )}
                                                    className="hover:underline"
                                                >
                                                    {t.name}
                                                </Link>
                                            ) : (
                                                t.name
                                            )}{' '}
                                            <span className="text-sm font-normal text-muted-foreground">
                                                {t.position ?? 'Member'}
                                            </span>
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Term {t.term_number} ·{' '}
                                            {day(t.started_on)}
                                            {t.ends_on
                                                ? ` to ${day(t.ends_on)}`
                                                : ' · no fixed end'}
                                            {t.phone ? ` · ${t.phone}` : ''}
                                        </p>
                                        <p className="flex flex-wrap gap-1.5">
                                            {t.ending &&
                                                t.days_left !== null && (
                                                    <Badge className="bg-amber-500 text-white hover:bg-amber-500">
                                                        {termTiming(
                                                            t.days_left,
                                                        )}
                                                    </Badge>
                                                )}
                                            {t.over_limit && (
                                                <Badge variant="destructive">
                                                    Over the limit of{' '}
                                                    {committee.max_terms} terms
                                                </Badge>
                                            )}
                                            {t.renewed && (
                                                <Badge variant="secondary">
                                                    Renewed
                                                </Badge>
                                            )}
                                        </p>
                                    </div>
                                    {manage && (
                                        <div className="flex flex-wrap gap-1">
                                            {!t.renewed && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            renew(t.id).url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <RefreshCw /> Renew
                                                </Button>
                                            )}
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setDialog({
                                                        kind: 'end',
                                                        term: t,
                                                    })
                                                }
                                            >
                                                <CalendarX2 /> End term
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${t.name}`}
                                                onClick={() =>
                                                    setDialog({
                                                        kind: 'edit',
                                                        term: t,
                                                    })
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                            {removing === t.id ? (
                                                <span className="flex items-center gap-1 text-sm">
                                                    Remove?
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.delete(
                                                                destroy(t.id)
                                                                    .url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Yes
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setRemoving(null)
                                                        }
                                                    >
                                                        No
                                                    </Button>
                                                </span>
                                            ) : (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Remove ${t.name}`}
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        setRemoving(t.id)
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {past.length > 0 && (
                    <details className="rounded-lg border">
                        <summary className="cursor-pointer p-4 font-medium">
                            Past members ({past.length})
                        </summary>
                        <ul className="divide-y border-t">
                            {past.map((t) => (
                                <li
                                    key={t.id}
                                    className="flex flex-wrap items-center justify-between gap-2 p-4 text-sm"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {t.name}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            {t.position ?? 'Member'} · term{' '}
                                            {t.term_number}
                                        </span>
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {day(t.started_on)} to {day(t.ends_on!)}
                                        {t.end_note ? ` · ${t.end_note}` : ''}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </details>
                )}
            </div>

            {dialog?.kind === 'add' && (
                <AddDialog
                    committee={committee}
                    positions={positions}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.kind === 'edit' && (
                <EditDialog
                    term={dialog.term}
                    positions={positions}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog?.kind === 'end' && (
                <EndDialog term={dialog.term} onClose={() => setDialog(null)} />
            )}
        </>
    );
}

/** The committee's term length and the most terms one person should serve; both optional. */
function RulesCard({
    committee,
    editable,
}: {
    committee: Props['committee'];
    editable: boolean;
}) {
    const form = useForm({
        term_years: (committee.term_years ?? '') as number | '',
        max_terms: (committee.max_terms ?? '') as number | '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(rules(committee.id).url, { preserveScroll: true });
    };

    if (!editable) {
        return (
            <p className="text-sm text-muted-foreground">
                {committee.term_years || committee.max_terms
                    ? `${committee.term_years ? `${committee.term_years}-year terms` : ''}${committee.term_years && committee.max_terms ? ', ' : ''}${committee.max_terms ? `at most ${committee.max_terms} terms` : ''}.`
                    : 'No term rules set.'}
            </p>
        );
    }

    const number = (name: 'term_years' | 'max_terms') => (
        <Input
            id={name}
            type="number"
            inputMode="numeric"
            min={1}
            max={10}
            className="w-24"
            value={form.data[name]}
            onChange={(e) =>
                form.setData(
                    name,
                    e.target.value === '' ? '' : Number(e.target.value),
                )
            }
        />
    );

    return (
        <form onSubmit={submit} className="space-y-3 rounded-lg border p-5">
            <div>
                <h2 className="font-medium">Term rules</h2>
                <p className="text-xs text-muted-foreground">
                    Optional. The term length fills in the end date when someone
                    is added. Going past the maximum only shows a warning.
                </p>
            </div>
            <div className="flex flex-wrap items-end gap-4">
                <div className="grid gap-1.5">
                    <Label htmlFor="term_years">Term length (years)</Label>
                    {number('term_years')}
                    <InputError message={form.errors.term_years} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="max_terms">
                        Most terms one person should serve
                    </Label>
                    {number('max_terms')}
                    <InputError message={form.errors.max_terms} />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                >
                    Save rules
                </Button>
            </div>
        </form>
    );
}

function AddDialog({
    committee,
    positions,
    onClose,
}: {
    committee: Props['committee'];
    positions: string[];
    onClose: () => void;
}) {
    const form = useForm({
        member_id: '' as number | '',
        name: '',
        position: '',
        started_on: today(),
        ends_on: '',
    });
    const { data, setData, errors } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store(committee.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Shell
            title="Add a member"
            description={`To ${committee.name}.`}
            onClose={onClose}
        >
            <form onSubmit={submit} className="space-y-4">
                <PersonField
                    id="cm"
                    label="Member"
                    value={{ member_id: data.member_id, name: data.name }}
                    onChange={(v) =>
                        setData((d) => ({
                            ...d,
                            member_id: v.member_id,
                            name: v.name,
                        }))
                    }
                    error={errors.member_id ?? errors.name}
                />
                <div className="grid gap-2">
                    <Label htmlFor="cm-position">Position</Label>
                    <ChoiceOrOther
                        id="cm-position"
                        value={data.position}
                        options={positions}
                        onChange={(v) => setData('position', v)}
                        placeholder="The position"
                    />
                    <InputError message={errors.position} />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="cm-start">Term starts</Label>
                        <Input
                            id="cm-start"
                            type="date"
                            value={data.started_on}
                            onChange={(e) =>
                                setData('started_on', e.target.value)
                            }
                            required
                        />
                        <InputError message={errors.started_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cm-end">Term ends</Label>
                        <Input
                            id="cm-end"
                            type="date"
                            min={data.started_on}
                            value={data.ends_on}
                            onChange={(e) => setData('ends_on', e.target.value)}
                        />
                        <p className="text-xs text-muted-foreground">
                            {committee.term_years
                                ? `Leave empty for ${committee.term_years} years.`
                                : 'Leave empty for no fixed end.'}
                        </p>
                        <InputError message={errors.ends_on} />
                    </div>
                </div>
                <Footer onClose={onClose} busy={form.processing} />
            </form>
        </Shell>
    );
}

function EditDialog({
    term,
    positions,
    onClose,
}: {
    term: Term;
    positions: string[];
    onClose: () => void;
}) {
    const form = useForm({
        position: term.position ?? '',
        started_on: term.started_on,
        ends_on: term.ends_on ?? '',
    });
    const { data, setData, errors } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update(term.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Shell
            title={`Edit ${term.name}`}
            description="Change the position or the dates of this term."
            onClose={onClose}
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor="cm-position">Position</Label>
                    <ChoiceOrOther
                        id="cm-position"
                        value={data.position}
                        options={positions}
                        onChange={(v) => setData('position', v)}
                        placeholder="The position"
                    />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="cm-start">Term starts</Label>
                        <Input
                            id="cm-start"
                            type="date"
                            value={data.started_on}
                            onChange={(e) =>
                                setData('started_on', e.target.value)
                            }
                            required
                        />
                        <InputError message={errors.started_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cm-end">Term ends</Label>
                        <Input
                            id="cm-end"
                            type="date"
                            min={data.started_on}
                            value={data.ends_on}
                            onChange={(e) => setData('ends_on', e.target.value)}
                        />
                        <InputError message={errors.ends_on} />
                    </div>
                </div>
                <Footer onClose={onClose} busy={form.processing} />
            </form>
        </Shell>
    );
}

function EndDialog({ term, onClose }: { term: Term; onClose: () => void }) {
    const form = useForm({ ends_on: today(), end_note: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(end(term.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Shell
            title={`End ${term.name}'s term`}
            description="For someone who resigns or steps down before the term is over. They move to past members once the day has passed."
            onClose={onClose}
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor="end-date">Ends on</Label>
                    <Input
                        id="end-date"
                        type="date"
                        min={term.started_on}
                        value={form.data.ends_on}
                        onChange={(e) =>
                            form.setData('ends_on', e.target.value)
                        }
                        required
                        className="w-auto"
                    />
                    <InputError message={form.errors.ends_on} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="end-note">Reason</Label>
                    <Input
                        id="end-note"
                        value={form.data.end_note}
                        onChange={(e) =>
                            form.setData('end_note', e.target.value)
                        }
                        maxLength={250}
                        placeholder="For example: moved away"
                    />
                </div>
                <Footer
                    onClose={onClose}
                    busy={form.processing}
                    label="End term"
                />
            </form>
        </Shell>
    );
}

function Shell({
    title,
    description,
    onClose,
    children,
}: {
    title: string;
    description: string;
    onClose: () => void;
    children: ReactNode;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className={cn('max-h-[90vh] overflow-y-auto')}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {children}
            </DialogContent>
        </Dialog>
    );
}

function Footer({
    onClose,
    busy,
    label = 'Save',
}: {
    onClose: () => void;
    busy: boolean;
    label?: string;
}) {
    return (
        <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
                Cancel
            </Button>
            <Button type="submit" disabled={busy}>
                {label}
            </Button>
        </DialogFooter>
    );
}

CommitteeMembers.layout = {
    breadcrumbs: [{ title: 'Committees', href: overview() }],
};
