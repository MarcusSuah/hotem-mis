<?php

namespace App\Enums;

/**
 * Every permission the application checks. Seeders provision these and the
 * Super Admin role always receives all of them.
 */
enum Permission: string
{
    case DashboardView = 'dashboard.view';

    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';
    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';
    case PermissionsAssign = 'permissions.assign';
    case AuditLogsView = 'audit-logs.view';

    case RoomsView = 'rooms.view';
    case RoomsCreate = 'rooms.create';
    case RoomsUpdate = 'rooms.update';
    case RoomsDelete = 'rooms.delete';
    case RoomTypesManage = 'room-types.manage';
    case RatePlansManage = 'rate-plans.manage';
    case RoomBlocksManage = 'room-blocks.manage';

    case BookingsView = 'bookings.view';
    case BookingsCreate = 'bookings.create';
    case BookingsUpdate = 'bookings.update';
    case BookingsCancel = 'bookings.cancel';
    case BookingsAssignRoom = 'bookings.assign-room';
    case CheckinManage = 'checkin.manage';
    case CheckoutManage = 'checkout.manage';
    case NightAuditRun = 'night-audit.run';

    case GuestsView = 'guests.view';
    case GuestsCreate = 'guests.create';
    case GuestsUpdate = 'guests.update';
    case GuestsViewIdentity = 'guests.view-identity';

    case FoliosManage = 'folios.manage';
    case FoliosOverride = 'folios.override';
    case PaymentsView = 'payments.view';
    case PaymentsCreate = 'payments.create';
    case PaymentsRefund = 'payments.refund';
    case InvoicesView = 'invoices.view';
    case InvoicesCreate = 'invoices.create';
    case ExpensesManage = 'expenses.manage';
    case ExchangeRatesManage = 'exchange-rates.manage';

    case HousekeepingManage = 'housekeeping.manage';
    case HousekeepingPerform = 'housekeeping.perform';
    case MaintenanceManage = 'maintenance.manage';
    case MaintenancePerform = 'maintenance.perform';
    case MaintenanceRequest = 'maintenance.request';
    case InventoryManage = 'inventory.manage';
    case ServicesManage = 'services.manage';
    case OrdersManage = 'orders.manage';
    case StaffManage = 'staff.manage';

    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case SettingsManage = 'settings.manage';
    case WebsiteManage = 'website.manage';
    case ReviewsModerate = 'reviews.moderate';

    /**
     * Human readable group used to organise the permission matrix.
     */
    public function group(): string
    {
        return match (true) {
            $this === self::DashboardView => 'Dashboard',
            in_array($this, [self::UsersView, self::UsersCreate, self::UsersUpdate, self::UsersDelete, self::RolesView, self::RolesCreate, self::RolesUpdate, self::RolesDelete, self::PermissionsAssign, self::AuditLogsView], true) => 'Identity & Access',
            in_array($this, [self::RoomsView, self::RoomsCreate, self::RoomsUpdate, self::RoomsDelete, self::RoomTypesManage, self::RatePlansManage, self::RoomBlocksManage], true) => 'Rooms',
            in_array($this, [self::BookingsView, self::BookingsCreate, self::BookingsUpdate, self::BookingsCancel, self::BookingsAssignRoom, self::CheckinManage, self::CheckoutManage, self::NightAuditRun], true) => 'Reservations & Front Desk',
            in_array($this, [self::GuestsView, self::GuestsCreate, self::GuestsUpdate, self::GuestsViewIdentity], true) => 'Guests',
            in_array($this, [self::FoliosManage, self::FoliosOverride, self::PaymentsView, self::PaymentsCreate, self::PaymentsRefund, self::InvoicesView, self::InvoicesCreate, self::ExpensesManage, self::ExchangeRatesManage], true) => 'Finance',
            in_array($this, [self::ReportsView, self::ReportsExport], true) => 'Reports',
            in_array($this, [self::SettingsManage, self::WebsiteManage, self::ReviewsModerate], true) => 'Website & Settings',
            default => 'Operations',
        };
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }
}
