import {
    Church,
    ClipboardList,
    Contact,
    KeyRound,
    Landmark,
    LayoutGrid,
    MapPinned,
    Signpost,
    ShieldCheck,
    UserPlus,
    Users,
    UsersRound,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as auditIndex } from '@/routes/admin/audit';
import { edit as churchEdit } from '@/routes/admin/church';
import { index as permissionsIndex } from '@/routes/admin/permissions';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as neighbourhoodsIndex } from '@/routes/admin/neighbourhoods';
import { index as townsIndex } from '@/routes/admin/towns';
import { index as usersIndex } from '@/routes/admin/users';
import { index as membersIndex } from '@/routes/members';
import { create as adultCreate } from '@/routes/members/adult';
import { index as presbyteriesIndex } from '@/routes/presbyteries';
import { create as staffCreate, index as staffIndex } from '@/routes/staff';
import type { NavItem } from '@/types';

/** A menu entry, hidden from people who lack `permission`. */
export type GatedNavItem = NavItem & { permission?: string };

/**
 * The app's sections. The desktop sidebar shows all three groups. On a phone the first two become the
 * app drawer (see MobileNav) and the sidebar keeps only Administration.
 */
export const platformItems: GatedNavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
];

export const directoryItems: GatedNavItem[] = [
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

export const administrationItems: GatedNavItem[] = [
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
    // The page itself stays open to everyone signed in (read-only for those who cannot manage settings).
    {
        title: 'PCG Presbyteries',
        href: presbyteriesIndex(),
        icon: Landmark,
        permission: 'settings.manage',
    },
    {
        title: 'Neighbourhoods',
        href: neighbourhoodsIndex(),
        icon: Signpost,
        permission: 'settings.manage',
    },
    {
        title: 'Ghana Towns',
        href: townsIndex(),
        icon: MapPinned,
        permission: 'settings.manage',
    },
];

/** Shortcuts shown at the top of the phone app drawer. */
export const quickActions: GatedNavItem[] = [
    {
        title: 'Add Member',
        href: adultCreate(),
        icon: UserPlus,
        permission: 'members.create',
    },
    {
        title: 'Add Staff',
        href: staffCreate(),
        icon: UserPlus,
        permission: 'staff.create',
    },
];
