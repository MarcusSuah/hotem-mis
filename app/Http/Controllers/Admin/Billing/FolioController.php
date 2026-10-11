<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Enums\ChargeCategory;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\PostChargeRequest;
use App\Http\Requests\Admin\Billing\ReasonRequest;
use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Billing\FolioService;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FolioController extends Controller
{
    public function show(Request $request, Folio $folio, FolioService $folios): Response
    {
        $user = $request->user();
        abort_unless($user->can(Permission::FoliosManage->value) || $user->can(Permission::PaymentsView->value), 403);

        $folio->load(['guest', 'reservation', 'charges.poster', 'charges.reversal', 'payments.receiver', 'payments.refunds', 'refunds.processor', 'refunds.payment', 'invoices']);
        $summary = $folios->summary($folio);

        return Inertia::render('admin/billing/folio', [
            'folio' => [
                'number' => $folio->number,
                'status' => $folio->status,
                'currency' => $folio->currency->value,
                'guest' => ['id' => $folio->guest->id, 'name' => $folio->guest->full_name, 'email' => $folio->guest->email],
                'reservation' => $folio->reservation ? [
                    'reference' => $folio->reservation->reference,
                    'status' => $folio->reservation->status->value,
                    'status_label' => $folio->reservation->status->label(),
                    'check_in_date' => $folio->reservation->check_in_date->toDateString(),
                    'check_out_date' => $folio->reservation->check_out_date->toDateString(),
                    'total' => $folio->reservation->total(),
                    'amount_due' => $folios->amountDue($folio->reservation, $summary),
                ] : null,
                'summary' => $summary->toArray(),
                'charges' => $folio->charges->map(fn (FolioCharge $charge) => [
                    'id' => $charge->id,
                    'category' => $charge->category->value,
                    'category_label' => $charge->category->label(),
                    'description' => $charge->description,
                    'quantity' => $charge->quantity,
                    'unit_amount' => Money::ofMinor($charge->unit_amount_minor, $charge->currency),
                    'amount' => $charge->amount(),
                    'service_date' => $charge->service_date->toDateString(),
                    'posted_by' => $charge->poster?->name,
                    'note' => $charge->note,
                    'is_reversal' => $charge->reversal_of_id !== null,
                    'is_reversed' => $charge->reversal !== null,
                    'created_at' => $charge->created_at->toIso8601String(),
                ]),
                'payments' => $folio->payments->map(fn (Payment $payment) => [
                    'reference' => $payment->reference,
                    'method' => $payment->method->value,
                    'method_label' => $payment->method->label(),
                    'status' => $payment->status->value,
                    'amount' => $payment->amount(),
                    'applied' => Money::ofMinor($payment->applied_minor, $folio->currency),
                    'exchange_rate' => $payment->exchange_rate,
                    'external_reference' => $payment->external_reference,
                    'received_by' => $payment->receiver?->name,
                    'received_at' => $payment->received_at->toIso8601String(),
                    'refundable' => $payment->refundable(),
                ]),
                'refunds' => $folio->refunds->map(fn (Refund $refund) => [
                    'reference' => $refund->reference,
                    'payment' => $refund->payment->reference,
                    'amount' => $refund->amount(),
                    'applied' => Money::ofMinor($refund->applied_minor, $folio->currency),
                    'reason' => $refund->reason,
                    'processed_by' => $refund->processor?->name,
                    'processed_at' => $refund->processed_at->toIso8601String(),
                ]),
                'invoices' => $folio->invoices->map(fn (Invoice $invoice) => [
                    'number' => $invoice->number,
                    'status' => $invoice->status->value,
                    'total' => $invoice->money($invoice->total_minor),
                    'balance' => $invoice->money($invoice->balance_minor),
                    'issued_at' => $invoice->issued_at->toIso8601String(),
                ]),
            ],
            'options' => [
                'categories' => collect(ChargeCategory::manual())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
                'methods' => collect(PaymentMethod::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label(), 'requires_reference' => $case->requiresReference()]),
                'currencies' => Currency::codes(),
            ],
            'can' => [
                'postCharge' => $user->can(Permission::FoliosManage->value) && $folio->isOpen(),
                'reverse' => $user->can(Permission::FoliosManage->value) && $folio->isOpen(),
                'recordPayment' => $user->can(Permission::PaymentsCreate->value),
                'refund' => $user->can(Permission::PaymentsRefund->value),
                'invoice' => $user->can(Permission::InvoicesCreate->value),
            ],
        ]);
    }

    public function storeCharge(PostChargeRequest $request, Folio $folio, FolioService $folios): RedirectResponse
    {
        $amount = Money::of($request->string('unit_amount')->toString(), $folio->currency);
        $category = $request->category();

        $folios->post(
            $folio,
            $category,
            $request->string('description')->toString(),
            $category === ChargeCategory::Discount ? $amount->negate() : $amount,
            $request->integer('quantity'),
            CarbonImmutable::parse($request->string('service_date')->toString()),
            $request->user(),
            'manual:'.$request->string('idempotency_key')->toString(),
            note: $request->filled('note') ? $request->string('note')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Charge posted to folio :folio.', ['folio' => $folio->number])]);

        return back();
    }

    public function reverseCharge(ReasonRequest $request, FolioCharge $charge, FolioService $folios): RedirectResponse
    {
        $folios->reverse($charge, $request->string('reason')->toString(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Charge reversed.')]);

        return back();
    }
}
