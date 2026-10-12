import {
    ArrowRightLeft,
    Webhook,
    Banknote,
    BarChart3,
    Boxes,
    Grid3x3,
    Globe,
    Images,
    Inbox,
    MessageSquareQuote,
    Settings2,
    BookOpen,
    ContactRound,
    MessageSquareWarning,
    Sparkles,
    Utensils,
    Wrench,
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
                    title: 'Complaints',
                    href: admin.complaints.index(),
                    icon: MessageSquareWarning,
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
                    title: 'Online Payments',
                    href: admin.gatewayEvents.index(),
                    icon: Webhook,
                    permissions: ['payments.view'],
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
            label: 'Reports',
            items: [
                {
                    title: 'Reports',
                    href: admin.reports.index(),
                    icon: BarChart3,
                    permissions: ['reports.view'],
                },
                {
                    title: 'Room Usage',
                    href: admin.reports.heatmap(),
                    icon: Grid3x3,
                    permissions: ['reports.view'],
                },
            ],
        },
        {
            label: 'Operations',
            items: [
                {
                    title: 'Housekeeping',
                    href: admin.housekeeping.index(),
                    icon: Sparkles,
                    permissions: [
                        'housekeeping.manage',
                        'housekeeping.perform',
                    ],
                },
                {
                    title: 'Maintenance',
                    href: admin.maintenance.index(),
                    icon: Wrench,
                    permissions: [
                        'maintenance.manage',
                        'maintenance.perform',
                        'maintenance.request',
                    ],
                },
                {
                    title: 'Inventory',
                    href: admin.inventory.index(),
                    icon: Boxes,
                    permissions: ['inventory.manage'],
                },
            ],
        },
        {
            label: 'Outlets',
            items: [
                {
                    title: 'Restaurant & Services',
                    href: admin.outlets.index(),
                    icon: Utensils,
                    permissions: ['orders.manage'],
                },
                {
                    title: 'Menu & Services',
                    href: admin.catalog.index(),
                    icon: BookOpen,
                    permissions: ['services.manage'],
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
            label: 'Website',
            items: [
                {
                    title: 'Content',
                    href: admin.website.content.index(),
                    icon: Globe,
                    permissions: ['website.manage'],
                },
                {
                    title: 'Gallery',
                    href: admin.website.gallery.index(),
                    icon: Images,
                    permissions: ['website.manage'],
                },
                {
                    title: 'Reviews',
                    href: admin.website.reviews.index(),
                    icon: MessageSquareQuote,
                    permissions: ['reviews.moderate'],
                },
                {
                    title: 'Enquiries',
                    href: admin.website.enquiries.index(),
                    icon: Inbox,
                    permissions: ['website.manage'],
                },
                {
                    title: 'Website Settings',
                    href: admin.website.settings.edit(),
                    icon: Settings2,
                    permissions: ['website.manage'],
                },
            ],
        },
        {
            label: 'Administration',
            items: [
                {
                    title: 'Staff',
                    href: admin.staff.index(),
                    icon: ContactRound,
                    permissions: ['staff.manage'],
                },
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
