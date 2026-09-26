import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2 } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { home } from '@/routes/portal';
import { store } from '@/routes/portal/requests';

type Field = {
    name: string;
    label: string;
    kind: 'text' | 'textarea' | 'select' | 'date' | 'time' | 'number';
    required?: boolean;
    options?: string[];
};

type Props = {
    type: string;
    definition: { label: string; description: string; fields: Field[] };
    changeable: Record<string, string>;
    current: Record<string, string | null>;
};

const MARITAL = ['single', 'married', 'divorced', 'widowed'];

export default function PortalRequest({
    type,
    definition,
    changeable,
    current,
}: Props) {
    const isChange = type === 'change_details';

    const form = useForm<{
        details: Record<string, string>;
        values: Record<string, string>;
        note: string;
    }>({
        details: {},
        values: Object.fromEntries(
            Object.keys(changeable).map((k) => [k, current[k] ?? '']),
        ),
        note: '',
    });

    const errors = form.errors as Record<string, string>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store(type).url);
    };

    const setDetail = (name: string, value: string) =>
        form.setData('details', { ...form.data.details, [name]: value });

    return (
        <div className="min-h-svh bg-background">
            <Head title={definition.label} />

            <div className="mx-auto max-w-xl space-y-6 p-4">
                <Link
                    href={home({ query: { tab: 'requests' } })}
                    className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Back to my requests
                </Link>

                <div>
                    <h1 className="text-xl font-medium">{definition.label}</h1>
                    <p className="text-sm text-muted-foreground">
                        {definition.description}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-5">
                    {isChange ? (
                        <>
                            <p className="text-sm text-muted-foreground">
                                Change what is not right and leave the rest.
                                Nothing changes on your record until the church
                                office approves it.
                            </p>
                            {Object.entries(changeable).map(
                                ([field, label]) => (
                                    <div key={field} className="grid gap-2">
                                        <Label htmlFor={field}>{label}</Label>
                                        {field === 'marital_status' ? (
                                            <NativeSelect
                                                id={field}
                                                value={form.data.values[field]}
                                                onChange={(e) =>
                                                    form.setData('values', {
                                                        ...form.data.values,
                                                        [field]: e.target.value,
                                                    })
                                                }
                                            >
                                                <option value="">
                                                    Not stated
                                                </option>
                                                {MARITAL.map((m) => (
                                                    <option key={m} value={m}>
                                                        {m[0].toUpperCase() +
                                                            m.slice(1)}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        ) : (
                                            <Input
                                                id={field}
                                                type={
                                                    field === 'marriage_date'
                                                        ? 'date'
                                                        : field === 'email'
                                                          ? 'email'
                                                          : 'text'
                                                }
                                                value={form.data.values[field]}
                                                onChange={(e) =>
                                                    form.setData('values', {
                                                        ...form.data.values,
                                                        [field]: e.target.value,
                                                    })
                                                }
                                            />
                                        )}
                                        <InputError
                                            message={errors[`values.${field}`]}
                                        />
                                    </div>
                                ),
                            )}
                            <InputError message={errors.values} />
                            <div className="grid gap-2">
                                <Label htmlFor="note">
                                    Anything else? (for a change of name or date
                                    of birth, say it here)
                                </Label>
                                <Textarea
                                    id="note"
                                    rows={3}
                                    value={form.data.note}
                                    onChange={(e) =>
                                        form.setData('note', e.target.value)
                                    }
                                    maxLength={2000}
                                />
                                <InputError message={errors.note} />
                            </div>
                        </>
                    ) : (
                        definition.fields.map((f) => (
                            <div key={f.name} className="grid gap-2">
                                <Label htmlFor={f.name}>
                                    {f.label}
                                    {f.required && ' *'}
                                </Label>
                                {f.kind === 'textarea' ? (
                                    <Textarea
                                        id={f.name}
                                        rows={4}
                                        value={form.data.details[f.name] ?? ''}
                                        onChange={(e) =>
                                            setDetail(f.name, e.target.value)
                                        }
                                        maxLength={2000}
                                    />
                                ) : f.kind === 'select' ? (
                                    <NativeSelect
                                        id={f.name}
                                        value={form.data.details[f.name] ?? ''}
                                        onChange={(e) =>
                                            setDetail(f.name, e.target.value)
                                        }
                                    >
                                        <option value="">Choose…</option>
                                        {(f.options ?? []).map((o) => (
                                            <option key={o} value={o}>
                                                {o}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                ) : (
                                    <Input
                                        id={f.name}
                                        type={
                                            f.kind === 'number'
                                                ? 'number'
                                                : f.kind
                                        }
                                        min={
                                            f.kind === 'number' ? 1 : undefined
                                        }
                                        value={form.data.details[f.name] ?? ''}
                                        onChange={(e) =>
                                            setDetail(f.name, e.target.value)
                                        }
                                        maxLength={200}
                                    />
                                )}
                                <InputError
                                    message={errors[`details.${f.name}`]}
                                />
                            </div>
                        ))
                    )}

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        {form.processing && (
                            <Loader2 className="animate-spin" />
                        )}{' '}
                        Send request
                    </Button>
                </form>
            </div>
        </div>
    );
}
