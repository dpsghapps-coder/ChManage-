import { Check, Monitor, Moon, Sun } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const options: { value: Appearance; icon: LucideIcon; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'system', icon: Monitor, label: 'System' },
];

/** Top-bar shortcut to the same Light / Dark / System choice as Settings → Appearance. */
export function ThemeToggle() {
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const Current = resolvedAppearance === 'dark' ? Moon : Sun;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-9"
                    aria-label="Change theme"
                    title="Change theme"
                >
                    <Current className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {options.map(({ value, icon: Icon, label }) => (
                    <DropdownMenuItem
                        key={value}
                        onSelect={() => updateAppearance(value)}
                    >
                        <Icon className="size-4" />
                        {label}
                        {appearance === value && (
                            <Check className="ml-auto size-4" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
