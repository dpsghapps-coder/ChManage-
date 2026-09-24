import { useEffect, useState } from 'react';
import { towns as townsRoute } from '@/routes/locations';

export type Town = { name: string; district: string | null; region: string };

// Fetched once per page load and shared by every town box (the browser also caches the file for a day).
let request: Promise<Town[]> | null = null;

/** `fresh` skips the browser's day-long cache, e.g. right after the list was refreshed. */
export function loadTowns(fresh = false): Promise<Town[]> {
    if (fresh) {
        request = null;
    }

    request ??= fetch(townsRoute().url, {
        headers: { Accept: 'application/json' },
        cache: fresh ? 'no-cache' : 'default',
    })
        .then((response) => (response.ok ? response.json() : []))
        .catch(() => {
            // Offline or failed: try again next time a form asks.
            request = null;

            return [];
        });

    return request;
}

export function useGhanaTowns(): Town[] {
    const [towns, setTowns] = useState<Town[]>([]);

    useEffect(() => {
        let active = true;
        void loadTowns().then((list) => active && setTowns(list));

        return () => {
            active = false;
        };
    }, []);

    return towns;
}

/**
 * A <datalist> of Ghana towns for an input's `list` attribute. Each suggestion shows its district and region;
 * `extra` places (e.g. ones already recorded) come first, and `region` keeps only that region's towns.
 * Anything can still be typed.
 */
export function TownSuggestions({
    id,
    extra = [],
    region = null,
}: {
    id: string;
    extra?: string[];
    region?: string | null;
}) {
    const towns = useGhanaTowns().filter((t) => !region || t.region === region);
    const known = new Set(towns.map((t) => t.name.toLowerCase()));

    return (
        <datalist id={id}>
            {[...new Set(extra)]
                .filter((place) => !known.has(place.toLowerCase()))
                .map((place) => (
                    <option key={`extra-${place}`} value={place} />
                ))}
            {towns.map((town) => (
                <option
                    key={`${town.name}|${town.region}`}
                    value={town.name}
                    label={[town.district, `${town.region} Region`]
                        .filter(Boolean)
                        .join(', ')}
                />
            ))}
        </datalist>
    );
}
