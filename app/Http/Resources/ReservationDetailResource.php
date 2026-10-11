<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use App\Models\ReservationNight;
use App\Models\ReservationRoom;
use App\Models\ReservationStatusHistory;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full reservation detail including nightly breakdown, rooms and history.
 *
 * @mixin Reservation
 */
class ReservationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new ReservationSummaryResource($this->resource))->toArray($request),
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'special_requests' => $this->special_requests,
            'rate_plan' => $this->ratePlan?->only(['id', 'name', 'code']),
            'currency' => $this->currency->value,
            'base_currency' => $this->base_currency->value,
            'exchange_rate' => $this->exchange_rate,
            'amounts' => [
                'room_total' => $this->money($this->room_total_minor),
                'extras_total' => $this->money($this->extras_total_minor),
                'discount' => $this->money($this->discount_minor),
                'service_charge' => $this->money($this->service_charge_minor),
                'tax' => $this->money($this->tax_minor),
                'total' => $this->total(),
                'base_total' => Money::ofMinor($this->base_total_minor, $this->base_currency),
                'deposit_due' => $this->money($this->deposit_due_minor),
                'amount_paid' => $this->money($this->amount_paid_minor),
                'balance_due' => $this->balanceDue(),
                'cancellation_fee' => $this->money($this->cancellation_fee_minor),
            ],
            'cancellation_policy' => $this->cancellation_policy_snapshot,
            'cancellation_reason' => $this->cancellation_reason,
            'booked_by' => $this->bookedBy?->name,
            'timestamps' => [
                'created_at' => $this->created_at?->toIso8601String(),
                'confirmed_at' => $this->confirmed_at?->toIso8601String(),
                'expires_at' => $this->expires_at?->toIso8601String(),
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
                'checked_in_at' => $this->checked_in_at?->toIso8601String(),
                'checked_out_at' => $this->checked_out_at?->toIso8601String(),
                'no_show_at' => $this->no_show_at?->toIso8601String(),
            ],
            'allocations' => $this->allocations->sortBy('start_date')->values()->map(fn (ReservationRoom $allocation): array => [
                'id' => $allocation->id,
                'room_number' => $allocation->room?->number,
                'room_type' => $allocation->roomType?->name,
                'start_date' => $allocation->start_date->toDateString(),
                'end_date' => $allocation->end_date->toDateString(),
                'released_at' => $allocation->released_at?->toIso8601String(),
                'release_reason' => $allocation->release_reason,
            ]),
            'night_records' => $this->nightRecords->map(fn (ReservationNight $night): array => [
                'date' => $night->night_date->toDateString(),
                'room_number' => $night->room?->number,
                'amount' => $this->money($night->rate_minor),
            ]),
            'history' => $this->statusHistory->map(fn (ReservationStatusHistory $entry): array => [
                'id' => $entry->id,
                'from' => $entry->from_status?->label(),
                'to' => $entry->to_status->label(),
                'by' => $entry->user?->name,
                'note' => $entry->note,
                'at' => $entry->created_at->toIso8601String(),
            ]),
        ];
    }
}
