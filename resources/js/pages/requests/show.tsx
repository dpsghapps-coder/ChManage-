import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { RequestStatus } from '@/components/request-status';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { show as showMember } from '@/routes/members';
import { decide, index, review } from '@/routes/requests';

type Request = {
    id: number;
    reference: string;
    type: string;
    type_label: string;
    status: string;
    member: {
        id: number;
        member_number: string;
        full_name: string;
        mobile: string | null;
    };
    submitted_at: string;
    answers: { label: string; value: string }[];
    changes: {
        field: string;
        label: string;
        from: string | null;
        to: string | null;
        now: string | null;
    }[];
    member_note: string | null;
    response: string | null;
    handled_by: string | null;
    handled_at: string | null;
    member_email: string | null;
};

export default function RequestShow({ request: r }: { request: Request }) {
    const open = r.status === 'submitted' || r.status === 'in_review';
    const [decision, setDecision] = useState<'approved' | 'declined' | null>(
        null,
    );
    const form = useForm({ decision: '', response: '' });
    const isChange = r.type === 'change_details';

    const send = (value: 'approved' | 'declined') => {
        form.transform((data) => ({ ...data, decision: value }));
        form.post(decide(r.id).url, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`${r.reference} · ${r.type_label}`} />

            <div className="max-w-3xl space-y-6 p-4">
                <PageHeader
                    title={r.type_label}
                    description={`${r.reference} · sent ${r.submitted_at.slice(0, 16).replace('T', ' ')}`}
                    actions={<RequestStatus status={r.status} />}
                />

                <section className="space-y-1 rounded-lg border p-4 text-sm">
                    <p className="text-xs text-muted-foreground uppercase">
                        From
                    </p>
                    <p className="font-medium">
                        <Link
                            href={showMember(r.member.id)}
                            className="underline underline-offset-4"
                        >
                            {r.member.full_name}
                        </Link>{' '}
                        <span className="font-normal text-muted-foreground">
                            ({r.member.member_number})
                        </span>
                    </p>
                    <p className="text-muted-foreground">
                        {[r.member.mobile, r.member_email]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </section>

                {isChange ? (
                    <section className="space-y-3 rounded-lg border p-4">
                        <h2 className="font-medium">Changes asked for</h2>
                        {r.changes.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No fields were changed on the form. See the note
                                below.
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {r.changes.map((c) => (
                                    <li
                                        key={c.field}
                                        className="grid gap-1 py-2 sm:grid-cols-3"
                                    >
                                        <span className="font-medium">
                                            {c.label}
                                        </span>
                                        <span className="text-muted-foreground">
                                            Was: {c.from ?? 'nothing'}
                                        </span>
                                        <span>
                                            Now:{' '}
                                            <strong>{c.to ?? 'nothing'}</strong>
                                            {open && c.now !== c.from && (
                                                <span className="block text-xs text-amber-600">
                                                    The record now says “
                                                    {c.now ?? 'nothing'}”,
                                                    changed since the request.
                                                </span>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {r.member_note && (
                            <div className="text-sm">
                                <p className="text-xs text-muted-foreground uppercase">
                                    Their note
                                </p>
                                <p className="whitespace-pre-line">
                                    {r.member_note}
                                </p>
                            </div>
                        )}
                        {open && (
                            <p className="text-xs text-muted-foreground">
                                Approving writes these changes to the
                                member&apos;s record. Names and date of birth
                                are not on the form: a note about them has to be
                                changed by hand.
                            </p>
                        )}
                    </section>
                ) : (
                    <section className="rounded-lg border p-4">
                        <h2 className="mb-2 font-medium">Details</h2>
                        <dl className="divide-y text-sm">
                            {r.answers.map((a) => (
                                <div
                                    key={a.label}
                                    className="grid gap-1 py-2 sm:grid-cols-3"
                                >
                                    <dt className="text-muted-foreground">
                                        {a.label}
                                    </dt>
                                    <dd className="whitespace-pre-line sm:col-span-2">
                                        {a.value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                )}

                {!open && (
                    <section className="space-y-1 rounded-lg border p-4 text-sm">
                        <p className="text-xs text-muted-foreground uppercase">
                            Reply to the member
                        </p>
                        <p className="whitespace-pre-line">
                            {r.response || 'No message.'}
                        </p>
                        {r.handled_by && (
                            <p className="text-xs text-muted-foreground">
                                {r.handled_by}
                                {r.handled_at
                                    ? `, ${r.handled_at.slice(0, 16).replace('T', ' ')}`
                                    : ''}
                            </p>
                        )}
                    </section>
                )}

                {open && (
                    <section className="space-y-3 rounded-lg border p-4">
                        <h2 className="font-medium">Your decision</h2>
                        {r.status === 'submitted' && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        review(r.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                I&apos;m looking at this
                            </Button>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="response">
                                Message to the member{' '}
                                {decision === 'declined'
                                    ? '(required)'
                                    : '(optional)'}
                            </Label>
                            <Textarea
                                id="response"
                                rows={3}
                                value={form.data.response}
                                onChange={(e) =>
                                    form.setData('response', e.target.value)
                                }
                                maxLength={2000}
                            />
                            <InputError message={form.errors.response} />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                disabled={form.processing}
                                onClick={() => {
                                    setDecision('approved');
                                    send('approved');
                                }}
                            >
                                <Check /> Approve
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => {
                                    setDecision('declined');
                                    send('declined');
                                }}
                            >
                                <X /> Decline
                            </Button>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

RequestShow.layout = {
    breadcrumbs: [{ title: 'Member requests', href: index() }],
};
