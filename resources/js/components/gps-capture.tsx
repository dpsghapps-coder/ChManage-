import { ExternalLink, LocateFixed, MapPin, X } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';
import { Button } from '@/components/ui/button';

// Leaflet is only downloaded when someone opens the map.
const MapPicker = lazy(() => import('@/components/map-picker'));

export type GpsFix = {
    latitude: number;
    longitude: number;
    /** Metres; null when the position was picked on the map or typed in rather than read from the device. */
    accuracy: number | null;
};

export const mapUrl = (latitude: number, longitude: number) =>
    `https://www.google.com/maps?q=${latitude},${longitude}`;

const explain = (error: GeolocationPositionError) => {
    switch (error.code) {
        case error.PERMISSION_DENIED:
            return 'Location permission was denied. Allow it for this site in the browser settings and try again.';
        case error.POSITION_UNAVAILABLE:
            return 'The device could not work out its position. Move to an open area and try again.';
        default:
            return 'Finding the location took too long. Try again.';
    }
};

/**
 * Reads the device's GPS position. Browsers only allow this on HTTPS or localhost, and only after the
 * person agrees. Rejects with a message that says what to do about it.
 */
export function locateDevice(): Promise<GpsFix & { accuracy: number }> {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator) || !window.isSecureContext) {
            reject(
                new Error(
                    'Location is only available over HTTPS (or localhost) on a device with GPS.',
                ),
            );

            return;
        }

        navigator.geolocation.getCurrentPosition(
            ({ coords }) =>
                resolve({
                    latitude: Number(coords.latitude.toFixed(7)),
                    longitude: Number(coords.longitude.toFixed(7)),
                    accuracy: Math.round(coords.accuracy),
                }),
            (error) => reject(new Error(explain(error))),
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
        );
    });
}

export function GpsCapture({
    value,
    onChange,
}: {
    value: GpsFix | null;
    onChange: (fix: GpsFix | null) => void;
}) {
    const [locating, setLocating] = useState(false);
    const [problem, setProblem] = useState<string | null>(null);
    const [mapOpen, setMapOpen] = useState(false);

    const capture = async () => {
        setLocating(true);
        setProblem(null);

        try {
            onChange(await locateDevice());
        } catch (error) {
            setProblem((error as Error).message);
        } finally {
            setLocating(false);
        }
    };

    return (
        <div className="grid gap-2">
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={capture}
                    disabled={locating}
                >
                    <LocateFixed className={locating ? 'animate-pulse' : ''} />
                    {locating
                        ? 'Locating…'
                        : value
                          ? 'Update Location'
                          : 'Use Current Location'}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setMapOpen(true)}
                >
                    <MapPin /> Pick on Map
                </Button>
                {value && (
                    <>
                        <a
                            href={mapUrl(value.latitude, value.longitude)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 text-sm underline-offset-4 hover:underline"
                        >
                            {value.latitude.toFixed(5)},{' '}
                            {value.longitude.toFixed(5)}
                            <ExternalLink className="size-3.5" />
                        </a>
                        <span className="text-xs text-muted-foreground">
                            {value.accuracy === null
                                ? 'chosen on the map'
                                : `accurate to about ${value.accuracy} m`}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => onChange(null)}
                        >
                            <X /> Clear
                        </Button>
                    </>
                )}
            </div>
            {value && value.accuracy !== null && value.accuracy > 100 && (
                <p className="text-xs text-amber-600 dark:text-amber-400">
                    The fix is rough (over 100 m). Step outside or wait a moment
                    and update it, or adjust the pin on the map.
                </p>
            )}
            {problem && <p className="text-sm text-destructive">{problem}</p>}

            {mapOpen && (
                <Suspense fallback={null}>
                    <MapPicker
                        open={mapOpen}
                        onOpenChange={setMapOpen}
                        value={value}
                        onSave={onChange}
                    />
                </Suspense>
            )}
        </div>
    );
}
