import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ClassBadge } from '@/components/class-badge';
import { PersonAvatar } from '@/components/person-avatar';
import { StatusBadge } from '@/components/staff-status';
import { Input } from '@/components/ui/input';
import { suggest } from '@/routes/members';

export type Suggestion = {
    key: string;
    name: string;
    member_number: string;
    phone: string | null;
    photo_url: string | null;
    category: 'adults' | 'junior_youth' | 'children';
    /** Age for adults, class for children and youth. */
    detail: string | null;
    status: string;
};

const heading: Record<Suggestion['category'], string> = {
    adults: 'Adults',
    junior_youth: 'Junior Youth',
    children: 'Children Service',
};

/**
 * The members search box with search-ahead: matches from every register appear as you type. Arrow keys and Enter
 * choose one; Enter with nothing highlighted runs the normal search.
 */
export function MemberSearchBox({
    value,
    onChange,
    onPick,
}: {
    value: string;
    onChange: (value: string) => void;
    onPick: (suggestion: Suggestion) => void;
}) {
    const [open, setOpen] = useState(false);
    const [results, setResults] = useState<Suggestion[]>([]);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(-1);
    const wrapper = useRef<HTMLDivElement>(null);
    const typed = value.trim();

    useEffect(() => {
        if (!open || typed.length < 2) {
            setResults([]);
            setLoading(false);

            return;
        }

        const controller = new AbortController();
        setLoading(true);

        const timer = setTimeout(async () => {
            try {
                const response = await fetch(
                    suggest({ query: { q: typed } }).url,
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );
                setResults(response.ok ? await response.json() : []);
                setActive(-1);
                setLoading(false);
            } catch {
                // Cancelled by a newer keystroke: leave the loading state to that request.
            }
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [typed, open]);

    // Clicking anywhere else closes the list.
    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (!wrapper.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    const pick = (suggestion: Suggestion) => {
        setOpen(false);
        setResults([]);
        onPick(suggestion);
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Escape') {
            setOpen(false);

            return;
        }

        if (!open || results.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((current) => (current + 1) % results.length);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((current) =>
                current <= 0 ? results.length - 1 : current - 1,
            );
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            pick(results[active]);
        }
    };

    const showList = open && typed.length >= 2;

    return (
        <div
            ref={wrapper}
            className="relative w-full sm:w-auto sm:min-w-56 sm:flex-1"
        >
            <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
            <Input
                value={value}
                onChange={(e) => {
                    onChange(e.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onKeyDown={onKeyDown}
                placeholder="Search name, member number or mobile"
                className="pl-9"
                aria-label="Search members"
                role="combobox"
                aria-expanded={showList}
                aria-autocomplete="list"
                autoComplete="off"
            />

            {showList && (
                <ul
                    role="listbox"
                    className="absolute z-30 mt-1 max-h-96 w-full overflow-auto rounded-md border bg-popover text-sm shadow-md"
                >
                    {results.length === 0 && (
                        <li className="px-3 py-3 text-muted-foreground">
                            {loading ? 'Searching…' : 'No members found.'}
                        </li>
                    )}
                    {results.map((item, i) => (
                        <li key={item.key} role="presentation">
                            {(i === 0 ||
                                results[i - 1].category !== item.category) && (
                                <div className="bg-muted/50 px-3 py-1 text-xs font-medium text-muted-foreground">
                                    {heading[item.category]}
                                </div>
                            )}
                            <div
                                role="option"
                                aria-selected={active === i}
                                // mousedown, not click: the input must not lose focus first.
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    pick(item);
                                }}
                                onMouseEnter={() => setActive(i)}
                                className={`flex cursor-pointer items-center gap-3 px-3 py-2 ${active === i ? 'bg-accent' : ''}`}
                            >
                                <PersonAvatar
                                    name={item.name}
                                    photoUrl={item.photo_url}
                                    className="size-9"
                                />
                                <div className="min-w-0 flex-1">
                                    <div className="truncate font-medium">
                                        {item.name}
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        {item.member_number}
                                        {item.phone ? ` · ${item.phone}` : ''}
                                    </div>
                                </div>
                                {item.category !== 'adults' && item.detail ? (
                                    <ClassBadge value={item.detail} />
                                ) : item.detail ? (
                                    <span className="text-xs text-muted-foreground">
                                        {item.detail}
                                    </span>
                                ) : null}
                                {item.status !== 'active' && (
                                    <StatusBadge status={item.status} />
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
