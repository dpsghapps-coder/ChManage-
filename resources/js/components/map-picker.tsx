import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { LocateFixed, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { GpsFix } from '@/components/gps-capture';
import { locateDevice } from '@/components/gps-capture';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Bundlers break Leaflet's icon path detection, so point it at the packaged images.
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl });

/** Accra: where the map starts when there is no position yet. */
const START: [number, number] = [5.6037, -0.187];

const round = (n: number) => Number(n.toFixed(6));

/**
 * Pick a position on a map: click or drag the pin, search for a place, use the device location, or type
 * the coordinates. Loaded lazily, so Leaflet is only downloaded when someone opens it.
 */
export default function MapPicker({
    open,
    onOpenChange,
    value,
    onSave,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    value: GpsFix | null;
    onSave: (fix: GpsFix) => void;
}) {
    const [container, setContainer] = useState<HTMLDivElement | null>(null);
    const map = useRef<L.Map | null>(null);
    const marker = useRef<L.Marker | null>(null);
    const [position, setPosition] = useState<[number, number]>(
        value ? [value.latitude, value.longitude] : START,
    );
    const [accuracy, setAccuracy] = useState<number | null>(
        value?.accuracy ?? null,
    );
    const [query, setQuery] = useState('');
    const [busy, setBusy] = useState<'search' | 'locate' | null>(null);
    const [problem, setProblem] = useState<string | null>(null);
    const [latText, setLatText] = useState(String(position[0]));
    const [lngText, setLngText] = useState(String(position[1]));

    const place = (lat: number, lng: number, fix: number | null = null) => {
        const next: [number, number] = [round(lat), round(lng)];
        setPosition(next);
        setLatText(String(next[0]));
        setLngText(String(next[1]));
        setAccuracy(fix);
        marker.current?.setLatLng(next);
        map.current?.setView(next, Math.max(map.current.getZoom(), 16));
    };

    // Build the map once the dialog's container exists; tear it down when the dialog closes.
    useEffect(() => {
        if (!container) {
            return;
        }

        const instance = L.map(container, {
            center: position,
            zoom: value ? 16 : 6,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(instance);

        const pin = L.marker(position, { draggable: true }).addTo(instance);
        const moved = (latlng: L.LatLng) => {
            setPosition([round(latlng.lat), round(latlng.lng)]);
            setLatText(String(round(latlng.lat)));
            setLngText(String(round(latlng.lng)));
            setAccuracy(null);
        };
        pin.on('dragend', () => moved(pin.getLatLng()));
        instance.on('click', (event: L.LeafletMouseEvent) => {
            pin.setLatLng(event.latlng);
            moved(event.latlng);
        });

        // The dialog animates in, so the map has to re-measure itself.
        const observer = new ResizeObserver(() => instance.invalidateSize());
        observer.observe(container);

        map.current = instance;
        marker.current = pin;

        return () => {
            observer.disconnect();
            instance.remove();
            map.current = null;
            marker.current = null;
        };
        // The starting position is only read when the map is created.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [container]);

    const useDevice = async () => {
        setBusy('locate');
        setProblem(null);

        try {
            const fix = await locateDevice();
            place(fix.latitude, fix.longitude, fix.accuracy);
        } catch (error) {
            setProblem((error as Error).message);
        } finally {
            setBusy(null);
        }
    };

    const searchPlace = async () => {
        if (!query.trim()) {
            return;
        }

        setBusy('search');
        setProblem(null);

        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(query.trim())}`,
                { headers: { Accept: 'application/json' } },
            );
            const found = (await response.json()) as {
                lat: string;
                lon: string;
            }[];

            if (found.length === 0) {
                setProblem('No place found with that name.');
            } else {
                place(Number(found[0].lat), Number(found[0].lon));
            }
        } catch {
            setProblem('The place search is not available right now.');
        } finally {
            setBusy(null);
        }
    };

    /** Typed coordinates: only move the pin once both are valid numbers in range. */
    const typed = (lat: string, lng: string) => {
        setLatText(lat);
        setLngText(lng);

        const la = Number(lat);
        const lo = Number(lng);

        if (
            lat.trim() &&
            lng.trim() &&
            Math.abs(la) <= 90 &&
            Math.abs(lo) <= 180
        ) {
            place(la, lo);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Pick the Location</DialogTitle>
                    <DialogDescription>
                        Click the map or drag the pin. You can also search for a
                        place or use this device’s location.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-wrap gap-2">
                    <div className="flex min-w-56 flex-1 gap-2">
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    void searchPlace();
                                }
                            }}
                            placeholder="Search a place, e.g. Kasoa market"
                            aria-label="Search a place"
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={searchPlace}
                            disabled={busy !== null}
                            aria-label="Search"
                        >
                            <Search />
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={useDevice}
                        disabled={busy !== null}
                    >
                        <LocateFixed
                            className={busy === 'locate' ? 'animate-pulse' : ''}
                        />
                        My Location
                    </Button>
                </div>

                <div
                    ref={setContainer}
                    className="z-0 h-80 w-full overflow-hidden rounded-md border"
                />

                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="pick-lat">Latitude</Label>
                        <Input
                            id="pick-lat"
                            inputMode="decimal"
                            value={latText}
                            onChange={(e) => typed(e.target.value, lngText)}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="pick-lng">Longitude</Label>
                        <Input
                            id="pick-lng"
                            inputMode="decimal"
                            value={lngText}
                            onChange={(e) => typed(latText, e.target.value)}
                        />
                    </div>
                </div>

                {problem && (
                    <p className="text-sm text-destructive">{problem}</p>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        onClick={() => {
                            onSave({
                                latitude: position[0],
                                longitude: position[1],
                                accuracy,
                            });
                            onOpenChange(false);
                        }}
                    >
                        Use This Location
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
