import { Link } from '@inertiajs/react';
import {
    Church,
    ClipboardList,
    Contact,
    KeyRound,
    LayoutGrid,
    ShieldCheck,
    Users,
    UsersRound,
} from 'lucide-react';
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
} from '@/components/ui/sidebar';
import { usePermission } from '@/hooks/use-permission';
import { dashboard } from '@/routes';
import { index as auditIndex } from '@/routes/admin/audit';
import { edit as churchEdit } from '@/routes/admin/church';
import { index as permissionsIndex } from '@/routes/admin/permissions';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as usersIndex } from '@/routes/admin/users';
import { index as membersIndex } from '@/routes/members';
import { index as staffIndex } from '@/routes/staff';
import type { NavItem } from '@/types';

type GatedNavItem = NavItem & { permission?: string };

const platformItems: GatedNavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
];

const directoryItems: GatedNavItem[] = [
    {
        title: 'Members',
        href: membersIndex(),
        icon: Contact,
        permission: 'members.view',
    },
    {
        title: 'Staff Directory',
        href: staffIndex(),
        icon: UsersRound,
        permission: 'staff.view',
    },
];

const administrationItems: GatedNavItem[] = [
    {
        title: 'Users',
        href: usersIndex(),
        icon: Users,
        permission: 'users.view',
    },
    {
        title: 'Roles',
        href: rolesIndex(),
        icon: ShieldCheck,
        permission: 'roles.view',
    },
    {
        title: 'Permissions',
        href: permissionsIndex(),
        icon: KeyRound,
        permission: 'permissions.view',
    },
    {
        title: 'Audit Log',
        href: auditIndex(),
        icon: ClipboardList,
        permission: 'audit.view',
    },
    {
        title: 'Church Settings',
        href: churchEdit(),
        icon: Church,
        permission: 'settings.manage',
    },
];

export function AppSidebar() {
    const { can } = usePermission();
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
                <NavMain items={visible(platformItems)} label="Platform" />
                <NavMain items={visible(directoryItems)} label="Directory" />
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
