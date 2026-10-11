<?php

namespace App\Services\Billing;

use App\Enums\ChargeCategory;
use App\Enums\InvoiceStatus;
use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingException;
use App\Services\Settings\SettingsRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Issues immutable invoice snapshots of a folio and renders them as PDF.
 */
class InvoiceService
{
    public function __construct(
        private FolioService $folios,
        private DocumentNumberGenerator $numbers,
        private SettingsRepository $settings,
        private AuditLogger $audit,
    ) {}

    public function issue(Folio $folio, ?User $actor = null, ?Invoice $replaces = null): Invoice
    {
        $folio->loadMissing(['charges', 'guest', 'reservation.activeAllocations.room', 'reservation.allocations.room']);

        if ($folio->charges->isEmpty()) {
            throw new BookingException('There are no charges to invoice yet.', 'invoice');
        }

        return DB::transaction(function () use ($folio, $actor, $replaces): Invoice {
            $summary = $this->folios->summary($folio);
            $charges = $folio->charges;
            $sum = fn (callable $filter): int => (int) $charges->filter($filter)->sum('amount_minor');

            $serviceCharge = $sum(fn (FolioCharge $charge) => $charge->category === ChargeCategory::ServiceCharge);
            $tax = $sum(fn (FolioCharge $charge) => $charge->category === ChargeCategory::Tax);
            $discount = -$sum(fn (FolioCharge $charge) => $charge->category === ChargeCategory::Discount);
            $subtotal = $summary->charges->minor - $serviceCharge - $tax + $discount;

            $reservation = $folio->reservation;
            $guest = $folio->guest;

            $invoice = Invoice::create([
                'number' => $this->numbers->next('invoice', (string) $this->settings->get('booking.invoice_prefix', 'INV')),
                'folio_id' => $folio->id,
                'reservation_id' => $reservation?->id,
                'guest_id' => $folio->guest_id,
                'currency' => $folio->currency,
                'status' => InvoiceStatus::Issued,
                'subtotal_minor' => $subtotal,
                'service_charge_minor' => $serviceCharge,
                'tax_minor' => $tax,
                'discount_minor' => $discount,
                'total_minor' => $summary->charges->minor,
                'paid_minor' => $summary->netPaid()->minor,
                'balance_minor' => $summary->balance->minor,
                'exchange_rate' => $reservation?->exchange_rate,
                'base_currency' => $reservation->base_currency ?? $folio->currency,
                'billing_details' => [
                    'guest_name' => $guest->full_name,
                    'guest_email' => $guest->email,
                    'guest_phone' => $guest->phone,
                    'guest_address' => $guest->address,
                    'reservation' => $reservation?->reference,
                    'check_in' => $reservation?->check_in_date->toDateString(),
                    'check_out' => $reservation?->check_out_date->toDateString(),
                    'rooms' => $reservation?->allocations->map(fn ($allocation) => $allocation->room?->number)->filter()->unique()->values()->all() ?? [],
                    'hotel' => $this->settings->group('hotel'),
                ],
                'issued_by' => $actor?->id,
                'issued_at' => now(),
                'replaces_invoice_id' => $replaces?->id,
            ]);

            foreach ($charges->values() as $index => $charge) {
                $invoice->items()->create([
                    'folio_charge_id' => $charge->id,
                    'category' => $charge->category,
                    'description' => $charge->description,
                    'service_date' => $charge->service_date,
                    'quantity' => $charge->quantity,
                    'unit_amount_minor' => $charge->unit_amount_minor,
                    'amount_minor' => $charge->amount_minor,
                    'sort_order' => $index,
                ]);
            }

            $this->audit->record('invoice.issued', $invoice, newValues: [
                'number' => $invoice->number,
                'total' => $invoice->money($invoice->total_minor)->format(),
                'replaces' => $replaces?->number,
            ], actor: $actor);

            return $invoice;
        });
    }

    /**
     * Void an invoice and immediately issue a corrected replacement from the current folio.
     */
    public function voidAndReissue(Invoice $invoice, string $reason, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $actor): Invoice {
            $this->void($invoice, $reason, $actor);

            return $this->issue($invoice->folio, $actor, $invoice);
        });
    }

    public function void(Invoice $invoice, string $reason, ?User $actor = null): void
    {
        if ($invoice->status === InvoiceStatus::Void) {
            throw new BookingException("Invoice {$invoice->number} is already void.", 'invoice');
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Void,
            'voided_at' => now(),
            'voided_by' => $actor?->id,
            'void_reason' => $reason,
        ])->save();

        $this->audit->record('invoice.voided', $invoice, newValues: ['number' => $invoice->number, 'reason' => $reason], actor: $actor);
    }

    public function pdf(Invoice $invoice): Response
    {
        $invoice->loadMissing(['items', 'folio.payments', 'folio.refunds']);

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'hotel' => $invoice->billing_details['hotel'] ?? $this->settings->group('hotel'),
        ])->setPaper('a4')->stream("{$invoice->number}.pdf");
    }
}
