<?php

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Reservation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-10 12:00:00');
});

it('shows reports only to staff allowed to view them', function (string $route) {
    $this->actingAs(User::factory()->withRole(SystemRole::Receptionist)->create())->get(route($route))->assertForbidden();
    $this->actingAs(User::factory()->withRole(SystemRole::Accountant)->create())->get(route($route))->assertOk();
})->with(['admin.reports.index', 'admin.reports.heatmap']);

it('renders each report', function (string $report) {
    $this->actingAs(User::factory()->withRole(SystemRole::Accountant)->create())
        ->get(route('admin.reports.index', ['report' => $report]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('report.key', $report)->has('report.columns'));
})->with(['performance', 'revenue', 'payments', 'sources', 'arrivals', 'forecast', 'balances', 'inventory']);

it('rejects report periods longer than a year', function () {
    $this->actingAs(User::factory()->withRole(SystemRole::Accountant)->create())
        ->get(route('admin.reports.index', ['from' => '2024-01-01', 'to' => '2026-01-01']))
        ->assertSessionHasErrors('to');
});

it('exports a report as csv, neutralising spreadsheet formulas, and audits it', function () {
    $accountant = User::factory()->withRole(SystemRole::Accountant)->create();
    Reservation::factory()->between('2026-11-12', '2026-11-14')->create(['reference' => 'MLX-CSV1', 'contact_name' => '=HYPERLINK("https://evil.example")']);

    $response = $this->actingAs($accountant)->get(route('admin.reports.export', ['report' => 'arrivals', 'from' => '2026-11-10', 'to' => '2026-11-20', 'format' => 'csv']));

    $response->assertOk()->assertDownload('arrivals_2026-11-10_2026-11-20.csv');
    $csv = $response->streamedContent();

    expect($csv)->toContain('Reference,Guest,Arrival')
        ->toContain('MLX-CSV1')
        ->toContain("'=HYPERLINK")
        ->not->toContain(',=HYPERLINK');

    expect(AuditLog::query()->where('action', 'report.exported')->sole()->new_values)->toMatchArray(['report' => 'arrivals', 'format' => 'csv', 'rows' => 1]);
});

it('exports excel workbooks', function () {
    $response = $this->actingAs(User::factory()->withRole(SystemRole::Accountant)->create())
        ->get(route('admin.reports.export', ['report' => 'performance', 'format' => 'xlsx']));

    $response->assertOk()->assertDownload('performance_2026-10-12_2026-11-10.xlsx');
    expect(substr($response->streamedContent(), 0, 2))->toBe('PK');
});

it('needs the export permission to download', function () {
    $this->actingAs(User::factory()->withRole(SystemRole::ReservationOfficer)->create())
        ->get(route('admin.reports.export', ['report' => 'performance']))
        ->assertForbidden();
});

it('loads financial charts on the dashboard only for report viewers', function () {
    $this->actingAs(User::factory()->withRole(SystemRole::HousekeepingStaff)->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('today.arrivals')->where('finance', null));

    $this->actingAs(User::factory()->withRole(SystemRole::Accountant)->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('finance')
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('finance.summary.current')->has('finance.forecast', 30)));
});

it('remembers the display currency for visitors and signed-in users', function () {
    $this->post(route('currency.update'), ['currency' => 'LRD'])->assertCookie('display_currency', 'LRD', false);

    $user = User::factory()->create();
    $this->actingAs($user)->post(route('currency.update'), ['currency' => 'LRD']);
    expect($user->fresh()->preferred_currency->value)->toBe('LRD');

    $this->post(route('currency.update'), ['currency' => 'EUR'])->assertSessionHasErrors('currency');
});
