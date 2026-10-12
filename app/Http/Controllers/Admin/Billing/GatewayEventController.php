<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Enums\GatewayEventStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\PaymentGatewayEvent;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\GatewayEventProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Online payment notifications and their outcome, with a retry for events
 * that could not be applied (for example a mistyped booking reference).
 */
class GatewayEventController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::PaymentsView->value);

        $filters = $request->validate(['status' => ['nullable', Rule::enum(GatewayEventStatus::class)]]);

        return Inertia::render('admin/billing/gateway-events', [
            'events' => PaymentGatewayEvent::query()
                ->with('payment:id,reference')
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->latest()
                ->paginate(25)
                ->withQueryString()
                ->through(fn (PaymentGatewayEvent $event) => [
                    ...$event->only(['id', 'gateway', 'event_id', 'type', 'reservation_reference', 'transaction_reference', 'attempts', 'error']),
                    'status' => $event->status->value,
                    'status_label' => $event->status->label(),
                    'payment' => $event->payment?->reference,
                    'amount' => is_array($event->payload['data'] ?? null) ? trim(($event->payload['data']['currency'] ?? '').' '.($event->payload['data']['amount'] ?? '')) : null,
                    'received_at' => $event->created_at?->toIso8601String(),
                    'processed_at' => $event->processed_at?->toIso8601String(),
                ]),
            'filters' => ['status' => $filters['status'] ?? ''],
            'statuses' => GatewayEventStatus::options(),
            'configured' => collect((array) config('hms.payments.gateways'))->filter(fn ($gateway): bool => is_array($gateway) && ($gateway['enabled'] ?? false) && filled($gateway['secret'] ?? null))->keys()->values(),
            'can' => ['retry' => $request->user()->can(Permission::PaymentsCreate->value)],
        ]);
    }

    public function retry(Request $request, PaymentGatewayEvent $event, GatewayEventProcessor $processor, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize(Permission::PaymentsCreate->value);

        abort_unless($event->status === GatewayEventStatus::Failed, 409);

        $result = $processor->process($event);
        $audit->record('payment_gateway.event_retried', $event, newValues: ['status' => $result->status->value]);

        Inertia::flash('toast', $result->status === GatewayEventStatus::Processed
            ? ['type' => 'success', 'message' => __('Payment applied to the booking.')]
            : ['type' => 'error', 'message' => __('Still could not apply: :reason', ['reason' => $result->error])]);

        return back();
    }
}
