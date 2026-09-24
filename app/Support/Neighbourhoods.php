<?php

namespace App\Support;

use App\Models\City;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Neighbourhoods by city, for the member form's Residence box. The church's City (Church Settings) picks which city's
 * neighbourhoods are suggested, along with the other towns in that city's region.
 *
 * Kept in the `cities` and `neighbourhoods` tables and edited on the Neighbourhoods page; the Accra list was seeded
 * from resources/data/ghana-neighbourhoods.json (taken from the dps-erp CRM).
 */
class Neighbourhoods
{
    /** @return array{city: ?string, region: ?string, neighbourhoods: list<string>} */
    public static function forCity(?string $city): array
    {
        $city = filled($city) ? trim($city) : null;

        if ($city === null) {
            return ['city' => null, 'region' => null, 'neighbourhoods' => []];
        }

        // MySQL's default collation compares names without regard to case.
        $record = City::where('name', $city)->first();

        return [
            'city' => $city,
            'region' => $record?->region ?? self::regionOf($city),
            'neighbourhoods' => $record ? $record->neighbourhoods()->pluck('name')->all() : [],
        ];
    }

    /**
     * Merges a list of cities into the tables: missing cities are created, missing neighbourhoods added, nothing removed.
     * Accepts [{ region, cities: [{ city, neighbourhoods: [...] }] }] (as in ghana-cities-neighbourhoods.json) or
     * { "City": [...] } (as in ghana-neighbourhoods.json).
     *
     * @return array{cities_added: int, neighbourhoods_added: int}
     */
    public static function import(array $data): array
    {
        $entries = array_is_list($data)
            ? collect($data)->flatMap(fn ($region) => collect($region['cities'] ?? [])->map(fn ($city) => [
                'city' => $city['city'] ?? '', 'region' => $region['region'] ?? null, 'neighbourhoods' => $city['neighbourhoods'] ?? [],
            ]))
            : collect($data)->map(fn ($names, $city) => ['city' => $city, 'region' => null, 'neighbourhoods' => $names])->values();

        $stats = ['cities_added' => 0, 'neighbourhoods_added' => 0];

        foreach ($entries as $entry) {
            $name = trim(preg_replace('/\s+/', ' ', (string) $entry['city']));

            if ($name === '') {
                continue;
            }

            $region = filled($entry['region']) ? trim(preg_replace('/\s+Region$/i', '', trim($entry['region']))) : null;
            $city = City::where('name', $name)->first();

            if (! $city) {
                $city = City::create(['name' => $name, 'region' => $region ?? self::regionOf($name)]);
                $stats['cities_added']++;
            }

            $existing = $city->neighbourhoods()->pluck('name')->map(fn ($n) => Str::lower($n))->all();

            foreach ((array) $entry['neighbourhoods'] as $neighbourhood) {
                $neighbourhood = trim(preg_replace('/\s+/', ' ', (string) $neighbourhood));

                if ($neighbourhood === '' || mb_strlen($neighbourhood) > 150 || in_array(Str::lower($neighbourhood), $existing, true)) {
                    continue;
                }

                $city->neighbourhoods()->create(['name' => $neighbourhood]);
                $existing[] = Str::lower($neighbourhood);
                $stats['neighbourhoods_added']++;
            }
        }

        return $stats;
    }

    /** The 16 regions, as named on the Ghana town list. @return list<string> */
    public static function regions(): array
    {
        return self::towns()->pluck('region')->filter()->unique()->sort()->values()->all();
    }

    /** The region of a town on the Ghana town list; a regional or district capital wins over a namesake elsewhere. */
    public static function regionOf(string $city): ?string
    {
        $towns = self::towns()->filter(fn ($t) => Str::lower($t['name'] ?? '') === Str::lower($city));

        return ($towns->first(fn ($t) => $t['capital'] ?? false) ?? $towns->first())['region'] ?? null;
    }

    /** @return Collection<int, array{name: string, district: ?string, region: string}> */
    private static function towns()
    {
        $path = config('church.ghana_towns');

        return collect(is_string($path) && is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : []);
    }
}
