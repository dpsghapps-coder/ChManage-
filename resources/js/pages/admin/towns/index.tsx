import { Head, router } from '@inertiajs/react';
import { ExternalLink, MapPin, RefreshCw, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { loadTowns } from '@/components/town-suggestions';
import type { Town } from '@/components/town-suggestions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, refresh } from '@/routes/admin/towns';

type Props = {
    updatedAt: string | null;
    sources: { name: string; url: string; license: string; covers: string }[];
};

const PAGE = 50;

export default function TownsIndex({ updatedAt, sources }: Props) {
    const [towns, setTowns] = useState<Town[] | null>(null);
    const [query, setQuery] = useState('');
    const [region, setRegion] = useState('');
    const [district, setDistrict] = useState('');
    const [shown, setShown] = useState(PAGE);
    const [refreshing, setRefreshing] = useState(false);

    // A new updatedAt means the list was just rebuilt, so skip the browser's cached copy.
    useEffect(() => {
        let active = true;
        void loadTowns(true).then((list) => active && setTowns(list));

        return () => {
            active = false;
        };
    }, [updatedAt]);

    const regions = useMemo(
        () => [...new Set((towns ?? []).map((t) => t.region))].sort(),
        [towns],
    );
    const districts = useMemo(
        () =>
            [
                ...new Set(
                    (towns ?? [])
                        .filter((t) => !region || t.region === region)
                        .map((t) => t.district)
                        .filter((d): d is string => Boolean(d)),
                ),
            ].sort(),
        [towns, region],
    );

    const q = query.trim().toLowerCase();
    const matches = (towns ?? []).filter(
        (t) =>
            (!region || t.region === region) &&
            (!district || t.district === district) &&
            (!q ||
                t.name.toLowerCase().includes(q) ||
                (t.district ?? '').toLowerCase().includes(q)),
    );

    // Any change of filter starts again from the top of the list.
    useEffect(() => setShown(PAGE), [query, region, district]);

    // Hover text: every level of the place at once.
    const tooltip = (t: Town) =>
        [
            `Town: ${t.name}`,
            `District: ${t.district ?? 'not recorded'}`,
            `Region: ${t.region} Region`,
        ].join(' · ');

    const place = (t: Town) =>
        [t.district, `${t.region} Region`].filter(Boolean).join(', ');

    return (
        <>
            <Head title="Ghana Towns" />

            <div className="space-y-6 p-4">
                <PageHeader
                    title="Ghana Towns"
                    description="The towns suggested for Place of Birth, Home Town and Residence on the member form. Any other name can still be typed there."
                    actions={
                        <Button
                            variant="outline"
                            disabled={refreshing}
                            onClick={() =>
                                router.post(
                                    refresh().url,
                                    {},
                                    {
                                        preserveScroll: true,
                                        onStart: () => setRefreshing(true),
                                        onFinish: () => setRefreshing(false),
                                    },
                                )
                            }
                        >
                            <RefreshCw
                                className={refreshing ? 'animate-spin' : ''}
                            />
                            {refreshing ? 'Refreshing…' : 'Refresh from source'}
                        </Button>
                    }
                />

                <section className="grid gap-3 rounded-lg border p-4 text-sm sm:grid-cols-[1fr_auto]">
                    <div className="space-y-1.5">
                        <p className="font-medium">Sources</p>
                        <ul className="space-y-1">
                            {sources.map((s) => (
                                <li key={s.name}>
                                    <a
                                        href={s.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 underline-offset-4 hover:underline"
                                    >
                                        {s.name}
                                        <ExternalLink className="size-3" />
                                    </a>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        — {s.covers} ({s.license})
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-1 sm:text-right">
                        <dt className="text-muted-foreground">Towns</dt>
                        <dd className="font-medium tabular-nums">
                            {towns ? towns.length.toLocaleString() : '…'}
                        </dd>
                        <dt className="text-muted-foreground">Regions</dt>
                        <dd className="font-medium tabular-nums">
                            {towns ? regions.length : '…'}
                        </dd>
                        <dt className="text-muted-foreground">Updated</dt>
                        <dd className="font-medium">
                            {updatedAt
                                ? new Date(updatedAt).toLocaleString()
                                : 'Never'}
                        </dd>
                    </dl>
                </section>

                <div className="flex flex-wrap gap-2">
                    <div className="relative w-full sm:w-auto sm:min-w-56 sm:flex-1">
                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search a town or district"
                            className="pl-9"
                            aria-label="Search towns"
                        />
                    </div>
                    <NativeSelect
                        value={region}
                        onChange={(e) => {
                            setRegion(e.target.value);
                            setDistrict('');
                        }}
                        className="w-full sm:w-48"
                        aria-label="Filter by region"
                    >
                        <option value="">All regions</option>
                        {regions.map((r) => (
                            <option key={r} value={r}>
                                {r}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        value={district}
                        onChange={(e) => setDistrict(e.target.value)}
                        className="w-full sm:w-56"
                        aria-label="Filter by district"
                    >
                        <option value="">All districts</option>
                        {districts.map((d) => (
                            <option key={d} value={d}>
                                {d}
                            </option>
                        ))}
                    </NativeSelect>
                </div>

                {towns === null ? (
                    <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                        Loading towns…
                    </p>
                ) : matches.length === 0 ? (
                    <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                        No town matches those filters.
                    </p>
                ) : (
                    <>
                        <p className="text-sm text-muted-foreground">
                            Showing{' '}
                            {Math.min(shown, matches.length).toLocaleString()}{' '}
                            of {matches.length.toLocaleString()}
                        </p>

                        {/* Phones: one card per town. */}
                        <ul className="grid gap-2 md:hidden">
                            {matches.slice(0, shown).map((t) => (
                                <li
                                    key={`${t.name}|${t.region}`}
                                    className="flex items-start gap-3 rounded-lg border p-3"
                                    title={tooltip(t)}
                                >
                                    <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                    <div className="min-w-0">
                                        <p className="font-medium">{t.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {place(t)}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="hidden rounded-lg border md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Town</TableHead>
                                        <TableHead>District</TableHead>
                                        <TableHead>Region</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {matches.slice(0, shown).map((t) => (
                                        <TableRow
                                            key={`${t.name}|${t.region}`}
                                            title={tooltip(t)}
                                        >
                                            <TableCell className="font-medium">
                                                {t.name}
                                            </TableCell>
                                            <TableCell>
                                                {t.district ?? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>{t.region}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {shown < matches.length && (
                            <div className="flex justify-center">
                                <Button
                                    variant="outline"
                                    onClick={() => setShown(shown + PAGE * 4)}
                                >
                                    Show more
                                </Button>
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

TownsIndex.layout = {
    breadcrumbs: [{ title: 'Ghana Towns', href: index() }],
};
