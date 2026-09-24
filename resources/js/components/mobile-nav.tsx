import { Link, router } from '@inertiajs/react';
import { Contact, Grip, House, Menu } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermission } from '@/hooks/use-permission';
import { directoryItems, platformItems, quickActions } from '@/lib/navigation';
import type { GatedNavItem } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as membersIndex } from '@/routes/members';

/**
 * Phone navigation (below the md breakpoint): a bottom bar with Home, Members, the app drawer and the sidebar.
 * The drawer holds Dashboard, the directories and quick "Add" actions; the sidebar holds Administration and the account menu.
 */
export function MobileNav() {
    const { can } = usePermission();
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { openMobile, setOpenMobile } = useSidebar();
    const [drawerOpen, setDrawerOpen] = useState(false);

    // The layout stays mounted between pages, so close both panels once a visit starts.
    useEffect(
        () =>
            router.on('start', () => {
                setDrawerOpen(false);
                setOpenMobile(false);
            }),
        [setOpenMobile],
    );

    const visible = (items: GatedNavItem[]) =>
        items.filter((item) => !item.permission || can(item.permission));
    const actions = visible(quickActions);
    const apps = visible([...platformItems, ...directoryItems]);

    return (
        <>
            <nav
                aria-label="Main"
                className="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur supports-[backdrop-filter]:bg-background/80 md:hidden"
            >
                <div className="grid grid-cols-4">
                    <BarLink
                        href={dashboard()}
                        icon={House}
                        label="Home"
                        active={isCurrentUrl(dashboard())}
                    />
                    {can('members.view') ? (
                        <BarLink
                            href={membersIndex()}
                            icon={Contact}
                            label="Members"
                            active={isCurrentOrParentUrl(membersIndex())}
                        />
                    ) : (
                        <span />
                    )}
                    <BarButton
                        icon={Grip}
                        label="Apps"
                        active={drawerOpen}
                        onClick={() => setDrawerOpen(true)}
                    />
                    <BarButton
                        icon={Menu}
                        label="Menu"
                        active={openMobile}
                        onClick={() => setOpenMobile(true)}
                    />
                </div>
            </nav>

            <Sheet open={drawerOpen} onOpenChange={setDrawerOpen}>
                <SheetContent
                    side="bottom"
                    className="max-h-[85vh] gap-0 overflow-y-auto rounded-t-2xl pb-[calc(1.5rem+env(safe-area-inset-bottom))] md:hidden"
                >
                    <div className="mx-auto mt-2 h-1.5 w-10 rounded-full bg-muted" />
                    <SheetHeader>
                        <SheetTitle>Apps</SheetTitle>
                        <SheetDescription className="sr-only">
                            Open a section of the app.
                        </SheetDescription>
                    </SheetHeader>

                    {actions.length > 0 && (
                        <DrawerSection title="Quick actions">
                            {actions.map((item) => (
                                <AppTile key={item.title} item={item} accent />
                            ))}
                        </DrawerSection>
                    )}

                    <DrawerSection title="Sections">
                        {apps.map((item) => (
                            <AppTile
                                key={item.title}
                                item={item}
                                active={isCurrentOrParentUrl(item.href)}
                            />
                        ))}
                    </DrawerSection>
                </SheetContent>
            </Sheet>
        </>
    );
}

const barItem =
    'flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium transition-colors';

function BarLink({
    href,
    icon: Icon,
    label,
    active,
}: {
    href: GatedNavItem['href'];
    icon: LucideIcon;
    label: string;
    active: boolean;
}) {
    return (
        <Link
            href={href}
            prefetch
            aria-current={active ? 'page' : undefined}
            className={cn(
                barItem,
                active ? 'text-primary' : 'text-muted-foreground',
            )}
        >
            <Icon className="size-5" />
            {label}
        </Link>
    );
}

function BarButton({
    icon: Icon,
    label,
    active,
    onClick,
}: {
    icon: LucideIcon;
    label: string;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-expanded={active}
            className={cn(
                barItem,
                active ? 'text-primary' : 'text-muted-foreground',
            )}
        >
            <Icon className="size-5" />
            {label}
        </button>
    );
}

function DrawerSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="px-4 pb-4">
            <h3 className="mb-2 text-xs font-medium text-muted-foreground">
                {title}
            </h3>
            <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
                {children}
            </div>
        </section>
    );
}

function AppTile({
    item,
    active = false,
    accent = false,
}: {
    item: GatedNavItem;
    active?: boolean;
    accent?: boolean;
}) {
    const Icon = item.icon;

    return (
        <Link
            href={item.href}
            prefetch
            className="group flex flex-col items-center gap-1.5 rounded-xl p-2 text-center text-xs font-medium"
        >
            <span
                className={cn(
                    'flex size-14 items-center justify-center rounded-2xl border transition-colors group-active:scale-95',
                    accent
                        ? 'border-primary/20 bg-primary text-primary-foreground'
                        : 'bg-muted/60',
                    active && 'border-primary ring-2 ring-primary/30',
                )}
            >
                {Icon && <Icon className="size-6" />}
            </span>
            <span className="leading-tight">{item.title}</span>
        </Link>
    );
}
