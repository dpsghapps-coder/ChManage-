import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { usePermission } from '@/hooks/use-permission';
import {
    administrationItems,
    directoryItems,
    platformItems,
} from '@/lib/navigation';
import type { GatedNavItem } from '@/lib/navigation';
import { dashboard } from '@/routes';

export function AppSidebar() {
    const { can } = usePermission();
    // On a phone, Dashboard and the directories live in the app drawer (MobileNav); the sidebar keeps the rest.
    const { isMobile } = useSidebar();
    const visible = (items: GatedNavItem[]) =>
        items.filter((item) => !item.permission || can(item.permission));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {!isMobile && (
                    <>
                        <NavMain
                            items={visible(platformItems)}
                            label="Platform"
                        />
                        <NavMain
                            items={visible(directoryItems)}
                            label="Directory"
                        />
                    </>
                )}
                <NavMain
                    items={visible(administrationItems)}
                    label="Administration"
                />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
