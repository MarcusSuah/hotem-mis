<?php

use App\Enums\GatewayEventStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\SystemRole;
use App\Models\Payment;
use App\Models\PaymentGatewayEvent;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Billing\FolioService;

beforeEach(function () {
    $this->travelTo('2026-11-10 12:00:00');
    config([
        'hms.payments.gateways.online.enabled' => true,
        'hms.payments.gateways.online.secret' => 'test-webhook-secret',
    ]);
});

/**
 * @param  array<string, mixed>  $data
 */
function webhookBody(string $eventId, array $data, string $type = 'payment.succeeded'): string
{
    return (string) json_encode(['id' => $eventId, 'type' => $type, 'data' => $data]);
}

function signedWebhook(string $body, ?int $timestamp = null, string $secret = 'test-webhook-secret'): array
{
    $timestamp ??= now()->getTimestamp();

    return ['HTTP_X_HMS_SIGNATURE' => "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, $secret), 'CONTENT_TYPE' => 'application/json'];
}

function pendingBookingWithFolio(): Reservation
{
    $reservation = Reservation::factory()->pending()->create(['total_minor' => 30000, 'deposit_due_minor' => 9000]);
    app(FolioService::class)->openFor($reservation);

    return $reservation->fresh();
}

it('records a signed online payment once, however often it is delivered', function () {
    $reservation = pendingBookingWithFolio();
    $body = webhookBody('evt_1', ['reservation_reference' => $reservation->reference, 'transaction_id' => 'txn_1', 'amount' => '90.00', 'currency' => 'USD']);

    $this->call('POST', route('webhooks.payments', 'online'), [], [], [], signedWebhook($body), $body)->assertStatus(202);
    $this->call('POST', route('webhooks.payments', 'online'), [], [], [], signedWebhook($body), $body)->assertOk();

    $payment = Payment::query()->sole();
    expect($payment)
        ->amount_minor->toBe(9000)
        ->method->toBe(PaymentMethod::Card)
        ->gateway->toBe('online')
        ->external_reference->toBe('txn_1')
        ->and(PaymentGatewayEvent::query()->sole()->status)->toBe(GatewayEventStatus::Processed)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('rejects webhooks with a wrong or stale signature without storing them', function (string $problem) {
    $reservation = pendingBookingWithFolio();
    $body = webhookBody('evt_2', ['reservation_reference' => $reservation->reference, 'transaction_id' => 'txn_2', 'amount' => '90.00', 'currency' => 'USD']);

    $headers = match ($problem) {
        'wrong secret' => signedWebhook($body, secret: 'guessed-secret'),
        'replayed' => signedWebhook($body, now()->subMinutes(10)->getTimestamp()),
        default => ['CONTENT_TYPE' => 'application/json'],
    };

    $this->call('POST', route('webhooks.payments', 'online'), [], [], [], $headers, $body)->assertUnauthorized();

    expect(PaymentGatewayEvent::query()->count())->toBe(0)->and(Payment::query()->count())->toBe(0);
})->with(['wrong secret', 'replayed', 'unsigned']);

it('answers 404 for gateways that are not enabled', function () {
    config(['hms.payments.gateways.online.enabled' => false]);
    $body = webhookBody('evt_3', []);

    $this->call('POST', route('webhooks.payments', 'online'), [], [], [], signedWebhook($body), $body)->assertNotFound();
});

it('flags events it cannot apply and lets finance staff retry them', function () {
    $reservation = pendingBookingWithFolio();
    $body = webhookBody('evt_4', ['reservation_reference' => 'MLX-TYPO', 'transaction_id' => 'txn_4', 'amount' => '90.00', 'currency' => 'USD', 'card_number' => '4242424242424242']);

    $this->call('POST', route('webhooks.payments', 'online'), [], [], [], signedWebhook($body), $body)->assertStatus(202);

    $event = PaymentGatewayEvent::query()->sole();
    expect($event->status)->toBe(GatewayEventStatus::Failed)
        ->and($event->error)->toContain('MLX-TYPO')
        ->and($event->payload['data']['card_number'])->toBe('[redacted]')
        ->and(Payment::query()->count())->toBe(0);

    $event->forceFill(['reservation_reference' => $reservation->reference])->save();

    $this->actingAs(User::factory()->withRole(SystemRole::ReservationOfficer)->create())
        ->post(route('admin.gateway-events.retry', $event))
        ->assertForbidden();

    $this->actingAs(User::factory()->withRole(SystemRole::Accountant)->create())
        ->post(route('admin.gateway-events.retry', $event))
        ->assertRedirect();

    expect($event->fresh()->status)->toBe(GatewayEventStatus::Processed)->and(Payment::query()->count())->toBe(1);
});

it('stores but ignores event types that need no action', function () {
    $body = webhookBody('evt_5', ['reservation_reference' => 'MLX-ANY'], 'payment.created');

    $this->call('POST', route('webhooks.payments', 'online'), [], [], [], signedWebhook($body), $body)->assertStatus(202);

    expect(PaymentGatewayEvent::query()->sole()->status)->toBe(GatewayEventStatus::Ignored);
});

it('shows online payment notifications to finance staff', function () {
    $this->actingAs(User::factory()->withRole(SystemRole::HousekeepingStaff)->create())->get(route('admin.gateway-events.index'))->assertForbidden();
    $this->actingAs(User::factory()->withRole(SystemRole::Accountant)->create())->get(route('admin.gateway-events.index'))->assertOk();
});
