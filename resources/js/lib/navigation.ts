import {
    BadgeCheck,
    Banknote,
    BarChart3,
    Bell,
    Boxes,
    Briefcase,
    Building2,
    CalendarDays,
    CalendarRange,
    Church,
    ClipboardCheck,
    ClipboardList,
    Coins,
    Contact,
    Crown,
    FileText,
    Gavel,
    Gift,
    HandCoins,
    HeartHandshake,
    KeyRound,
    Landmark,
    LayoutGrid,
    ListChecks,
    Mail,
    MapPinned,
    Megaphone,
    MessageSquareText,
    Package,
    PieChart,
    PiggyBank,
    Presentation,
    Receipt,
    ScrollText,
    ShieldCheck,
    ShoppingCart,
    Signpost,
    Smartphone,
    Sprout,
    Tags,
    TrendingUp,
    UserPlus,
    UserRoundPlus,
    Users,
    Users2,
    UsersRound,
    Wallet,
    Wrench,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as auditIndex } from '@/routes/admin/audit';
import { edit as churchEdit } from '@/routes/admin/church';
import { index as neighbourhoodsIndex } from '@/routes/admin/neighbourhoods';
import { index as occupationsIndex } from '@/routes/admin/occupations';
import { index as permissionsIndex } from '@/routes/admin/permissions';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as serviceGroupsIndex } from '@/routes/admin/service-groups';
import { index as servicePositionsIndex } from '@/routes/admin/service-positions';
import { index as townsIndex } from '@/routes/admin/towns';
import { index as usersIndex } from '@/routes/admin/users';
import * as communication from '@/routes/communication';
import * as finance from '@/routes/finance';
import { index as membersIndex } from '@/routes/members';
import { create as adultCreate } from '@/routes/members/adult';
import * as ministry from '@/routes/ministry';
import {
    calendar as eventsCalendar,
    index as eventsIndex,
} from '@/routes/events';
import { overview as committeesOverview } from '@/routes/committees';
import { index as decisionsIndex } from '@/routes/decisions';
import { index as meetingsIndex } from '@/routes/meetings';
import * as operations from '@/routes/operations';
import { overview as newcomersIndex } from '@/routes/newcomers';
import { index as presbyteriesIndex } from '@/routes/presbyteries';
import * as reporting from '@/routes/reporting';
import * as resources from '@/routes/resources';
import { create as staffCreate, index as staffIndex } from '@/routes/staff';
import type { NavItem } from '@/types';

/** A menu entry, hidden from people who lack `permission`. */
export type GatedNavItem = NavItem & {
    permission?: string;
    /** Key into the shared `badges` counts, shown as a small number on the item. */
    badgeKey?: string;
};

export type NavSection = { title: string; items: GatedNavItem[] };

// Pages not built yet (config/modules.php) open for anyone who can view members.
const soon = (
    title: string,
    href: GatedNavItem['href'],
    icon: GatedNavItem['icon'],
): GatedNavItem => ({ title, href, icon, permission: 'members.view' });

export const platformItems: GatedNavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
];

/**
 * The app's modules. The desktop sidebar shows every section. On a phone they become the app drawer
 * (see MobileNav) and the sidebar keeps only System.
 */
export const moduleSections: NavSection[] = [
    {
        title: 'People',
        items: [
            {
                title: 'Members',
                href: membersIndex(),
                icon: Contact,
                permission: 'members.view',
            },
            {
                title: 'Newcomers',
                href: newcomersIndex(),
                icon: UserRoundPlus,
                permission: 'newcomers.view',
            },
            {
                title: 'Staff Directory',
                href: staffIndex(),
                icon: UsersRound,
                permission: 'staff.view',
            },
        ],
    },
    {
        title: 'Ministry',
        items: [
            {
                title: 'Committees',
                href: committeesOverview(),
                icon: Users2,
                permission: 'committees.view',
                badgeKey: 'committees',
            },
            {
                title: 'Groups',
                href: serviceGroupsIndex(),
                icon: Tags,
                permission: 'settings.manage',
            },
            soon('Pastoral Care', ministry.pastoralCare(), HeartHandshake),
            soon('Discipleship', ministry.discipleship(), Sprout),
        ],
    },
    {
        title: 'Operations',
        items: [
            {
                title: 'Events',
                href: eventsIndex(),
                icon: CalendarDays,
                permission: 'events.view',
            },
            {
                title: 'Calendar',
                href: eventsCalendar(),
                icon: CalendarRange,
                permission: 'events.view',
            },
            {
                title: 'Meetings',
                href: meetingsIndex(),
                icon: Presentation,
                permission: 'meetings.view',
            },
            {
                title: 'Decisions & Actions',
                href: decisionsIndex(),
                icon: Gavel,
                permission: 'meetings.view',
            },
            soon('Tasks', operations.tasks(), ListChecks),
            soon('Facilities', operations.facilities(), Building2),
        ],
    },
    {
        title: 'Finance',
        items: [
            soon('Giving', finance.giving(), Gift),
            soon('Offerings', finance.offerings(), HandCoins),
            soon('Tithes', finance.tithes(), Coins),
            soon('Pledges', finance.pledges(), ClipboardCheck),
            soon('Expenses', finance.expenses(), Receipt),
            soon('Budgets', finance.budgets(), Wallet),
            soon('Funds', finance.funds(), PiggyBank),
        ],
    },
    {
        title: 'Resources',
        items: [
            soon('Procurement', resources.procurement(), ShoppingCart),
            soon('Inventory', resources.inventory(), Boxes),
            soon('Assets', resources.assets(), Package),
            soon('Maintenance', resources.maintenance(), Wrench),
            soon('Documents', resources.documents(), FileText),
        ],
    },
    {
        title: 'Communication',
        items: [
            soon('Announcements', communication.announcements(), Megaphone),
            soon('SMS', communication.sms(), MessageSquareText),
            soon('Email', communication.email(), Mail),
            soon('Notifications', communication.notifications(), Bell),
            soon('Member Portal', communication.memberPortal(), Smartphone),
        ],
    },
    {
        title: 'Reporting',
        items: [
            soon('Membership Reports', reporting.membership(), TrendingUp),
            soon(
                'Membership Records',
                reporting.membershipRecords(),
                ScrollText,
            ),
            soon('Attendance', reporting.attendance(), BarChart3),
            soon('Finance Reports', reporting.finance(), Banknote),
            soon('Ministry Reports', reporting.ministry(), PieChart),
            soon('Leadership Reports', reporting.leadership(), Crown),
        ],
    },
];

export const systemSection: NavSection = {
    title: 'System',
    items: [
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
            title: 'Service Positions',
            href: servicePositionsIndex(),
            icon: BadgeCheck,
            permission: 'settings.manage',
        },
        {
            title: 'Occupations',
            href: occupationsIndex(),
            icon: Briefcase,
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
    ],
};

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
