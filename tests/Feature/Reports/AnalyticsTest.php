<?php

use App\Enums\ChargeCategory;
use App\Enums\Currency;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Services\Billing\FolioService;
use App\Services\Reports\AnalyticsService;
use App\Services\Reports\ReportPeriod;
use App\Support\Money\Money;

beforeEach(function () {
    $this->travelTo('2026-11-10 12:00:00');
});

it('derives occupancy, ADR and RevPAR from sold nights', function () {
    [$roomA] = Room::factory()->count(2)->create();
    Reservation::factory()->checkedIn()->between('2026-11-08', '2026-11-10')->inRoom($roomA)->create();

    $totals = app(AnalyticsService::class)->summary(new ReportPeriod('2026-11-08', '2026-11-09'))['current'];

    expect($totals)
        ->available->toBe(4)
        ->sold->toBe(2)
        ->occupancy->toBe(50.0)
        ->and($totals['adr']->format())->toBe('USD 150.00')
        ->and($totals['revpar']->format())->toBe('USD 75.00')
        ->and($totals['revenue']->format())->toBe('USD 300.00');
});

it('does not count past nights of guests who never arrived as sold', function () {
    $room = Room::factory()->create();
    Reservation::factory()->confirmed()->between('2026-11-08', '2026-11-12')->inRoom($room)->create();

    $nights = app(AnalyticsService::class)->soldNights(new ReportPeriod('2026-11-08', '2026-11-11'));

    expect(array_column($nights, 'sold'))->toBe([0, 0, 1, 1]);
});

it('removes blocked rooms from the nights available to sell', function () {
    $room = Room::factory()->create();
    Room::factory()->create();
    RoomBlock::factory()->for($room)->create(['start_date' => '2026-11-09', 'end_date' => '2026-11-10']);

    expect(app(AnalyticsService::class)->availableNights(new ReportPeriod('2026-11-08', '2026-11-10')))
        ->toBe(['2026-11-08' => 2, '2026-11-09' => 1, '2026-11-10' => 2]);
});

it('converts foreign-currency folio revenue at the rate agreed on the reservation', function () {
    $reservation = Reservation::factory()->create(['currency' => Currency::LRD, 'exchange_rate' => '190.00000000']);
    $folios = app(FolioService::class);
    $folio = $folios->openFor($reservation);
    $folios->post($folio, ChargeCategory::Laundry, 'Laundry', Money::of('1900', Currency::LRD), serviceDate: now()->toImmutable());

    $revenue = app(AnalyticsService::class)->revenueByCategory(ReportPeriod::lastDays(1));

    expect($revenue)->toHaveCount(1)
        ->and($revenue[0]['label'])->toBe(ChargeCategory::Laundry->label())
        ->and($revenue[0]['amount']->format())->toBe('USD 10.00');
});

it('maps each room night to its use for the heatmap', function () {
    $booked = Room::factory()->create(['number' => '101']);
    $blocked = Room::factory()->create(['number' => '102']);
    Reservation::factory()->checkedIn()->between('2026-11-09', '2026-11-10')->inRoom($booked)->create();
    Reservation::factory()->confirmed()->between('2026-11-11', '2026-11-12')->inRoom($booked)->create();
    RoomBlock::factory()->for($blocked)->create(['start_date' => '2026-11-10', 'end_date' => '2026-11-12']);

    $heatmap = app(AnalyticsService::class)->heatmap(new ReportPeriod('2026-11-09', '2026-11-11'));

    expect($heatmap['rooms'][0])->number->toBe('101')->cells->toBe(['occupied', 'free', 'reserved'])
        ->and($heatmap['rooms'][1]['cells'])->toBe(['free', 'blocked', 'blocked']);
});
