import { Link, usePage } from '@inertiajs/react';
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
    isVisible,
    moduleSections,
    platformItems,
    systemSection,
} from '@/lib/navigation';
import type { GatedNavItem } from '@/lib/navigation';
import { dashboard } from '@/routes';

export function AppSidebar() {
    const { can } = usePermission();
    // On a phone, Dashboard and the modules live in the app drawer (MobileNav); the sidebar keeps System.
    const { isMobile } = useSidebar();
    const badges = (usePage().props.badges ?? {}) as Record<string, number>;
    const visible = (items: GatedNavItem[]) =>
        items.filter((item) => isVisible(item, can, badges));

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
                        {moduleSections.map((section) => (
                            <NavMain
                                key={section.title}
                                items={visible(section.items)}
                                label={section.title}
                                collapsible
                            />
                        ))}
                    </>
                )}
                <NavMain
                    items={visible(systemSection.items)}
                    label={systemSection.title}
                    collapsible={!isMobile}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
