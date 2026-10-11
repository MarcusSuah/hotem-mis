<?php

namespace Database\Seeders;

use App\Enums\CancellationPenalty;
use App\Enums\Currency;
use App\Enums\HousekeepingStatus;
use App\Models\Amenity;
use App\Models\CancellationPolicy;
use App\Models\ExchangeRate;
use App\Models\Property;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SeasonalRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Demonstration room inventory: amenities, policies, rate plans, room types,
 * rooms and an LRD exchange rate. Safe to rerun; existing records are kept.
 */
class HotelInventorySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $property = Property::default();
            $amenities = $this->amenities();
            $this->policiesAndPlans();

            ExchangeRate::query()->firstOrCreate(
                ['base_currency' => Currency::USD->value, 'quote_currency' => Currency::LRD->value, 'notes' => 'Initial demo rate'],
                ['rate' => '192.50000000', 'effective_at' => now()->subYear()->startOfDay()],
            );

            foreach ($this->roomTypes() as $index => $definition) {
                $type = RoomType::query()->firstOrCreate(['slug' => $definition['slug']], [
                    ...$definition['attributes'],
                    'property_id' => $property->id,
                    'name' => $definition['name'],
                    'currency' => Currency::USD,
                    'display_order' => $index,
                    'is_active' => true,
                ]);

                $type->amenities()->syncWithoutDetaching(
                    $amenities->only($definition['amenities'])->pluck('id')->all()
                );

                foreach ($definition['rooms'] as $number) {
                    Room::query()->firstOrCreate(['property_id' => $property->id, 'number' => (string) $number], [
                        'room_type_id' => $type->id,
                        'floor' => intdiv($number, 100),
                        'building' => 'Main',
                        'housekeeping_status' => HousekeepingStatus::Inspected,
                        'is_accessible' => $number % 100 === 1,
                        'accessibility_notes' => $number % 100 === 1 ? 'Roll-in shower, grab bars, wide doorway' : null,
                    ]);
                }

                if ($definition['slug'] !== 'classic-queen') {
                    SeasonalRate::query()->firstOrCreate(
                        ['room_type_id' => $type->id, 'name' => 'Festive season'],
                        [
                            'start_date' => now()->setDate((int) now()->format('Y'), 12, 20)->toDateString(),
                            'end_date' => now()->setDate((int) now()->format('Y') + 1, 1, 2)->toDateString(),
                            'rate_minor' => (int) round($definition['attributes']['base_rate_minor'] * 1.25),
                            'priority' => 5,
                        ],
                    );
                }
            }
        });
    }

    /**
     * @return Collection<string, Amenity>
     */
    private function amenities()
    {
        $definitions = [
            ['Free Wi-Fi', 'wifi', 'room'], ['Air conditioning', 'air-vent', 'room'], ['Smart TV', 'tv', 'room'],
            ['Minibar', 'wine', 'room'], ['In-room safe', 'lock', 'room'], ['Rain shower', 'shower-head', 'room'],
            ['Ocean view', 'waves', 'room'], ['Work desk', 'briefcase', 'room'], ['Espresso machine', 'coffee', 'room'],
            ['Bathtub', 'bath', 'room'], ['Private balcony', 'sun', 'room'], ['Living area', 'sofa', 'room'],
            ['Outdoor pool', 'waves-ladder', 'hotel'], ['Spa', 'flower-2', 'hotel'], ['Fitness centre', 'dumbbell', 'hotel'],
            ['Restaurant', 'utensils', 'hotel'], ['Airport transfer', 'plane', 'hotel'], ['24-hour front desk', 'concierge-bell', 'hotel'],
        ];

        foreach ($definitions as $order => [$name, $icon, $category]) {
            Amenity::query()->firstOrCreate(['name' => $name], ['icon' => $icon, 'category' => $category, 'display_order' => $order]);
        }

        return Amenity::query()->get()->keyBy('name');
    }

    private function policiesAndPlans(): void
    {
        $flexible = CancellationPolicy::query()->firstOrCreate(['name' => 'Flexible'], [
            'description' => 'Free cancellation until 48 hours before arrival. Later cancellations are charged the first night.',
            'free_cancellation_hours' => 48,
            'penalty_type' => CancellationPenalty::FirstNight,
            'penalty_percent' => '0',
            'is_default' => true,
        ]);

        $nonRefundable = CancellationPolicy::query()->firstOrCreate(['name' => 'Non-refundable'], [
            'description' => 'The full stay is charged if the reservation is cancelled.',
            'free_cancellation_hours' => 0,
            'penalty_type' => CancellationPenalty::FullStay,
            'penalty_percent' => '0',
        ]);

        CancellationPolicy::query()->firstOrCreate(['name' => 'Moderate'], [
            'description' => 'Free cancellation until 7 days before arrival; 50% of the stay afterwards.',
            'free_cancellation_hours' => 168,
            'penalty_type' => CancellationPenalty::Percentage,
            'penalty_percent' => '50',
        ]);

        RatePlan::query()->firstOrCreate(['code' => 'BAR'], [
            'name' => 'Best Available Rate',
            'description' => 'Our flexible standard rate.',
            'cancellation_policy_id' => $flexible->id,
            'is_default' => true,
            'display_order' => 0,
        ]);

        RatePlan::query()->firstOrCreate(['code' => 'BB'], [
            'name' => 'Bed & Breakfast',
            'description' => 'Includes a full breakfast for every guest.',
            'cancellation_policy_id' => $flexible->id,
            'adjustment_percent' => '12',
            'includes_breakfast' => true,
            'display_order' => 1,
        ]);

        RatePlan::query()->firstOrCreate(['code' => 'SAVER'], [
            'name' => 'Advance Saver',
            'description' => 'Pay in full, save 15%.',
            'cancellation_policy_id' => $nonRefundable->id,
            'is_refundable' => false,
            'adjustment_percent' => '-15',
            'min_nights' => 2,
            'deposit_percent' => '100',
            'display_order' => 2,
        ]);
    }

    /**
     * @return list<array{name: string, slug: string, attributes: array<string, mixed>, amenities: list<string>, rooms: list<int>}>
     */
    private function roomTypes(): array
    {
        $roomAmenities = ['Free Wi-Fi', 'Air conditioning', 'Smart TV', 'In-room safe', 'Rain shower', 'Work desk'];

        return [
            [
                'name' => 'Classic Queen',
                'slug' => 'classic-queen',
                'attributes' => [
                    'short_description' => 'A calm, well-appointed retreat with a plush queen bed.',
                    'description' => 'Warm wood, crisp linens and everything you need for a restful stay in the heart of Monrovia.',
                    'max_occupancy' => 2, 'max_adults' => 2, 'max_children' => 1, 'included_adults' => 2,
                    'bed_type' => 'Queen', 'bed_count' => 1, 'size_sqm' => '24.0', 'bathroom_details' => 'Rain shower',
                    'base_rate_minor' => 9500, 'weekend_rate_minor' => 11000, 'extra_adult_minor' => 2500, 'extra_child_minor' => 1200,
                ],
                'amenities' => $roomAmenities,
                'rooms' => [101, 102, 103, 104, 105, 106, 107, 108],
            ],
            [
                'name' => 'Deluxe King',
                'slug' => 'deluxe-king',
                'attributes' => [
                    'short_description' => 'Generous space, a king bed and a marble bathroom with soaking tub.',
                    'description' => 'Designed for unhurried stays with a lounge chair by the window and an espresso machine.',
                    'max_occupancy' => 3, 'max_adults' => 2, 'max_children' => 1, 'included_adults' => 2,
                    'bed_type' => 'King', 'bed_count' => 1, 'size_sqm' => '32.0', 'bathroom_details' => 'Bathtub and rain shower',
                    'base_rate_minor' => 14500, 'weekend_rate_minor' => 16500, 'extra_adult_minor' => 3000, 'extra_child_minor' => 1500,
                    'is_featured' => true,
                ],
                'amenities' => [...$roomAmenities, 'Minibar', 'Espresso machine', 'Bathtub'],
                'rooms' => [201, 202, 203, 204, 205, 206, 207, 208],
            ],
            [
                'name' => 'Ocean View Twin',
                'slug' => 'ocean-view-twin',
                'attributes' => [
                    'short_description' => 'Two double beds facing the Atlantic — ideal for families and friends.',
                    'description' => 'Floor-to-ceiling windows frame the ocean; perfect for families with a sofa that converts for a child.',
                    'max_occupancy' => 4, 'max_adults' => 3, 'max_children' => 2, 'included_adults' => 2,
                    'bed_type' => 'Double', 'bed_count' => 2, 'size_sqm' => '36.0', 'bathroom_details' => 'Rain shower',
                    'base_rate_minor' => 16500, 'weekend_rate_minor' => 18500, 'extra_adult_minor' => 3500, 'extra_child_minor' => 1500,
                    'is_featured' => true,
                ],
                'amenities' => [...$roomAmenities, 'Ocean view', 'Minibar'],
                'rooms' => [301, 302, 303, 304, 305, 306],
            ],
            [
                'name' => 'Executive Suite',
                'slug' => 'executive-suite',
                'attributes' => [
                    'short_description' => 'A separate living room, private balcony and panoramic ocean views.',
                    'description' => 'Our signature suite with a dining nook, walk-in wardrobe and a balcony made for sunsets.',
                    'max_occupancy' => 4, 'max_adults' => 2, 'max_children' => 2, 'included_adults' => 2,
                    'bed_type' => 'King', 'bed_count' => 1, 'size_sqm' => '64.0', 'bathroom_details' => 'Freestanding tub, double vanity',
                    'base_rate_minor' => 29500, 'weekend_rate_minor' => 32500, 'extra_adult_minor' => 5000, 'extra_child_minor' => 2500,
                    'is_featured' => true,
                ],
                'amenities' => [...$roomAmenities, 'Ocean view', 'Minibar', 'Espresso machine', 'Bathtub', 'Private balcony', 'Living area'],
                'rooms' => [401, 402, 403, 404],
            ],
        ];
    }
}
