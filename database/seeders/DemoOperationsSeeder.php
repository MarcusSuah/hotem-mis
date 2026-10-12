<?php

namespace Database\Seeders;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\Currency;
use App\Enums\EmploymentType;
use App\Enums\InventoryMovementType;
use App\Enums\MaintenanceCategory;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\ServiceCategory;
use App\Enums\Settlement;
use App\Enums\SystemRole;
use App\Enums\TaskPriority;
use App\Models\AdditionalService;
use App\Models\Complaint;
use App\Models\Department;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\MaintenanceSchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Shift;
use App\Models\StaffProfile;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\PurchasingService;
use App\Services\Operations\HousekeepingService;
use App\Services\Operations\MaintenanceService;
use App\Services\Outlets\OutletService;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Development-only operations data: departments and staff, the menu and
 * bookable services, stock with suppliers, and today's operational work.
 * Everything that moves stock or money goes through the real services.
 */
class DemoOperationsSeeder extends Seeder
{
    public function run(
        InventoryService $inventory,
        PurchasingService $purchasing,
        HousekeepingService $housekeeping,
        MaintenanceService $maintenance,
        OutletService $outlets,
    ): void {
        if (MenuCategory::query()->exists()) {
            $this->command->info('Operations demo data already present; skipped.');

            return;
        }

        $today = CarbonImmutable::today();
        $manager = $this->demoUser(SystemRole::HotelManager);
        $stockist = $this->demoUser(SystemRole::InventoryManager);
        $receptionist = $this->demoUser(SystemRole::Receptionist);

        $this->seedStaff($today, $manager);
        $this->seedMenu();
        $this->seedServices();
        $this->seedStock($inventory, $purchasing, $stockist, $today);

        $housekeeping->generateStayoverTasks($today);
        $this->seedMaintenance($maintenance, $manager, $today);
        $this->seedOutletActivity($outlets, $receptionist);
        $this->seedComplaints($receptionist);

        $this->command->info('Operations demo data seeded.');
    }

    private function demoUser(SystemRole $role): User
    {
        return User::query()->where('email', Str::slug($role->value).'@hms.test')->firstOrFail();
    }

    private function seedStaff(CarbonImmutable $today, User $manager): void
    {
        $departments = collect([
            'Front Office' => 'Reception, reservations and concierge',
            'Housekeeping' => 'Rooms, laundry and public areas',
            'Engineering' => 'Maintenance and building services',
            'Food & Beverage' => 'Restaurant, bar and room service',
            'Finance' => 'Accounts and purchasing',
            'Management' => 'General management',
        ])->mapWithKeys(fn (string $description, string $name) => [$name => Department::query()->firstOrCreate(['name' => $name], ['description' => $description])]);

        $linked = [
            SystemRole::HotelManager->value => ['Management', 'General Manager'],
            SystemRole::Receptionist->value => ['Front Office', 'Receptionist'],
            SystemRole::ReservationOfficer->value => ['Front Office', 'Reservations Officer'],
            SystemRole::Accountant->value => ['Finance', 'Accountant'],
            SystemRole::HousekeepingStaff->value => ['Housekeeping', 'Room Attendant'],
            SystemRole::MaintenanceStaff->value => ['Engineering', 'Maintenance Technician'],
            SystemRole::InventoryManager->value => ['Finance', 'Stores Controller'],
        ];

        $number = 1;

        foreach ($linked as $role => [$department, $title]) {
            $user = $this->demoUser(SystemRole::from($role));
            [$first, $last] = array_pad(explode(' ', $user->name, 2), 2, 'Demo');

            StaffProfile::create([
                'user_id' => $user->id,
                'department_id' => $departments[$department]->id,
                'employee_number' => sprintf('EMP-%04d', $number++),
                'first_name' => $first,
                'last_name' => $last,
                'job_title' => $title,
                'employment_type' => EmploymentType::FullTime,
                'hire_date' => $today->subMonths(6 + $number)->toDateString(),
                'email' => $user->email,
            ]);
        }

        foreach (['Food & Beverage' => ['Waiter', 'Bartender', 'Chef'], 'Housekeeping' => ['Laundry Attendant']] as $department => $titles) {
            foreach ($titles as $title) {
                StaffProfile::factory()->create([
                    'department_id' => $departments[$department]->id,
                    'employee_number' => sprintf('EMP-%04d', $number++),
                    'job_title' => $title,
                ]);
            }
        }

        $weekStart = $today->startOfWeek();

        StaffProfile::query()->where('is_active', true)->get()->each(function (StaffProfile $profile, int $index) use ($weekStart, $manager): void {
            $early = $index % 2 === 0;

            foreach (range(0, 4) as $offset) {
                Shift::create([
                    'staff_profile_id' => $profile->id,
                    'shift_date' => $weekStart->addDays(($offset + $index) % 7)->toDateString(),
                    'starts_at' => $early ? '07:00' : '15:00',
                    'ends_at' => $early ? '15:00' : '23:00',
                    'role' => $profile->job_title,
                    'created_by' => $manager->id,
                ]);
            }
        });
    }

    private function seedMenu(): void
    {
        $menu = [
            'Breakfast' => [['Continental breakfast', 'Pastries, fresh fruit, juice and coffee', '12.00'], ['Liberian breakfast', 'Fried plantain, eggs and spiced sausage', '14.00']],
            'Mains' => [['Jollof rice with grilled chicken', 'Smoky tomato rice, charred chicken thigh', '18.00'], ['Palm butter with fish', 'Rich palm nut stew served with rice', '20.00'], ['Grilled red snapper', 'Whole fish, pepper sauce, fried plantain', '24.00'], ['Potato greens', 'Slow-cooked greens with beef and rice', '16.00']],
            'Light bites' => [['Club sandwich', 'Chicken, bacon, egg and fries', '13.00'], ['Pepper soup', 'Goat pepper soup, served hot', '11.00']],
            'Drinks' => [['Fresh ginger beer', 'House-made, lightly sparkling', '4.00'], ['Club Beer', 'Local lager, 600ml', '5.00'], ['Espresso', null, '3.00'], ['Mango juice', 'Freshly pressed', '5.00']],
        ];

        $order = 0;

        foreach ($menu as $categoryName => $items) {
            $category = MenuCategory::create(['name' => $categoryName, 'display_order' => $order++, 'is_active' => true]);

            foreach ($items as $position => [$name, $description, $price]) {
                MenuItem::create([
                    'menu_category_id' => $category->id,
                    'name' => $name,
                    'description' => $description,
                    'currency' => Currency::USD,
                    'price_minor' => Money::of($price, Currency::USD)->minor,
                    'is_available' => true,
                    'is_featured' => $position === 0,
                    'display_order' => $position,
                ]);
            }
        }
    }

    private function seedServices(): void
    {
        $services = [
            ['Airport transfer', ServiceCategory::Transport, 'Roberts International Airport pick-up or drop-off', '60.00', 'per trip'],
            ['Signature massage', ServiceCategory::Spa, 'Sixty minutes of full-body relaxation', '45.00', 'per person'],
            ['Laundry — wash & fold', ServiceCategory::Laundry, 'Returned the same day when collected before 10:00', '8.00', 'per bag'],
            ['Monrovia city tour', ServiceCategory::Events, 'Half-day guided tour including Providence Island', '35.00', 'per person'],
        ];

        foreach ($services as $order => [$name, $category, $summary, $price, $unit]) {
            AdditionalService::create([
                'name' => $name,
                'category' => $category,
                'short_description' => $summary,
                'currency' => Currency::USD,
                'price_minor' => Money::of($price, Currency::USD)->minor,
                'unit' => $unit,
                'is_active' => true,
                'is_featured' => $order < 2,
                'display_order' => $order,
            ]);
        }
    }

    private function seedStock(InventoryService $inventory, PurchasingService $purchasing, User $stockist, CarbonImmutable $today): void
    {
        $suppliers = collect([
            ['name' => 'Monrovia Linen Supply', 'contact_person' => 'Store manager', 'phone' => '+231770000101', 'email' => 'orders@linen.example'],
            ['name' => 'Bushrod Wholesale Foods', 'contact_person' => 'Sales desk', 'phone' => '+231770000202', 'email' => 'sales@bushrod.example'],
            ['name' => 'Capitol Hardware', 'contact_person' => 'Trade counter', 'phone' => '+231770000303', 'email' => 'trade@capitol.example'],
        ])->map(fn (array $supplier) => Supplier::create($supplier + ['is_active' => true]));

        $catalogue = [
            'Linen' => [0, [['LIN-TWL-BATH', 'Bath towel', 'each', '40', '120', '6.50'], ['LIN-SHT-KING', 'King bed sheet', 'each', '30', '80', '14.00']]],
            'Guest amenities' => [0, [['AMN-SOAP', 'Guest soap 40g', 'each', '200', '150', '0.35'], ['AMN-WATER', 'Bottled water 500ml', 'each', '150', '90', '0.40']]],
            'Food & beverage' => [1, [['FB-RICE-25', 'Rice 25kg bag', 'bag', '4', '3', '28.00'], ['FB-PALM-OIL', 'Palm oil', 'litre', '10', '24', '3.20']]],
            'Spare parts' => [2, [['SP-BULB-LED', 'LED bulb 9W', 'each', '20', '35', '2.10'], ['SP-AC-FILTER', 'AC filter', 'each', '8', '5', '9.75']]],
        ];

        $items = collect();

        foreach ($catalogue as $categoryName => [$supplierIndex, $rows]) {
            $category = InventoryCategory::create(['name' => $categoryName]);

            foreach ($rows as [$sku, $name, $unit, $reorder, $opening, $cost]) {
                $item = InventoryItem::create([
                    'inventory_category_id' => $category->id,
                    'preferred_supplier_id' => $suppliers[$supplierIndex]->id,
                    'sku' => $sku,
                    'name' => $name,
                    'unit' => $unit,
                    'reorder_level' => $reorder,
                    'currency' => Currency::USD,
                    'tracks_expiry' => $category->name === 'Food & beverage',
                    'is_active' => true,
                ]);

                $inventory->record($item, InventoryMovementType::Receipt, $opening, $stockist, Money::of($cost, Currency::USD)->minor, reference: 'Opening stock', expiresOn: $item->tracks_expiry ? $today->addDays(20) : null);
                $items[$sku] = $item;
            }
        }

        $inventory->record($items['AMN-SOAP'], InventoryMovementType::Issue, '60', $stockist, reference: 'Housekeeping floor 2');
        $inventory->record($items['FB-RICE-25'], InventoryMovementType::Issue, '1', $stockist, reference: 'Kitchen');

        $order = $purchasing->create($suppliers[0], [
            ['inventory_item_id' => $items['LIN-TWL-BATH']->id, 'quantity' => '60', 'unit_cost' => Money::of('6.25', Currency::USD)],
            ['inventory_item_id' => $items['LIN-SHT-KING']->id, 'quantity' => '20', 'unit_cost' => Money::of('13.50', Currency::USD)],
        ], $today->addDays(5), 'Quarterly linen top-up', $stockist);
        $purchasing->markOrdered($order, $stockist);
        $purchasing->receive($order, [$order->items()->orderBy('id')->firstOrFail()->id => ['quantity' => '60', 'expires_on' => null]], $stockist);

        $purchasing->create($suppliers[2], [
            ['inventory_item_id' => $items['SP-AC-FILTER']->id, 'quantity' => '12', 'unit_cost' => Money::of('9.50', Currency::USD)],
        ], $today->addDays(7), null, $stockist);
    }

    private function seedMaintenance(MaintenanceService $maintenance, User $manager, CarbonImmutable $today): void
    {
        $technician = $this->demoUser(SystemRole::MaintenanceStaff);
        $housekeeper = $this->demoUser(SystemRole::HousekeepingStaff);
        $rooms = Room::query()->orderBy('number')->get();

        $maintenance->create(['title' => 'Air conditioner dripping', 'category' => MaintenanceCategory::Hvac, 'priority' => TaskPriority::High, 'room_id' => $rooms->get(2)?->id], $housekeeper, $technician);
        $maintenance->create(['title' => 'Bathroom tiles cracked', 'category' => MaintenanceCategory::Damage, 'priority' => TaskPriority::Normal, 'room_id' => $rooms->last()?->id], $manager);
        $maintenance->create(['title' => 'Pool pump making noise', 'category' => MaintenanceCategory::Appliance, 'priority' => TaskPriority::Normal, 'location' => 'Pool plant room'], $manager, $technician);

        MaintenanceSchedule::create([
            'title' => 'Generator service',
            'location' => 'Generator house',
            'category' => MaintenanceCategory::Preventive,
            'priority' => TaskPriority::High,
            'frequency_days' => 30,
            'next_due_on' => $today->addDays(4)->toDateString(),
            'assigned_to' => $technician->id,
            'is_active' => true,
        ]);
    }

    private function seedOutletActivity(OutletService $outlets, User $receptionist): void
    {
        $items = MenuItem::query()->pluck('id', 'name');
        $inHouse = Reservation::query()->where('status', ReservationStatus::CheckedIn->value)->orderBy('id')->first();

        $outlets->createOrder(OrderType::Restaurant, [
            ['menu_item_id' => $items['Jollof rice with grilled chicken'], 'quantity' => 2],
            ['menu_item_id' => $items['Fresh ginger beer'], 'quantity' => 2],
        ], $receptionist, tableOrRoom: 'T4', customer: 'Table 4');

        $paid = $outlets->createOrder(OrderType::Bar, [['menu_item_id' => $items['Club Beer'], 'quantity' => 3]], $receptionist, tableOrRoom: 'Bar');
        $outlets->advance($paid, OrderStatus::Served);
        $outlets->settleOrder($paid, Settlement::PaidAtOutlet, $receptionist, PaymentMethod::Cash);

        if ($inHouse !== null) {
            $roomService = $outlets->createOrder(OrderType::RoomService, [['menu_item_id' => $items['Club sandwich'], 'quantity' => 1]], $receptionist, $inHouse);
            $outlets->advance($roomService, OrderStatus::Preparing);
        }
    }

    private function seedComplaints(User $receptionist): void
    {
        $recent = Reservation::query()
            ->whereIn('status', [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value])
            ->whereNotNull('guest_id')
            ->orderByDesc('check_in_date')
            ->limit(2)
            ->get();

        foreach ($recent as $index => $reservation) {
            Complaint::create([
                'reference' => 'CMP-'.Str::upper(Str::random(6)),
                'guest_id' => $reservation->guest_id,
                'reservation_id' => $reservation->id,
                'category' => $index === 0 ? ComplaintCategory::Noise : ComplaintCategory::Room,
                'severity' => $index === 0 ? TaskPriority::High : TaskPriority::Normal,
                'subject' => $index === 0 ? 'Noise from the corridor late at night' : 'Hot water slow to arrive',
                'description' => $index === 0 ? 'Guest reported voices in the corridor after midnight.' : 'Shower took several minutes to run hot in the morning.',
                'status' => $index === 0 ? ComplaintStatus::Open : ComplaintStatus::Resolved,
                'resolution' => $index === 0 ? null : 'Engineering bled the hot-water line; guest confirmed it was fixed.',
                'resolved_at' => $index === 0 ? null : now(),
                'created_by' => $receptionist->id,
            ]);
        }
    }
}
