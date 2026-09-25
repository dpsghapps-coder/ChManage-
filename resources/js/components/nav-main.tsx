import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

/**
 * A labelled group of menu links. A `collapsible` group opens by itself when it holds the current page; while the
 * sidebar is shrunk to icons every link shows, since there is no label to open it with.
 */
export function NavMain({
    items,
    label = 'Platform',
    collapsible = false,
}: {
    items: NavItem[];
    label?: string;
    collapsible?: boolean;
}) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { state, isMobile } = useSidebar();
    const holdsCurrent = items.some((item) => isCurrentOrParentUrl(item.href));
    const [toggled, setToggled] = useState<boolean | null>(null);

    if (items.length === 0) {
        return null;
    }

    const menu = (
        <SidebarMenu>
            {items.map((item) => (
                <SidebarMenuItem key={item.title}>
                    <SidebarMenuButton
                        asChild
                        isActive={isCurrentUrl(item.href)}
                        tooltip={{ children: item.title }}
                    >
                        <Link href={item.href} prefetch>
                            {item.icon && <item.icon />}
                            <span>{item.title}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            ))}
        </SidebarMenu>
    );

    if (!collapsible) {
        return (
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel>{label}</SidebarGroupLabel>
                {menu}
            </SidebarGroup>
        );
    }

    const iconsOnly = state === 'collapsed' && !isMobile;
    const open = iconsOnly || (toggled ?? holdsCurrent);

    return (
        <Collapsible open={open} onOpenChange={setToggled} asChild>
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel asChild>
                    <CollapsibleTrigger className="group/label w-full hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                        {label}
                        <ChevronRight className="ml-auto transition-transform group-data-[state=open]/label:rotate-90" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent>{menu}</CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}
