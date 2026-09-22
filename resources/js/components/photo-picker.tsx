import { Camera, Image as ImageIcon, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

const MAX_SIDE = 800;
const MAX_BYTES = 1_500_000;

/** Decode with the camera's rotation applied (phones store portraits sideways plus an EXIF flag). */
async function decode(file: File): Promise<ImageBitmap | HTMLImageElement> {
    if ('createImageBitmap' in window) {
        try {
            return await createImageBitmap(file, {
                imageOrientation: 'from-image',
            });
        } catch {
            // Fall through to the <img> route below.
        }
    }

    const url = URL.createObjectURL(file);

    try {
        return await new Promise<HTMLImageElement>((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error('decode'));
            img.src = url;
        });
    } finally {
        URL.revokeObjectURL(url);
    }
}

/** Shrink to at most 800px on the long side and re-encode as JPEG under about 1.5 MB. */
async function shrink(file: File): Promise<File> {
    const image = await decode(file);
    const scale = Math.min(1, MAX_SIDE / Math.max(image.width, image.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(image.width * scale);
    canvas.height = Math.round(image.height * scale);
    canvas
        .getContext('2d')
        ?.drawImage(image, 0, 0, canvas.width, canvas.height);

    if ('close' in image) {
        image.close();
    }

    let quality = 0.85;
    let blob: Blob | null = null;

    do {
        blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', quality),
        );
        quality -= 0.1;
    } while (blob && blob.size > MAX_BYTES && quality > 0.3);

    if (!blob) {
        throw new Error('encode');
    }

    return new File([blob], 'photo.jpg', { type: 'image/jpeg' });
}

/** Take a photo with the camera or choose one from the gallery. Emits a ready-to-upload File. */
export function PhotoPicker({
    value,
    onChange,
    error,
    currentUrl,
}: {
    value: File | null;
    /** The photo already on file, shown until a new one is chosen. */
    currentUrl?: string | null;
    onChange: (file: File | null) => void;
    error?: string;
}) {
    const [preview, setPreview] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [problem, setProblem] = useState<string | null>(null);

    useEffect(() => {
        if (!value) {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(value);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [value]);

    const pick = async (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = ''; // so choosing the same file again still fires

        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            setProblem('That file is not an image.');

            return;
        }

        setBusy(true);
        setProblem(null);

        try {
            onChange(await shrink(file));
        } catch {
            setProblem('That image could not be read. Try another photo.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="flex items-center gap-4">
            <div className="relative size-24 shrink-0">
                {preview || currentUrl ? (
                    <>
                        <img
                            src={preview ?? currentUrl ?? undefined}
                            alt="Photo preview"
                            className="size-24 rounded-full border object-cover"
                        />
                        {value && (
                            <button
                                type="button"
                                onClick={() => onChange(null)}
                                aria-label="Remove photo"
                                className="absolute -top-1 -right-1 flex size-6 items-center justify-center rounded-full bg-destructive text-white"
                            >
                                <X className="size-3.5" />
                            </button>
                        )}
                    </>
                ) : (
                    <div className="flex size-24 items-center justify-center rounded-full border-2 border-dashed text-muted-foreground">
                        {busy ? (
                            <span className="size-6 animate-spin rounded-full border-2 border-current border-t-transparent" />
                        ) : (
                            <Camera className="size-7 opacity-50" />
                        )}
                    </div>
                )}
            </div>

            <div className="grid gap-2">
                <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" size="sm" asChild>
                        <label className="cursor-pointer">
                            <Camera /> Camera
                            <input
                                type="file"
                                accept="image/*"
                                capture="environment"
                                onChange={pick}
                                disabled={busy}
                                className="sr-only"
                            />
                        </label>
                    </Button>
                    <Button type="button" variant="outline" size="sm" asChild>
                        <label className="cursor-pointer">
                            <ImageIcon /> Gallery
                            <input
                                type="file"
                                accept="image/*"
                                onChange={pick}
                                disabled={busy}
                                className="sr-only"
                            />
                        </label>
                    </Button>
                </div>
                <p className="text-xs text-muted-foreground">
                    Optional. Photos are shrunk to 800px before uploading.
                </p>
                {(problem ?? error) && (
                    <p className="text-sm text-destructive">
                        {problem ?? error}
                    </p>
                )}
            </div>
        </div>
    );
}
