<?php

/*
 * The parts of the church management system not built yet. Each page is served as a "coming soon" placeholder at
 * /{section}/{slug} (see routes/web.php); when a page is built, remove it here and give it its own route.
 * The menu (resources/js/lib/navigation.ts) links to them by route name.
 */
return [
    'ministry' => ['title' => 'Ministry', 'pages' => [
        'pastoral-care' => ['Pastoral Care', 'Visits, counselling and follow-up for members.'],
        'discipleship' => ['Discipleship', 'Classes, catechism and discipleship programmes.'],
    ]],
    'operations' => ['title' => 'Operations', 'pages' => [
        'events' => ['Events', 'Services, programmes and church activities.'],
        'calendar' => ['Calendar', 'The church calendar.'],
        'meetings' => ['Meetings', 'Session, committee and group meetings, with minutes.'],
        'decisions' => ['Decisions', 'Decisions taken at meetings, and who acts on them.'],
        'tasks' => ['Tasks', 'Work assigned to people, and its progress.'],
        'facilities' => ['Facilities', 'Church buildings, rooms and their bookings.'],
    ]],
    'finance' => ['title' => 'Finance', 'pages' => [
        'giving' => ['Giving', 'All gifts received, by member and by fund.'],
        'offerings' => ['Offerings', 'Offerings taken at services.'],
        'tithes' => ['Tithes', 'Tithes paid by members.'],
        'pledges' => ['Pledges', 'Pledges made, and what has been redeemed.'],
        'expenses' => ['Expenses', 'Money spent, and its approval.'],
        'budgets' => ['Budgets', 'Budgets, and spending against them.'],
        'funds' => ['Funds', 'The church funds and their balances.'],
    ]],
    'resources' => ['title' => 'Resources', 'pages' => [
        'procurement' => ['Procurement', 'Requests, quotations and purchases.'],
        'inventory' => ['Inventory', 'Stock and supplies.'],
        'assets' => ['Assets', 'Church property and equipment.'],
        'maintenance' => ['Maintenance', 'Repairs and upkeep of buildings and equipment.'],
        'documents' => ['Documents', 'Church documents and files.'],
    ]],
    'communication' => ['title' => 'Communication', 'pages' => [
        'announcements' => ['Announcements', 'Announcements for services and groups.'],
        'sms' => ['SMS', 'Text messages to members and groups.'],
        'email' => ['Email', 'Email to members and groups.'],
        'notifications' => ['Notifications', 'Alerts sent by the system.'],
        'member-portal' => ['Member Portal', 'Where members see and update their own details.'],
    ]],
    'reporting' => ['title' => 'Reporting', 'pages' => [
        'membership' => ['Membership Reports', 'Membership numbers and trends.'],
        'membership-records' => ['Membership Records', 'Transfers, communicant status and membership history.'],
        'attendance' => ['Attendance', 'Attendance at services and meetings.'],
        'finance' => ['Finance Reports', 'Income, spending and fund reports.'],
        'ministry' => ['Ministry Reports', 'Reports on groups, committees and pastoral work.'],
        'leadership' => ['Leadership Reports', 'Summaries for the Session and church leaders.'],
    ]],
];
