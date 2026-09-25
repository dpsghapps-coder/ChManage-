import { Head, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { MemberPicker } from '@/components/member-picker';
import type { MemberHit } from '@/components/member-picker';
import { NewcomersNav } from '@/components/newcomers-nav';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/newcomers';
import {
    destroy,
    index as counsellorsIndex,
    store,
    update,
} from '@/routes/newcomers/counsellors';

type Counsellor = {
    id: number;
    member_id: number;
    name: string;
    member_number: string;
    phone: string | null;
    is_active: boolean;
    people: number;
    total: number;
};

export default function Counsellors({
    counsellors,
}: {
    counsellors: Counsellor[];
}) {
    const [picked, setPicked] = useState<MemberHit | null>(null);
    const form = useForm({ member_id: '' as number | '' });

    const add = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                setPicked(null);
                form.reset();
            },
        });
    };

    return (
        <>
            <Head title="Counsellors" />

            <div className="max-w-5xl space-y-5 p-4">
                <PageHeader
                    title="Counsellors"
                    description="Church members who counsel newcomers. Pick a counsellor on a person's record; a counsellor with people on record can be made inactive but not removed."
                />
                <NewcomersNav />

                <form
                    onSubmit={add}
                    className="space-y-3 rounded-lg border p-4"
                >
                    <h2 className="font-medium">Add a counsellor</h2>
                    {picked ? (
                        <p className="flex flex-wrap items-center gap-2 text-sm">
                            <span className="font-medium">
                                {picked.full_name}
                            </span>
                            <span className="text-muted-foreground">
                                {picked.member_number}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setPicked(null);
                                    form.setData('member_id', '');
                                }}
                            >
                                Change
                            </Button>
                        </p>
                    ) : (
                        <MemberPicker
                            invalid={Boolean(form.errors.member_id)}
                            onPick={(member) => {
                                setPicked(member);
                                form.setData('member_id', member.id);
                            }}
                        />
                    )}
                    <InputError message={form.errors.member_id} />
                    <Button type="submit" disabled={form.processing || !picked}>
                        Add counsellor
                    </Button>
                </form>

                {counsellors.length === 0 ? (
                    <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                        No counsellors yet. Add a member above.
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {counsellors.map((c) => (
                            <li
                                key={c.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div className="space-y-0.5">
                                    <p className="font-medium">
                                        {c.name}{' '}
                                        {!c.is_active && (
                                            <Badge variant="secondary">
                                                Inactive
                                            </Badge>
                                        )}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {[c.member_number, c.phone]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                <p className="text-sm text-muted-foreground tabular-nums">
                                    {c.people} counselling now · {c.total} in
                                    all
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.put(
                                                update(c.id).url,
                                                { is_active: !c.is_active },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {c.is_active
                                            ? 'Make inactive'
                                            : 'Make active'}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive hover:text-destructive"
                                        disabled={c.total > 0}
                                        title={
                                            c.total > 0
                                                ? 'Has people on record, so cannot be removed.'
                                                : undefined
                                        }
                                        onClick={() =>
                                            router.delete(destroy(c.id).url, {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        <Trash2 /> Remove
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Counsellors.layout = {
    breadcrumbs: [
        { title: 'Newcomers', href: index() },
        { title: 'Counsellors', href: counsellorsIndex() },
    ],
};
