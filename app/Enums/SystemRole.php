<?php

namespace App\Enums;

/**
 * Default roles provisioned by the application. Additional roles can be
 * created through the administration interface.
 */
enum SystemRole: string
{
    case SuperAdmin = 'Super Admin';
    case HotelAdministrator = 'Hotel Administrator';
    case HotelManager = 'Hotel Manager';
    case Receptionist = 'Receptionist';
    case ReservationOfficer = 'Reservation Officer';
    case Accountant = 'Accountant';
    case HousekeepingStaff = 'Housekeeping Staff';
    case MaintenanceStaff = 'Maintenance Staff';
    case InventoryManager = 'Inventory Manager';
    case MarketingManager = 'Marketing Manager';
    case Guest = 'Guest';

    /**
     * Whether the role grants access to the staff back office.
     */
    public function isStaff(): bool
    {
        return $this !== self::Guest;
    }

    /**
     * Permissions granted to the role when it is provisioned.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            // Administrators hold every permission; what separates them from the
            // Super Admin is that they cannot manage Super Admin accounts or the role.
            self::SuperAdmin, self::HotelAdministrator => Permission::cases(),
            self::HotelManager => [
                Permission::DashboardView, Permission::UsersView, Permission::RolesView, Permission::AuditLogsView,
                Permission::RoomsView, Permission::RoomsCreate, Permission::RoomsUpdate, Permission::RoomTypesManage,
                Permission::RatePlansManage, Permission::RoomBlocksManage,
                Permission::BookingsView, Permission::BookingsCreate, Permission::BookingsUpdate, Permission::BookingsCancel,
                Permission::BookingsAssignRoom, Permission::CheckinManage, Permission::CheckoutManage, Permission::NightAuditRun,
                Permission::GuestsView, Permission::GuestsCreate, Permission::GuestsUpdate, Permission::GuestsViewIdentity,
                Permission::FoliosManage, Permission::PaymentsView, Permission::InvoicesView, Permission::ExpensesManage,
                Permission::HousekeepingManage, Permission::MaintenanceManage, Permission::MaintenanceRequest,
                Permission::InventoryManage, Permission::ServicesManage, Permission::OrdersManage, Permission::StaffManage,
                Permission::ReportsView, Permission::ReportsExport, Permission::WebsiteManage, Permission::ReviewsModerate,
            ],
            self::Receptionist => [
                Permission::DashboardView, Permission::RoomsView,
                Permission::BookingsView, Permission::BookingsCreate, Permission::BookingsUpdate, Permission::BookingsCancel,
                Permission::BookingsAssignRoom, Permission::CheckinManage, Permission::CheckoutManage,
                Permission::GuestsView, Permission::GuestsCreate, Permission::GuestsUpdate, Permission::GuestsViewIdentity,
                Permission::FoliosManage, Permission::PaymentsView, Permission::PaymentsCreate,
                Permission::InvoicesView, Permission::InvoicesCreate,
                Permission::MaintenanceRequest, Permission::OrdersManage,
            ],
            self::ReservationOfficer => [
                Permission::DashboardView, Permission::RoomsView,
                Permission::BookingsView, Permission::BookingsCreate, Permission::BookingsUpdate, Permission::BookingsCancel,
                Permission::BookingsAssignRoom, Permission::GuestsView, Permission::GuestsCreate, Permission::GuestsUpdate,
                Permission::PaymentsView, Permission::InvoicesView, Permission::ReportsView,
            ],
            self::Accountant => [
                Permission::DashboardView, Permission::BookingsView, Permission::GuestsView,
                Permission::FoliosManage, Permission::PaymentsView, Permission::PaymentsCreate, Permission::PaymentsRefund,
                Permission::InvoicesView, Permission::InvoicesCreate, Permission::ExpensesManage,
                Permission::ExchangeRatesManage, Permission::ReportsView, Permission::ReportsExport,
            ],
            self::HousekeepingStaff => [
                Permission::RoomsView, Permission::HousekeepingPerform, Permission::MaintenanceRequest,
            ],
            self::MaintenanceStaff => [
                Permission::RoomsView, Permission::MaintenancePerform, Permission::MaintenanceRequest,
            ],
            self::InventoryManager => [
                Permission::DashboardView, Permission::InventoryManage, Permission::ReportsView, Permission::ReportsExport,
            ],
            self::MarketingManager => [
                Permission::DashboardView, Permission::WebsiteManage, Permission::ReviewsModerate, Permission::ReportsView,
            ],
            self::Guest => [],
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
