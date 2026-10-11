import { History, LayoutDashboard, ShieldCheck, Users } from 'lucide-react';
import admin from '@/routes/admin';
import type { NavGroup } from '@/types';

/**
 * Back-office navigation. Each item lists the permissions that reveal it;
 * the server enforces the same permissions on every route.
 */
export function adminNavigation(): NavGroup[] {
    return [
        {
            label: 'Overview',
            items: [
                {
                    title: 'Dashboard',
                    href: admin.dashboard(),
                    icon: LayoutDashboard,
                    permissions: ['dashboard.view'],
                },
            ],
        },
        {
            label: 'Administration',
            items: [
                {
                    title: 'Users',
                    href: admin.users.index(),
                    icon: Users,
                    permissions: ['users.view'],
                },
                {
                    title: 'Roles & Permissions',
                    href: admin.roles.index(),
                    icon: ShieldCheck,
                    permissions: ['roles.view'],
                },
                {
                    title: 'Audit Log',
                    href: admin.auditLogs.index(),
                    icon: History,
                    permissions: ['audit-logs.view'],
                },
            ],
        },
    ];
}
