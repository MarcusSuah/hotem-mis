<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use App\Models\ReservationRoom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact reservation row for lists.
 *
 * @mixin Reservation
 */
class ReservationSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $allocation = $this->relationLoaded('activeAllocations') ? $this->activeAllocations->sortByDesc('start_date')->first() : null;
        $released = $this->relationLoaded('allocations') ? $this->allocations->sortByDesc('id')->first() : null;
        /** @var ReservationRoom|null $room */
        $room = $allocation ?? $released;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'guest' => $this->whenLoaded('guest', fn () => [
                'id' => $this->guest->id,
                'name' => $this->guest->full_name,
                'email' => $this->guest->email,
                'phone' => $this->guest->phone,
                'is_vip' => $this->guest->is_vip,
            ]),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_status' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status->label(),
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'check_in_date' => $this->check_in_date->toDateString(),
            'check_out_date' => $this->check_out_date->toDateString(),
            'nights' => $this->nights,
            'adults' => $this->adults,
            'children' => $this->children,
            'room' => $room ? [
                'id' => $room->room_id,
                'number' => $room->room?->number,
                'type' => $room->roomType?->name,
            ] : null,
            'total' => $this->total(),
            'balance_due' => $this->balanceDue(),
            'is_complimentary' => $this->is_complimentary,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
