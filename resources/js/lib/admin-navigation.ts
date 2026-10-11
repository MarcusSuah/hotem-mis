import {
    ArrowRightLeft,
    Banknote,
    BedDouble,
    ConciergeBell,
    FileText,
    MoonStar,
    UsersRound,
    CalendarDays,
    CalendarRange,
    DoorOpen,
    History,
    LayoutDashboard,
    ShieldCheck,
    Tags,
    Users,
} from 'lucide-react';
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
            label: 'Front Office',
            items: [
                {
                    title: 'Front Desk',
                    href: admin.frontDesk.index(),
                    icon: ConciergeBell,
                    permissions: ['checkin.manage', 'checkout.manage'],
                },
                {
                    title: 'Reservations',
                    href: admin.reservations.index(),
                    icon: CalendarDays,
                    permissions: ['bookings.view'],
                },
                {
                    title: 'Availability',
                    href: admin.availability.index(),
                    icon: CalendarRange,
                    permissions: ['bookings.view'],
                },
                {
                    title: 'Guests',
                    href: admin.guests.index(),
                    icon: UsersRound,
                    permissions: ['guests.view'],
                },
                {
                    title: 'Night Audit',
                    href: admin.nightAudit.index(),
                    icon: MoonStar,
                    permissions: ['night-audit.run'],
                },
            ],
        },
        {
            label: 'Finance',
            items: [
                {
                    title: 'Payments',
                    href: admin.payments.index(),
                    icon: Banknote,
                    permissions: ['payments.view'],
                },
                {
                    title: 'Invoices',
                    href: admin.invoices.index(),
                    icon: FileText,
                    permissions: ['invoices.view'],
                },
                {
                    title: 'Exchange Rates',
                    href: admin.exchangeRates.index(),
                    icon: ArrowRightLeft,
                    permissions: ['exchange-rates.manage', 'reports.view'],
                },
            ],
        },
        {
            label: 'Rooms',
            items: [
                {
                    title: 'Rooms',
                    href: admin.rooms.index(),
                    icon: DoorOpen,
                    permissions: ['rooms.view'],
                },
                {
                    title: 'Room Types',
                    href: admin.roomTypes.index(),
                    icon: BedDouble,
                    permissions: ['rooms.view'],
                },
                {
                    title: 'Rates & Policies',
                    href: admin.rates.index(),
                    icon: Tags,
                    permissions: ['rooms.view'],
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
