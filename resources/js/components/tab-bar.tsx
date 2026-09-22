/** A row of tabs with an optional count on each. The caller shows the panel that matches `value`. */
export function TabBar<T extends string>({
    tabs,
    value,
    onChange,
    label,
}: {
    tabs: { key: T; label: string; count?: number }[];
    value: T;
    onChange: (key: T) => void;
    label: string;
}) {
    return (
        <div
            role="tablist"
            aria-label={label}
            className="flex gap-1 overflow-x-auto border-b"
        >
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    type="button"
                    role="tab"
                    aria-selected={value === tab.key}
                    onClick={() => onChange(tab.key)}
                    className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors ${value === tab.key ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
                >
                    {tab.label}
                    {tab.count !== undefined && (
                        <span className="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs tabular-nums">
                            {tab.count}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}
