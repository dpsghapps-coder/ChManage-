import { Head, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    CheckCircle2,
    Church,
    FileText,
    Gauge,
    Layers,
    ListChecks,
    Loader2,
    Plus,
    Sparkles,
    UserCog,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/setup';
import { cn } from '@/lib/utils';

type Presbytery = {
    name: string;
    headquarters: string | null;
    districts: string[];
};
type Entry = { name: string; on: boolean };
/** A list as chips: standard entries start ticked, and the person unticks what this congregation does not use. */
type Chips = Record<string, Entry[]>;

type Props = {
    presbyteries: Presbytery[];
    cities: string[];
    defaults: { followup_days: number; term_warning_days: number };
    lists: {
        groups: string[];
        positions: Record<string, string[]>;
        committees: string[];
        occupations: Record<string, string[]>;
        venues: string[];
        options: Record<string, string[]>;
    };
    labels: {
        positions: Record<string, string>;
        options: Record<string, string>;
    };
};

const ticked = (names: string[]): Entry[] =>
    names.map((name) => ({ name, on: true }));
const tickedGroups = (groups: Record<string, string[]>): Chips =>
    Object.fromEntries(
        Object.entries(groups).map(([key, names]) => [key, ticked(names)]),
    );
const chosen = (entries: Entry[]) =>
    entries.filter((e) => e.on).map((e) => e.name);
const chosenGroups = (chips: Chips) =>
    Object.fromEntries(
        Object.entries(chips).map(([key, entries]) => [key, chosen(entries)]),
    );
const count = (chips: Chips) =>
    Object.values(chips).reduce((n, entries) => n + chosen(entries).length, 0);

function ChipGroup({
    title,
    entries,
    onChange,
    placeholder,
}: {
    title?: string;
    entries: Entry[];
    onChange: (entries: Entry[]) => void;
    placeholder: string;
}) {
    const [text, setText] = useState('');

    const add = () => {
        const name = text.trim();

        if (!name) {
            return;
        }

        // A name already listed is ticked again instead of repeated.
        const same = entries.findIndex(
            (e) => e.name.toLowerCase() === name.toLowerCase(),
        );
        onChange(
            same >= 0
                ? entries.map((e, i) => (i === same ? { ...e, on: true } : e))
                : [...entries, { name, on: true }],
        );
        setText('');
    };

    return (
        <div className="space-y-2">
            {title && (
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {title}
                </p>
            )}
            <div className="flex flex-wrap gap-2">
                {entries.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Nothing listed. Add your own below.
                    </p>
                )}
                {entries.map((entry, i) => (
                    <button
                        key={entry.name}
                        type="button"
                        aria-pressed={entry.on}
                        onClick={() =>
                            onChange(
                                entries.map((e, j) =>
                                    j === i ? { ...e, on: !e.on } : e,
                                ),
                            )
                        }
                        className={cn(
                            'inline-flex items-center gap-1 rounded-full border px-3 py-1 text-sm transition-colors',
                            entry.on
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'text-muted-foreground line-through hover:border-foreground/40',
                        )}
                    >
                        {entry.on && <Check className="size-3.5" />}
                        {entry.name}
                    </button>
                ))}
            </div>
            <div className="flex gap-2">
                <Input
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            add();
                        }
                    }}
                    placeholder={placeholder}
                    maxLength={150}
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={add}
                    disabled={!text.trim()}
                >
                    <Plus /> Add
                </Button>
            </div>
        </div>
    );
}

function Field({
    label,
    error,
    hint,
    children,
}: {
    label: string;
    error?: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

export default function Setup({
    presbyteries,
    cities,
    defaults,
    lists,
    labels,
}: Props) {
    const form = useForm({
        username: '',
        first_name: '',
        last_name: '',
        email: '',
        password: '',
        password_confirmation: '',
        church_name: 'Presbyterian Church of Ghana',
        presbytery: '',
        district: '',
        congregation: '',
        city: '',
        followup_days: defaults.followup_days,
        term_warning_days: defaults.term_warning_days,
    });

    const [groups, setGroups] = useState<Chips>({ '': ticked(lists.groups) });
    const [positions, setPositions] = useState<Chips>(
        tickedGroups(lists.positions),
    );
    const [committees, setCommittees] = useState<Chips>({
        '': ticked(lists.committees),
    });
    const [occupations, setOccupations] = useState<Chips>(
        tickedGroups(lists.occupations),
    );
    const [venues, setVenues] = useState<Chips>({ '': ticked(lists.venues) });
    const [options, setOptions] = useState<Chips>(tickedGroups(lists.options));
    const [category, setCategory] = useState('');
    const [stepIndex, setStepIndex] = useState(0);
    const [dataMode, setDataMode] = useState<'sample' | 'clean' | null>(null);

    const steps = [
        { key: 'welcome', label: 'Start', icon: Sparkles },
        { key: 'admin', label: 'Administrator', icon: UserCog },
        { key: 'church', label: 'The church', icon: Church },
        { key: 'limits', label: 'Reminders', icon: Gauge },
        { key: 'groups', label: 'Groups & positions', icon: Layers },
        { key: 'committees', label: 'Committees & venues', icon: Layers },
        { key: 'occupations', label: 'Occupations', icon: ListChecks },
        { key: 'newcomers', label: 'Newcomer lists', icon: ListChecks },
        { key: 'review', label: 'Finish', icon: CheckCircle2 },
    ];
    const current = steps[stepIndex].key;
    const d = form.data;

    const districts = useMemo(() => {
        const key = d.presbytery
            .trim()
            .toLowerCase()
            .replace(/\s*presbytery$/, '');
        return (
            presbyteries.find(
                (p) =>
                    p.name.toLowerCase().replace(/\s*presbytery$/, '') === key,
            )?.districts ?? []
        );
    }, [d.presbytery, presbyteries]);

    const canProceed = (() => {
        switch (current) {
            case 'welcome':
                return dataMode !== null;
            case 'admin':
                return (
                    d.username.trim() !== '' &&
                    d.first_name.trim() !== '' &&
                    d.password.length >= 8 &&
                    d.password === d.password_confirmation
                );
            case 'church':
                return [
                    d.church_name,
                    d.presbytery,
                    d.district,
                    d.congregation,
                ].every((v) => v.trim() !== '');
            case 'limits':
                return [d.followup_days, d.term_warning_days].every(
                    (n) => n >= 7 && n <= 365,
                );
            default:
                return true;
        }
    })();

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            groups: chosen(groups['']),
            positions: chosenGroups(positions),
            committees: chosen(committees['']),
            occupations: chosenGroups(occupations),
            venues: chosen(venues['']),
            options: chosenGroups(options),
            sample_data: dataMode === 'sample',
        }));
        form.post(store().url);
    };

    // A failed check on the server sends the person back to the step that holds the field.
    const errorStep = () => {
        const has = (...keys: string[]) =>
            keys.some((k) =>
                Object.keys(form.errors).some(
                    (e) => e === k || e.startsWith(`${k}.`),
                ),
            );

        if (has('username', 'first_name', 'last_name', 'email', 'password'))
            return 'admin';
        if (
            has('church_name', 'presbytery', 'district', 'congregation', 'city')
        )
            return 'church';
        if (has('followup_days', 'term_warning_days')) return 'limits';

        return null;
    };
    const failedStep = errorStep();

    return (
        <div className="min-h-svh bg-background px-4 py-8 sm:py-12">
            <Head title="Set up" />

            <div className="mx-auto w-full max-w-2xl">
                <div className="mb-6 text-center">
                    <AppLogoIcon className="mx-auto size-10 fill-current text-foreground" />
                    <h1 className="mt-3 text-2xl font-semibold">
                        Welcome to ChManage+
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Let&apos;s set up this congregation. It takes a few
                        minutes, and everything can be changed later.
                    </p>
                </div>

                <ol
                    className="mb-6 flex items-center px-1"
                    aria-label="Setup steps"
                >
                    {steps.map((s, i) => (
                        <li
                            key={s.key}
                            className={cn(
                                'flex items-center',
                                i < steps.length - 1 && 'flex-1',
                            )}
                        >
                            <span
                                title={s.label}
                                className={cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-full text-sm',
                                    i <= stepIndex
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                <s.icon className="size-4" />
                            </span>
                            {i < steps.length - 1 && (
                                <span
                                    className={cn(
                                        'mx-1 h-px flex-1',
                                        i < stepIndex
                                            ? 'bg-primary'
                                            : 'bg-border',
                                    )}
                                />
                            )}
                        </li>
                    ))}
                </ol>

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-xl border p-5 sm:p-6"
                >
                    {current === 'welcome' && (
                        <div className="space-y-4">
                            <div>
                                <h2 className="text-lg font-medium">
                                    How would you like to start?
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    You can always add real records and remove
                                    sample ones later.
                                </p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <button
                                    type="button"
                                    onClick={() => setDataMode('sample')}
                                    className={cn(
                                        'rounded-lg border p-4 text-left transition-colors hover:border-primary/50',
                                        dataMode === 'sample' &&
                                            'border-primary bg-primary/5',
                                    )}
                                >
                                    <Sparkles className="mb-2 size-5 text-primary" />
                                    <p className="font-medium">
                                        Start with sample data
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Adds a sample congregation with
                                        members, committees, events, meetings,
                                        newcomers, communion and requests, so
                                        you can try the app out. Every sample
                                        record is marked and can be removed at
                                        any time.
                                    </p>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setDataMode('clean')}
                                    className={cn(
                                        'rounded-lg border p-4 text-left transition-colors hover:border-primary/50',
                                        dataMode === 'clean' &&
                                            'border-primary bg-primary/5',
                                    )}
                                >
                                    <FileText className="mb-2 size-5 text-primary" />
                                    <p className="font-medium">Start clean</p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        An empty system with just this
                                        congregation&apos;s own setup, ready
                                        for real records.
                                    </p>
                                </button>
                            </div>
                        </div>
                    )}

                    {current === 'admin' && (
                        <div className="space-y-4">
                            <div>
                                <h2 className="text-lg font-medium">
                                    Create the administrator account
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    This account has full access. You will sign
                                    in with it, and can add other users later.
                                </p>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="First name *"
                                    error={form.errors.first_name}
                                >
                                    <Input
                                        value={d.first_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'first_name',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={100}
                                        autoComplete="given-name"
                                    />
                                </Field>
                                <Field
                                    label="Last name"
                                    error={form.errors.last_name}
                                >
                                    <Input
                                        value={d.last_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'last_name',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={100}
                                        autoComplete="family-name"
                                    />
                                </Field>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Username *"
                                    error={form.errors.username}
                                    hint="Letters, numbers, dots, dashes and underscores."
                                >
                                    <Input
                                        value={d.username}
                                        onChange={(e) =>
                                            form.setData(
                                                'username',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={50}
                                        autoComplete="username"
                                    />
                                </Field>
                                <Field label="Email" error={form.errors.email}>
                                    <Input
                                        type="email"
                                        value={d.email}
                                        onChange={(e) =>
                                            form.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={150}
                                        autoComplete="email"
                                    />
                                </Field>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Password *"
                                    error={form.errors.password}
                                    hint="At least 8 characters."
                                >
                                    <Input
                                        type="password"
                                        value={d.password}
                                        onChange={(e) =>
                                            form.setData(
                                                'password',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                    />
                                </Field>
                                <Field
                                    label="Confirm password *"
                                    error={
                                        d.password_confirmation &&
                                        d.password !== d.password_confirmation
                                            ? "The passwords don't match."
                                            : undefined
                                    }
                                >
                                    <Input
                                        type="password"
                                        value={d.password_confirmation}
                                        onChange={(e) =>
                                            form.setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                    />
                                </Field>
                            </div>
                        </div>
                    )}

                    {current === 'church' && (
                        <div className="space-y-4">
                            <div>
                                <h2 className="text-lg font-medium">
                                    Who is this church?
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Shown across the system and on reports.
                                </p>
                            </div>
                            <Field
                                label="Church name *"
                                error={form.errors.church_name}
                            >
                                <Input
                                    value={d.church_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'church_name',
                                            e.target.value,
                                        )
                                    }
                                    maxLength={150}
                                />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Presbytery *"
                                    error={form.errors.presbytery}
                                >
                                    <Input
                                        list="setup-presbyteries"
                                        value={d.presbytery}
                                        onChange={(e) =>
                                            form.setData(
                                                'presbytery',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={150}
                                        placeholder="Choose or type"
                                    />
                                    <datalist id="setup-presbyteries">
                                        {presbyteries.map((p) => (
                                            <option
                                                key={p.name}
                                                value={p.name}
                                            />
                                        ))}
                                    </datalist>
                                </Field>
                                <Field
                                    label="District *"
                                    error={form.errors.district}
                                >
                                    <Input
                                        list="setup-districts"
                                        value={d.district}
                                        onChange={(e) =>
                                            form.setData(
                                                'district',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={150}
                                        placeholder="Choose or type"
                                    />
                                    <datalist id="setup-districts">
                                        {districts.map((name) => (
                                            <option key={name} value={name} />
                                        ))}
                                    </datalist>
                                </Field>
                            </div>
                            <Field
                                label="Congregation *"
                                error={form.errors.congregation}
                            >
                                <Input
                                    value={d.congregation}
                                    onChange={(e) =>
                                        form.setData(
                                            'congregation',
                                            e.target.value,
                                        )
                                    }
                                    maxLength={150}
                                    placeholder="Ebenezer Congregation, Mamprobi"
                                />
                            </Field>
                            <Field
                                label="City"
                                error={form.errors.city}
                                hint="The member form suggests this city's neighbourhoods for Residence."
                            >
                                <Input
                                    list="setup-cities"
                                    value={d.city}
                                    onChange={(e) =>
                                        form.setData('city', e.target.value)
                                    }
                                    maxLength={150}
                                    placeholder="Accra"
                                />
                                <datalist id="setup-cities">
                                    {cities.map((name) => (
                                        <option key={name} value={name} />
                                    ))}
                                </datalist>
                            </Field>
                        </div>
                    )}

                    {current === 'limits' && (
                        <div className="space-y-4">
                            <div>
                                <h2 className="text-lg font-medium">
                                    When to be reminded
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Between 7 and 365 days. These can be changed
                                    later in Church Settings.
                                </p>
                            </div>
                            <Field
                                label="Newcomer follow-up (days)"
                                error={form.errors.followup_days}
                                hint="Someone still coming, with no visit or lesson for this long, is flagged on the Newcomers overview."
                            >
                                <Input
                                    type="number"
                                    min={7}
                                    max={365}
                                    value={d.followup_days}
                                    onChange={(e) =>
                                        form.setData(
                                            'followup_days',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Committee term warning (days)"
                                error={form.errors.term_warning_days}
                                hint="A committee term ending within this many days is flagged for renewal."
                            >
                                <Input
                                    type="number"
                                    min={7}
                                    max={365}
                                    value={d.term_warning_days}
                                    onChange={(e) =>
                                        form.setData(
                                            'term_warning_days',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                        </div>
                    )}

                    {current === 'groups' && (
                        <div className="space-y-6">
                            <div>
                                <h2 className="text-lg font-medium">
                                    Service groups and positions
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Tap an entry to leave it out. Add any this
                                    congregation has that are missing.
                                </p>
                            </div>
                            <ChipGroup
                                title="Service groups"
                                entries={groups['']}
                                onChange={(entries) =>
                                    setGroups({ '': entries })
                                }
                                placeholder="Add a service group"
                            />
                            {Object.entries(positions).map(
                                ([type, entries]) => (
                                    <ChipGroup
                                        key={type}
                                        title={`Positions: ${labels.positions[type] ?? type}`}
                                        entries={entries}
                                        onChange={(next) =>
                                            setPositions({
                                                ...positions,
                                                [type]: next,
                                            })
                                        }
                                        placeholder="Add a position"
                                    />
                                ),
                            )}
                        </div>
                    )}

                    {current === 'committees' && (
                        <div className="space-y-6">
                            <div>
                                <h2 className="text-lg font-medium">
                                    Committees and venues
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Tap an entry to leave it out.
                                </p>
                            </div>
                            <ChipGroup
                                title="Committees"
                                entries={committees['']}
                                onChange={(entries) =>
                                    setCommittees({ '': entries })
                                }
                                placeholder="Add a committee"
                            />
                            <ChipGroup
                                title="Event venues"
                                entries={venues['']}
                                onChange={(entries) =>
                                    setVenues({ '': entries })
                                }
                                placeholder="Add a venue"
                            />
                        </div>
                    )}

                    {current === 'occupations' && (
                        <div className="space-y-6">
                            <div>
                                <h2 className="text-lg font-medium">
                                    Occupations
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Offered on the member form. Tap to leave one
                                    out.
                                </p>
                            </div>
                            {Object.entries(occupations).map(
                                ([name, entries]) => (
                                    <ChipGroup
                                        key={name}
                                        title={name}
                                        entries={entries}
                                        onChange={(next) =>
                                            setOccupations({
                                                ...occupations,
                                                [name]: next,
                                            })
                                        }
                                        placeholder={`Add to ${name}`}
                                    />
                                ),
                            )}
                            <div className="flex gap-2 border-t pt-4">
                                <Input
                                    value={category}
                                    onChange={(e) =>
                                        setCategory(e.target.value)
                                    }
                                    placeholder="Add a new category"
                                    maxLength={100}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={
                                        !category.trim() ||
                                        category.trim() in occupations
                                    }
                                    onClick={() => {
                                        setOccupations({
                                            ...occupations,
                                            [category.trim()]: [],
                                        });
                                        setCategory('');
                                    }}
                                >
                                    <Plus /> Category
                                </Button>
                            </div>
                        </div>
                    )}

                    {current === 'newcomers' && (
                        <div className="space-y-6">
                            <div>
                                <h2 className="text-lg font-medium">
                                    Newcomer lists
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    The choices offered on the newcomer form.
                                </p>
                            </div>
                            {Object.entries(options).map(([kind, entries]) => (
                                <ChipGroup
                                    key={kind}
                                    title={labels.options[kind] ?? kind}
                                    entries={entries}
                                    onChange={(next) =>
                                        setOptions({ ...options, [kind]: next })
                                    }
                                    placeholder="Add a choice"
                                />
                            ))}
                        </div>
                    )}

                    {current === 'review' && (
                        <div className="space-y-4">
                            <div>
                                <h2 className="text-lg font-medium">
                                    Review and finish
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Check the summary, then finish. Nothing is
                                    saved until you do.
                                </p>
                            </div>
                            <dl className="divide-y rounded-lg border text-sm">
                                {[
                                    [
                                        'Data',
                                        dataMode === 'sample'
                                            ? 'Sample data will be added'
                                            : 'Clean, no sample data',
                                    ],
                                    [
                                        'Administrator',
                                        `${d.first_name} ${d.last_name}`.trim() +
                                            ` (${d.username})`,
                                    ],
                                    ['Church', d.church_name],
                                    [
                                        'Congregation',
                                        `${d.congregation}, ${d.district} district, ${d.presbytery}`,
                                    ],
                                    ['City', d.city || 'Not set'],
                                    [
                                        'Reminders',
                                        `Follow-up ${d.followup_days} days, committee terms ${d.term_warning_days} days`,
                                    ],
                                    [
                                        'Service groups',
                                        `${chosen(groups['']).length} selected`,
                                    ],
                                    [
                                        'Positions',
                                        `${count(positions)} selected`,
                                    ],
                                    [
                                        'Committees',
                                        `${chosen(committees['']).length} selected`,
                                    ],
                                    [
                                        'Event venues',
                                        `${chosen(venues['']).length} selected`,
                                    ],
                                    [
                                        'Occupations',
                                        `${count(occupations)} selected`,
                                    ],
                                    [
                                        'Newcomer choices',
                                        `${count(options)} selected`,
                                    ],
                                ].map(([term, value]) => (
                                    <div
                                        key={term}
                                        className="flex justify-between gap-4 px-3 py-2"
                                    >
                                        <dt className="text-muted-foreground">
                                            {term}
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            {failedStep && (
                                <p className="text-sm text-red-600 dark:text-red-400">
                                    Something needs correcting on the{' '}
                                    <button
                                        type="button"
                                        className="underline"
                                        onClick={() =>
                                            setStepIndex(
                                                steps.findIndex(
                                                    (s) => s.key === failedStep,
                                                ),
                                            )
                                        }
                                    >
                                        {
                                            steps.find(
                                                (s) => s.key === failedStep,
                                            )?.label
                                        }
                                    </button>{' '}
                                    step.
                                </p>
                            )}
                            {dataMode === 'sample' && (
                                <p className="text-sm text-muted-foreground">
                                    Adding the sample data takes a little
                                    longer than a clean setup. Please wait for
                                    the page to move on after you finish.
                                </p>
                            )}
                        </div>
                    )}

                    <div className="flex justify-between border-t pt-4">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() =>
                                setStepIndex((i) => Math.max(i - 1, 0))
                            }
                            className={cn(stepIndex === 0 && 'invisible')}
                        >
                            <ArrowLeft /> Back
                        </Button>
                        {current === 'review' ? (
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? (
                                    <Loader2 className="animate-spin" />
                                ) : (
                                    <CheckCircle2 />
                                )}{' '}
                                Finish setup
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                disabled={!canProceed}
                                onClick={() =>
                                    setStepIndex((i) =>
                                        Math.min(i + 1, steps.length - 1),
                                    )
                                }
                            >
                                Next <ArrowRight />
                            </Button>
                        )}
                    </div>
                </form>
            </div>
        </div>
    );
}
