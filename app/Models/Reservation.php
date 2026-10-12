<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $reference
 * @property int $property_id
 * @property int $guest_id
 * @property int|null $rate_plan_id
 * @property int|null $booked_by
 * @property BookingSource $source
 * @property ReservationStatus $status
 * @property PaymentStatus $payment_status
 * @property CarbonImmutable $check_in_date
 * @property CarbonImmutable $check_out_date
 * @property int $nights
 * @property int $adults
 * @property int $children
 * @property string $contact_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $special_requests
 * @property Currency $currency
 * @property Currency $base_currency
 * @property int|null $exchange_rate_id
 * @property string|null $exchange_rate
 * @property string $tax_rate
 * @property string $service_charge_rate
 * @property int $room_total_minor
 * @property int $extras_total_minor
 * @property int $discount_minor
 * @property int $service_charge_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property int $base_total_minor
 * @property int $deposit_due_minor
 * @property int $amount_paid_minor
 * @property int $cancellation_fee_minor
 * @property bool $is_complimentary
 * @property array<string, mixed> $cancellation_policy_snapshot
 * @property string|null $cancellation_reason
 * @property string|null $idempotency_key
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $checked_in_at
 * @property CarbonImmutable|null $checked_out_at
 * @property CarbonImmutable|null $no_show_at
 * @property CarbonImmutable|null $arrival_reminder_sent_at
 * @property CarbonImmutable|null $review_invited_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'reference', 'property_id', 'guest_id', 'rate_plan_id', 'booked_by', 'source', 'status', 'payment_status',
    'check_in_date', 'check_out_date', 'nights', 'adults', 'children',
    'contact_name', 'contact_email', 'contact_phone', 'special_requests',
    'currency', 'base_currency', 'exchange_rate_id', 'exchange_rate', 'tax_rate', 'service_charge_rate',
    'room_total_minor', 'extras_total_minor', 'discount_minor', 'service_charge_minor', 'tax_minor',
    'total_minor', 'base_total_minor', 'deposit_due_minor', 'is_complimentary',
    'cancellation_policy_snapshot', 'idempotency_key', 'expires_at',
])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class)->withTrashed();
    }

    /**
     * @return BelongsTo<RatePlan, $this>
     */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by')->withTrashed();
    }

    /**
     * @return BelongsTo<ExchangeRate, $this>
     */
    public function exchangeRateRecord(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class, 'exchange_rate_id');
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * @return HasOne<Folio, $this>
     */
    public function folio(): HasOne
    {
        return $this->hasOne(Folio::class);
    }

    /**
     * Every allocation, including released history.
     *
     * @return HasMany<ReservationRoom, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    /**
     * @return HasMany<ReservationRoom, $this>
     */
    public function activeAllocations(): HasMany
    {
        return $this->hasMany(ReservationRoom::class)->whereNull('released_at');
    }

    /**
     * @return HasMany<ReservationNight, $this>
     */
    public function nightRecords(): HasMany
    {
        return $this->hasMany(ReservationNight::class)->orderBy('night_date');
    }

    /**
     * @return HasMany<ReservationGuest, $this>
     */
    public function occupants(): HasMany
    {
        return $this->hasMany(ReservationGuest::class);
    }

    /**
     * @return HasMany<ReservationStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ReservationStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function money(int $minor): Money
    {
        return Money::ofMinor($minor, $this->currency);
    }

    public function total(): Money
    {
        return $this->money($this->total_minor);
    }

    public function balanceDue(): Money
    {
        return $this->money($this->total_minor - $this->amount_paid_minor);
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    #[Scope]
    protected function holdingInventory(Builder $query): void
    {
        $query->whereIn('status', ReservationStatus::inventoryHolding());
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    #[Scope]
    protected function arrivingOn(Builder $query, CarbonImmutable $date): void
    {
        $query->whereDate('check_in_date', $date)
            ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::Pending->value]);
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    #[Scope]
    protected function departingOn(Builder $query, CarbonImmutable $date): void
    {
        $query->whereDate('check_out_date', $date)->where('status', ReservationStatus::CheckedIn->value);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'source' => BookingSource::class,
            'status' => ReservationStatus::class,
            'payment_status' => PaymentStatus::class,
            'currency' => Currency::class,
            'base_currency' => Currency::class,
            'check_in_date' => 'immutable_date',
            'check_out_date' => 'immutable_date',
            'exchange_rate' => 'decimal:8',
            'tax_rate' => 'decimal:2',
            'service_charge_rate' => 'decimal:2',
            'room_total_minor' => 'integer',
            'extras_total_minor' => 'integer',
            'discount_minor' => 'integer',
            'service_charge_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'base_total_minor' => 'integer',
            'deposit_due_minor' => 'integer',
            'amount_paid_minor' => 'integer',
            'cancellation_fee_minor' => 'integer',
            'is_complimentary' => 'boolean',
            'cancellation_policy_snapshot' => 'array',
            'confirmed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'checked_in_at' => 'immutable_datetime',
            'checked_out_at' => 'immutable_datetime',
            'no_show_at' => 'immutable_datetime',
            'arrival_reminder_sent_at' => 'immutable_datetime',
            'review_invited_at' => 'immutable_datetime',
        ];
    }
}
