<?php

namespace App\Support;

use App\Models\ChurchSetting;
use App\Models\Presbytery;
use Illuminate\Support\Str;

/**
 * The PCG presbyteries and their districts, kept in the `presbyteries` and `presbytery_districts` tables and edited on
 * the PCG Presbyteries page. The markdown file set in config/church.php seeded those tables once (see the
 * create_presbyteries_tables migration) and still supplies the closing note.
 *
 * The file's shape: "## 1. Ga Presbytery", then "* **Headquarters:** …" style facts, then a "### Districts" heading
 * followed by one "* District" bullet each. A district's "(…)" is kept as a note, e.g. "Kaneshie (Head Station)".
 */
class Presbyteries
{
    /** @var array{presbyteries: list<array<string, mixed>>, note: ?string}|null */
    private static ?array $parsed = null;

    private static ?string $parsedPath = null;

    /**
     * @return list<array{id: int, name: string, title: string, short_name: ?string, headquarters: ?string, coverage: ?string,
     *     facts: list<array{label: string, value: string}>, districts: list<array{id: int, name: string, note: ?string}>}>
     */
    public static function all(): array
    {
        return Presbytery::with('districts')->orderBy('sort_order')->orderBy('title')->get()
            ->map(fn (Presbytery $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'title' => $p->title,
                'short_name' => $p->short_name,
                'headquarters' => $p->headquarters,
                'coverage' => $p->coverage,
                'facts' => $p->facts ?? [],
                'districts' => $p->districts->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'note' => $d->note])->values()->all(),
            ])->values()->all();
    }

    /** The closing remark at the foot of the markdown file, if any. */
    public static function note(): ?string
    {
        return self::parsed()['note'];
    }

    /** For a presbytery → district picker. @return list<array{name: string, headquarters: ?string, districts: list<string>}> */
    public static function options(): array
    {
        return array_map(fn ($p) => [
            'name' => $p['name'],
            'headquarters' => $p['headquarters'],
            'districts' => array_column($p['districts'], 'name'),
        ], self::all());
    }

    /** This church's own presbytery, district and congregation, as set on the Church Settings page. @return array{presbytery: string, district: string, congregation: string} */
    public static function church(): array
    {
        $stored = ChurchSetting::values(['presbytery_name', 'district_name', 'congregation_name']);

        return [
            'presbytery' => (string) ($stored['presbytery_name'] ?? ''),
            'district' => (string) ($stored['district_name'] ?? ''),
            'congregation' => (string) ($stored['congregation_name'] ?? ''),
        ];
    }

    /** Every district as a place name, e.g. "Osu District, Ga Presbytery". @return list<string> */
    public static function places(): array
    {
        return collect(self::all())
            ->flatMap(fn ($p) => array_map(fn ($d) => self::districtTitle($d['name']).', '.$p['title'], $p['districts']))
            ->values()->all();
    }

    /**
     * Suggestions for a "where" box (place of baptism, a staff station): this congregation first, then the places
     * already recorded, then every PCG district. Duplicates are dropped, ignoring case.
     *
     * @param  iterable<?string>  $recorded
     * @return list<string>
     */
    public static function placeSuggestions(iterable $recorded = []): array
    {
        $recorded = collect($recorded)->filter(fn ($place) => filled($place))->map(fn ($place) => trim($place))->sort(SORT_NATURAL | SORT_FLAG_CASE);

        return collect([ChurchSetting::values(['congregation_name'])['congregation_name'] ?? null])
            ->filter(fn ($place) => filled($place))
            ->concat($recorded)
            ->concat(self::places())
            ->unique(fn ($place) => Str::lower($place))
            ->values()->all();
    }

    /** "Ga" → "Ga Presbytery"; a name that already says "Presbytery" is left alone. */
    public static function presbyteryTitle(string $name): string
    {
        return Str::contains($name, 'Presbytery', ignoreCase: true) ? $name : $name.' Presbytery';
    }

    /** "Osu" → "Osu District"; "New York District" is left alone. */
    public static function districtTitle(string $name): string
    {
        return Str::contains($name, 'District', ignoreCase: true) ? $name : $name.' District';
    }

    /** Forgets the parsed markdown file (for tests that point the config at another file). */
    public static function flush(): void
    {
        self::$parsed = null;
        self::$parsedPath = null;
    }

    /** @return array{presbyteries: list<array<string, mixed>>, note: ?string} */
    private static function parsed(): array
    {
        $path = config('church.presbyteries');

        if (self::$parsed === null || self::$parsedPath !== $path) {
            self::$parsed = is_string($path) && is_file($path)
                ? self::parse((string) file_get_contents($path))
                : ['presbyteries' => [], 'note' => null];
            self::$parsedPath = $path;
        }

        return self::$parsed;
    }

    /** @return array{presbyteries: list<array<string, mixed>>, note: ?string} */
    public static function parse(string $markdown): array
    {
        $presbyteries = [];
        $current = null;
        $inDistricts = false;
        $note = [];

        foreach (preg_split('/\R/', $markdown) as $line) {
            $line = trim($line);

            if (preg_match('/^##\s+(?:\d+\.\s*)?(.+)$/', $line, $m) && ! str_starts_with($line, '###')) {
                if ($current) {
                    $presbyteries[] = $current;
                }

                [$title, $short] = self::splitNote($m[1]);
                $current = [
                    'name' => Str::startsWith($title, 'Presbytery of') ? $title : trim(preg_replace('/\s+Presbytery$/i', '', $title)),
                    'title' => $title,
                    'short_name' => $short,
                    'headquarters' => null,
                    'coverage' => null,
                    'facts' => [],
                    'districts' => [],
                ];
                $inDistricts = false;

                continue;
            }

            if (str_starts_with($line, '>')) {
                $text = self::plain(ltrim($line, "> \t"));

                if ($text !== '' && ! str_ends_with($text, ':')) {
                    $note[] = $text;
                }

                continue;
            }

            if (! $current) {
                continue;
            }

            if (str_starts_with($line, '###')) {
                $inDistricts = (bool) preg_match('/district/i', $line);

                continue;
            }

            if (! preg_match('/^[*-]\s+(.+)$/', $line, $m)) {
                continue;
            }

            if (! $inDistricts && preg_match('/^\*\*(.+?):\*\*\s*(.+)$/', $m[1], $fact)) {
                $label = trim($fact[1]);
                $value = self::plain($fact[2]);

                match (Str::lower($label)) {
                    'headquarters' => $current['headquarters'] = $value,
                    'geographical coverage' => $current['coverage'] = $value,
                    default => $current['facts'][] = ['label' => $label, 'value' => $value],
                };

                continue;
            }

            if ($inDistricts) {
                [$name, $districtNote] = self::splitNote(self::plain($m[1]));
                $current['districts'][] = ['name' => $name, 'note' => $districtNote];
            }
        }

        if ($current) {
            $presbyteries[] = $current;
        }

        return ['presbyteries' => $presbyteries, 'note' => $note ? implode(' ', $note) : null];
    }

    /** "Kaneshie (Head Station)" → ["Kaneshie", "Head Station"]. @return array{string, ?string} */
    private static function splitNote(string $text): array
    {
        $text = trim($text);

        return preg_match('/^(.+?)\s*\(([^()]+)\)$/', $text, $m) ? [trim($m[1]), trim($m[2])] : [$text, null];
    }

    /** Drops markdown emphasis. */
    private static function plain(string $text): string
    {
        return trim(str_replace('*', '', $text));
    }
}
