<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Builds resources/data/ghana-towns.json, the town suggestions for Place of Birth, Home Town and Residence, from three
 * open datasets (licenses in resources/data/ghana-towns.LICENSE.md):
 *
 *  - blingyplus/ghana-location-api (MIT): ~4,200 towns, each with its district and region.
 *  - oliverboamah/ghana-cities (MIT): ~500 major towns by region; fills gaps such as Kumasi, Tema and Winneba.
 *  - maaddae/ghana-cities-database (Apache 2.0): ~170 Greater Accra towns and neighbourhoods (Mamprobi, Spintex, ...).
 */
class ImportGhanaTowns extends Command
{
    protected $signature = 'locations:import
        {--from= : Read the source files from this folder instead of downloading them: regions.json, districts.json and cities.json (blingyplus), ghana-cities.json (oliverboamah) and maaddae-greater-accra.csv (maaddae)}';

    protected $description = 'Download the Ghana town lists and rebuild resources/data/ghana-towns.json';

    private const BLINGY = 'https://raw.githubusercontent.com/blingyplus/ghana-location-api/main/data/';

    private const OLIVER = 'https://raw.githubusercontent.com/oliverboamah/ghana-cities/master/cities.json';

    private const MAADDAE = 'https://raw.githubusercontent.com/maaddae/ghana-cities-database/master/src/csv/towns/greater_accra/towns_with_region_country.csv';

    /** Spelling slips in the sources, corrected on import. */
    private const CORRECTIONS = [
        'Valco Estades' => 'Valco Estates',
        'Galiliea' => 'Galilea',
        'kakasunaka No.1' => 'Kakasunanka No.1',
        'New Abelekuma' => 'New Ablekuma',
        'NanaKrom' => 'Nanakrom',
    ];

    public function handle(): int
    {
        // --from is for machines where PHP cannot make HTTPS requests (e.g. no CA bundle set in php.ini's curl.cainfo).
        $folder = $this->option('from');
        $fetch = fn (string $url, string $file) => $folder
            ? (string) file_get_contents(rtrim($folder, '/\\').'/'.$file)
            : Http::connectTimeout(30)->timeout(120)->retry(3, 2000)->get($url)->throw()->body();
        $json = fn (string $url, string $file) => json_decode($fetch($url, $file), true, flags: JSON_THROW_ON_ERROR);

        $regionRows = collect($json(self::BLINGY.'regions.json', 'regions.json'));
        $regions = $regionRows->mapWithKeys(fn ($r) => [$r['slug'] => self::regionName($r['name'])]);
        $districts = collect($json(self::BLINGY.'districts.json', 'districts.json'))->keyBy('slug');

        $towns = collect($json(self::BLINGY.'cities.json', 'cities.json'))->map(function ($city) use ($districts, $regions) {
            $district = $districts->get($city['district_slug'] ?? '');

            return [
                'name' => trim($city['name']),
                'district' => $district['name'] ?? null,
                'region' => $regions->get($district['region_slug'] ?? '') ?? null,
            ];
        });

        // Regional and district capitals: neither town list has plain "Accra", for one. They are also marked
        // "capital", so a capital (Dodowa, Greater Accra) wins over a village of the same name elsewhere.
        $capitals = [];

        foreach ($regionRows as $region) {
            $capitals[] = Str::lower(trim((string) ($region['capital'] ?? ''))).'|'.Str::lower(self::regionName($region['name']));
            $towns->push(['name' => trim((string) ($region['capital'] ?? '')), 'district' => null, 'region' => self::regionName($region['name'])]);
        }

        foreach ($districts as $district) {
            $capitals[] = Str::lower(trim((string) ($district['capital'] ?? ''))).'|'.Str::lower((string) $regions->get($district['region_slug'] ?? ''));
            $towns->push(['name' => trim((string) ($district['capital'] ?? '')), 'district' => $district['name'], 'region' => $regions->get($district['region_slug'] ?? '')]);
        }

        foreach ($json(self::OLIVER, 'ghana-cities.json') as $region => $names) {
            foreach ((array) $names as $name) {
                $towns->push(['name' => trim($name), 'district' => null, 'region' => self::regionName($region)]);
            }
        }

        // A CSV with an "id,name,country,region" header; every row is in Greater Accra.
        $rows = array_map('str_getcsv', preg_split('/\R/', trim($fetch(self::MAADDAE, 'maaddae-greater-accra.csv'))));
        $column = array_search('name', array_map('strtolower', array_shift($rows) ?? []), true);

        foreach ($column === false ? [] : $rows as $row) {
            $towns->push(['name' => trim((string) ($row[$column] ?? '')), 'district' => null, 'region' => 'Greater Accra']);
        }

        // One entry per town and region; where sources overlap, keep the one that knows the district.
        $merged = $towns
            ->map(fn ($t) => [...$t, 'name' => preg_replace('/\s+/', ' ', self::CORRECTIONS[$t['name']] ?? $t['name'])])
            ->filter(fn ($t) => $t['name'] !== '')
            ->sortBy(fn ($t) => $t['district'] === null ? 1 : 0)
            ->unique(fn ($t) => Str::lower($t['name']).'|'.Str::lower((string) $t['region']))
            ->sortBy(fn ($t) => Str::lower($t['name']).'|'.$t['region'], SORT_NATURAL)
            ->map(fn ($t) => in_array(Str::lower($t['name']).'|'.Str::lower((string) $t['region']), $capitals, true) ? [...$t, 'capital' => true] : $t)
            ->values();

        $path = resource_path('data/ghana-towns.json');
        file_put_contents($path, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $this->info("Wrote {$merged->count()} towns to {$path}.");

        return self::SUCCESS;
    }

    /** "Ashanti Region" and "Ashanti" both become "Ashanti"; the source's "Nort East" is corrected. */
    private static function regionName(string $name): string
    {
        $name = trim(preg_replace('/\s+Region$/i', '', trim($name)));

        return $name === 'Nort East' ? 'North East' : $name;
    }
}
