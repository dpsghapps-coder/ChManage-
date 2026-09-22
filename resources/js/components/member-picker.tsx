import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PersonAvatar } from '@/components/person-avatar';
import { Input } from '@/components/ui/input';
import { search } from '@/routes/members';

export type MemberHit = {
    id: number;
    member_number: string;
    full_name: string;
    phones: string[];
    photo_url: string | null;
};

/** Type-ahead over active church members; picking one fills the guardian from the member record. */
export function MemberPicker({
    onPick,
    invalid,
}: {
    onPick: (member: MemberHit) => void;
    invalid: boolean;
}) {
    const [term, setTerm] = useState('');
    const [hits, setHits] = useState<MemberHit[]>([]);
    const [searching, setSearching] = useState(false);

    useEffect(() => {
        if (term.trim().length < 2) {
            setHits([]);

            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setSearching(true);

            try {
                const response = await fetch(
                    search({ query: { q: term.trim() } }).url,
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );
                setHits(response.ok ? await response.json() : []);
            } catch {
                // Aborted by a newer keystroke, or offline: leave the list as it is.
            } finally {
                setSearching(false);
            }
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [term]);

    return (
        <div className="relative">
            <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
            <Input
                value={term}
                onChange={(e) => setTerm(e.target.value)}
                placeholder="Search active members by name, number or mobile"
                className="pl-9"
                aria-label="Search members"
                aria-invalid={invalid}
            />
            {term.trim().length >= 2 && (
                <ul className="mt-1 max-h-60 overflow-auto rounded-md border bg-popover text-sm shadow-sm">
                    {hits.length === 0 && (
                        <li className="px-3 py-2 text-muted-foreground">
                            {searching
                                ? 'Searching…'
                                : 'No active member found.'}
                        </li>
                    )}
                    {hits.map((hit) => (
                        <li key={hit.id}>
                            <button
                                type="button"
                                onClick={() => {
                                    onPick(hit);
                                    setTerm('');
                                    setHits([]);
                                }}
                                className="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-accent"
                            >
                                <PersonAvatar
                                    name={hit.full_name}
                                    photoUrl={hit.photo_url}
                                    className="size-9"
                                />
                                <span className="flex-1">
                                    <span className="block font-medium">
                                        {hit.full_name}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {hit.member_number}
                                        {hit.phones.length
                                            ? ` · ${hit.phones.join(' · ')}`
                                            : ''}
                                    </span>
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
