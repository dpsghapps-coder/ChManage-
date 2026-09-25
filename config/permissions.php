<?php

/*
|--------------------------------------------------------------------------
| Permission registry
|--------------------------------------------------------------------------
| The single source of truth for what permissions exist. A permission's name is "{module}.{action}",
| e.g. members.view. Run `php artisan permissions:sync` (also run by the seeder) to load them into the
| `permissions` table. Roles are then built in the UI by combining these permissions; new features add
| entries here and reference them with the `permission:` route middleware or `$user->can('members.view')`.
*/

return [

    'modules' => [
        'members' => [
            'label' => 'Members',
            'actions' => [
                'view' => 'View the members register and member profiles',
                'create' => 'Register new members',
                'edit' => 'Edit member details',
                'delete' => 'Delete members',
                'export' => 'Export member lists',
            ],
        ],
        'contributions' => [
            'label' => 'Tithes & Offerings',
            'actions' => [
                'view' => 'View tithes, offerings and harvest contributions',
                'create' => 'Record contributions',
                'edit' => 'Edit contributions',
                'delete' => 'Delete contributions',
                'export' => 'Export contribution data',
            ],
        ],
        'income' => [
            'label' => 'Church Income',
            'actions' => [
                'view' => 'View income entries',
                'create' => 'Record income',
                'edit' => 'Edit income entries',
                'delete' => 'Delete income entries',
            ],
        ],
        'expenses' => [
            'label' => 'Expenses',
            'actions' => [
                'view' => 'View expenses',
                'create' => 'Record expenses',
                'edit' => 'Edit expenses',
                'delete' => 'Delete expenses',
            ],
        ],
        'budgets' => [
            'label' => 'Budgets',
            'actions' => [
                'view' => 'View budgets',
                'manage' => 'Create and change budgets',
            ],
        ],
        'banking' => [
            'label' => 'Banking',
            'actions' => [
                'view' => 'View bank accounts and transactions',
                'manage' => 'Manage bank accounts and record transactions',
            ],
        ],
        'payroll' => [
            'label' => 'Payroll',
            'actions' => [
                'view' => 'View salary and allowance payments',
                'manage' => 'Record salary and allowance payments',
            ],
        ],
        'assets' => [
            'label' => 'Assets',
            'actions' => [
                'view' => 'View the asset register',
                'manage' => 'Add and change assets',
            ],
        ],
        'attendance' => [
            'label' => 'Attendance',
            'actions' => [
                'view' => 'View attendance counts',
                'record' => 'Record attendance counts',
            ],
        ],
        'visitors' => [
            'label' => 'Visitors',
            'actions' => [
                'view' => 'View the visitors book',
                'record' => 'Record visitors',
            ],
        ],
        'committees' => [
            'label' => 'Committee Members',
            'actions' => [
                'view' => 'View who serves on each committee, and the terms',
                'manage' => 'Add, renew and end committee terms, and set the term rules',
            ],
        ],
        'meetings' => [
            'label' => 'Meetings, Decisions & Actions',
            'actions' => [
                'view' => 'View meetings, minutes, decisions and actions',
                'manage' => 'Record meetings, minutes, decisions and actions, and update their status',
            ],
        ],
        'events' => [
            'label' => 'Events & Calendar',
            'actions' => [
                'view' => 'View the events and the calendar',
                'manage' => 'Create, change, cancel and delete events',
            ],
        ],
        'newcomers' => [
            'label' => 'Visitors & Newcomers',
            'actions' => [
                'view' => 'View visitors, newcomers and catechumens',
                'manage' => 'Register and update them, assign counsellors and record lessons',
                'promote' => 'Make a newcomer a member',
            ],
        ],
        'sms' => [
            'label' => 'SMS',
            'actions' => [
                'view' => 'View sent messages',
                'send' => 'Send SMS to members and groups',
            ],
        ],
        'reports' => [
            'label' => 'Reports',
            'actions' => [
                'view' => 'View reports',
            ],
        ],
        'staff' => [
            'label' => 'Staff Directory',
            'actions' => [
                'view' => 'View the staff directory',
                'create' => 'Add staff members',
                'edit' => 'Edit staff details',
                'delete' => 'Delete staff records',
                'transfer' => 'Transfer staff between departments, positions or stations',
                'link_user' => 'Link or unlink a staff member and a user account',
            ],
        ],
        'users' => [
            'label' => 'User Accounts',
            'actions' => [
                'view' => 'View user accounts',
                'create' => 'Create user accounts',
                'edit' => 'Edit user accounts, change role, activate or deactivate',
                'reset_password' => 'Issue a temporary password for a user',
            ],
        ],
        'roles' => [
            'label' => 'Roles',
            'actions' => [
                'view' => 'View roles and their permissions',
                'manage' => 'Create, edit and delete roles and choose their permissions',
            ],
        ],
        'permissions' => [
            'label' => 'Permissions',
            'actions' => [
                'view' => 'View the permission list',
            ],
        ],
        'audit' => [
            'label' => 'Audit Log',
            'actions' => [
                'view' => 'View the security audit log',
            ],
        ],
        'settings' => [
            'label' => 'System Settings',
            'actions' => [
                'manage' => 'Change system settings and lookup lists',
            ],
        ],
    ],

    /*
    | Default permission sets for the seeded roles. Applied only to a role that has no permissions yet, so
    | changes made in the UI are never overwritten. "*" means every permission. The `admin` role always
    | bypasses permission checks regardless of what is listed here.
    */
    'default_roles' => [
        'admin' => ['*'],
        'admin_super_user' => [
            'members.*', 'contributions.*', 'income.*', 'expenses.*', 'budgets.*', 'banking.*', 'payroll.view',
            'assets.*', 'attendance.*', 'visitors.*', 'newcomers.*', 'events.*', 'meetings.*', 'committees.*', 'sms.*', 'reports.view', 'staff.*',
            'users.view', 'roles.view', 'permissions.view',
        ],
        'admin_user' => [
            'members.view', 'members.create', 'members.edit', 'attendance.*', 'visitors.*', 'newcomers.view', 'newcomers.manage', 'events.view', 'events.manage', 'meetings.view', 'committees.view', 'sms.*',
            'staff.view', 'assets.view', 'reports.view',
        ],
        'admin_data_entry' => [
            'members.view', 'members.create', 'members.edit', 'attendance.record', 'visitors.record',
        ],
        'finance_admin' => [
            'contributions.*', 'income.*', 'expenses.*', 'budgets.*', 'banking.*', 'payroll.*', 'reports.view',
            'members.view',
        ],
        'finance_head' => [
            'contributions.view', 'contributions.create', 'contributions.edit', 'contributions.export',
            'income.*', 'expenses.*', 'budgets.view', 'banking.view', 'payroll.view', 'reports.view', 'members.view',
        ],
        'finance_user' => [
            'contributions.view', 'contributions.create', 'income.view', 'income.create', 'expenses.view',
            'expenses.create', 'reports.view', 'members.view',
        ],
        'finance_data_entry' => [
            'contributions.view', 'contributions.create', 'income.view', 'income.create',
            'expenses.view', 'expenses.create',
        ],
        'visitor_entry' => ['visitors.view', 'visitors.record'],
        // Roles added with the permission system. Adjust in the UI: Administration > Roles.
        'clerk' => [
            'members.view', 'members.create', 'members.edit', 'contributions.view', 'contributions.create',
            'attendance.record', 'visitors.record',
        ],
        'agent' => [
            'members.view', 'contributions.view', 'contributions.create', 'visitors.record',
        ],
    ],

    // Roles created by the seeder that do not exist yet: slug => [name, description].
    'new_roles' => [
        'clerk' => ['Clerk', 'Registers members and records contributions, attendance and visitors.'],
        'agent' => ['Agent', 'Field agent: looks up members, records contributions and visitors.'],
    ],
];
