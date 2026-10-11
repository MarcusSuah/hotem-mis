<?php

namespace App\Http\Controllers\Admin\Reservations;

use App\Enums\BookingSource;
use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reservations\ModifyReservationRequest;
use App\Http\Requests\Admin\Reservations\StoreReservationRequest;
use App\Http\Resources\ReservationDetailResource;
use App\Http\Resources\ReservationSummaryResource;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\NewReservation;
use App\Services\Booking\PricingService;
use App\Services\Booking\ReservationService;
use App\Services\Booking\StayPeriod;
use App\Services\Currency\MissingExchangeRateException;
use App\Services\Guests\GuestService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Reservation::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'source' => ['nullable', Rule::enum(BookingSource::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(['check_in_date', '-check_in_date', 'created_at', '-created_at', 'total_minor', '-total_minor'])],
        ]);

        $sort = $filters['sort'] ?? '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $reservations = Reservation::query()
            ->with(['guest', 'activeAllocations.room:id,number', 'activeAllocations.roomType:id,name', 'allocations.room:id,number', 'allocations.roomType:id,name'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($inner) => $inner
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('contact_email', 'like', "%{$search}%")
                ->orWhere('contact_phone', 'like', "%{$search}%")
                ->orWhereHas('guest', fn ($guests) => $guests->search($search))))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn ($query, string $status) => $query->where('payment_status', $status))
            ->when($filters['source'] ?? null, fn ($query, string $source) => $query->where('source', $source))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('check_out_date', '>', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('check_in_date', '<=', $to))
            ->orderBy(ltrim($sort, '-'), $direction)
            ->orderBy('id', $direction)
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Reservation $reservation) => (new ReservationSummaryResource($reservation))->resolve());

        return Inertia::render('admin/reservations/index', [
            'reservations' => $reservations,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'payment_status' => $filters['payment_status'] ?? '',
                'source' => $filters['source'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'sort' => $sort,
            ],
            'options' => $this->options(),
            'can' => ['create' => $request->user()->can('create', Reservation::class)],
        ]);
    }

    /**
     * Booking workspace. Availability and guest search are loaded through
     * partial reloads driven by query parameters.
     */
    public function create(Request $request, AvailabilityService $availability, PricingService $pricing): Response
    {
        Gate::authorize('create', Reservation::class);

        $search = Validator::make($request->query(), [
            'check_in_date' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out_date' => ['nullable', 'date', 'after:check_in_date'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'rate_plan_id' => ['nullable', 'integer'],
            'guest_search' => ['nullable', 'string', 'max:100'],
        ]);

        $criteria = $search->fails() ? [] : $search->validated();
        $ratePlans = RatePlan::query()->active()->orderBy('display_order')->orderBy('name')->get();

        return Inertia::render('admin/reservations/create', [
            'criteria' => [
                'check_in_date' => $criteria['check_in_date'] ?? CarbonImmutable::today()->toDateString(),
                'check_out_date' => $criteria['check_out_date'] ?? CarbonImmutable::today()->addDay()->toDateString(),
                'adults' => (int) ($criteria['adults'] ?? 2),
                'children' => (int) ($criteria['children'] ?? 0),
                'currency' => $criteria['currency'] ?? $request->user()->preferred_currency->value ?? 'USD',
                'rate_plan_id' => $criteria['rate_plan_id'] ?? $ratePlans->firstWhere('is_default', true)?->id,
            ],
            'searchErrors' => $search->errors()->toArray(),
            'availability' => fn () => $this->availability($criteria, $ratePlans, $availability, $pricing),
            'guestResults' => fn () => filled($criteria['guest_search'] ?? null)
                ? Guest::query()->search($criteria['guest_search'])->orderBy('last_name')->limit(8)->get()
                    ->map(fn (Guest $guest): array => [
                        'id' => $guest->id,
                        'name' => $guest->full_name,
                        'email' => $guest->email,
                        'phone' => $guest->phone,
                        'is_vip' => $guest->is_vip,
                    ])
                : [],
            'ratePlans' => $ratePlans->map(fn (RatePlan $plan): array => $plan->only(['id', 'name', 'code', 'is_refundable', 'includes_breakfast', 'min_nights'])),
            'sources' => collect(BookingSource::staffChannels())->map(fn (BookingSource $source) => ['value' => $source->value, 'label' => $source->label()]),
            'currencies' => Currency::codes(),
            'can' => ['complimentary' => $request->user()->can('bookings.update')],
        ]);
    }

    public function store(StoreReservationRequest $request, ReservationService $reservations, GuestService $guests): RedirectResponse
    {
        $guest = $request->filled('guest_id')
            ? Guest::query()->findOrFail($request->integer('guest_id'))
            : $guests->findOrCreate($request->newGuestDetails());

        $reservation = $reservations->create(new NewReservation(
            guest: $guest,
            roomType: RoomType::query()->findOrFail($request->integer('room_type_id')),
            stay: $request->stay(),
            adults: $request->integer('adults'),
            children: $request->integer('children'),
            currency: Currency::from($request->string('currency')->toString()),
            source: BookingSource::from($request->string('source')->toString()),
            ratePlan: $request->filled('rate_plan_id') ? RatePlan::query()->find($request->integer('rate_plan_id')) : null,
            roomId: $request->filled('room_id') ? $request->integer('room_id') : null,
            specialRequests: $request->filled('special_requests') ? $request->string('special_requests')->toString() : null,
            confirmImmediately: $request->boolean('confirm'),
            complimentary: $request->boolean('complimentary'),
            idempotencyKey: $request->string('idempotency_key')->toString(),
        ), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reservation :reference created.', ['reference' => $reservation->reference])]);

        return to_route('admin.reservations.show', $reservation);
    }

    public function show(Request $request, Reservation $reservation, AvailabilityService $availability): Response
    {
        Gate::authorize('view', $reservation);

        $reservation->load([
            'guest', 'ratePlan', 'bookedBy', 'folio',
            'activeAllocations.room', 'activeAllocations.roomType',
            'allocations.room', 'allocations.roomType',
            'nightRecords.room', 'statusHistory.user',
        ]);

        $user = $request->user();
        $current = $reservation->activeAllocations->sortByDesc('start_date')->first();

        return Inertia::render('admin/reservations/show', [
            'reservation' => (new ReservationDetailResource($reservation))->resolve(),
            'roomOptions' => fn () => $this->roomOptions($reservation, $current?->room_type_id, $availability),
            'folio' => $reservation->folio?->number,
            'can' => [
                'checkIn' => $user->can('checkin.manage') && in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::Pending], true),
                'checkOut' => $user->can('checkout.manage') && $reservation->status === ReservationStatus::CheckedIn,
                'viewFolio' => $user->can('folios.manage') || $user->can('payments.view'),
                'update' => $user->can('update', $reservation) && $reservation->status->isModifiable(),
                'confirm' => $user->can('update', $reservation) && $reservation->status->canTransitionTo(ReservationStatus::Confirmed),
                'cancel' => $user->can('cancel', $reservation) && $reservation->status->canTransitionTo(ReservationStatus::Cancelled),
                'noShow' => $user->can('markNoShow', $reservation) && $reservation->status->canTransitionTo(ReservationStatus::NoShow)
                    && ! $reservation->check_in_date->isFuture(),
                'assignRoom' => $user->can('assignRoom', $reservation) && in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed, ReservationStatus::CheckedIn], true),
            ],
        ]);
    }

    public function update(ModifyReservationRequest $request, Reservation $reservation, ReservationService $reservations): RedirectResponse
    {
        $reservations->modify($reservation, $request->stay(), $request->integer('adults'), $request->integer('children'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reservation updated.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  Collection<int, RatePlan>  $ratePlans
     * @return array<string, mixed>|null
     */
    private function availability(array $criteria, $ratePlans, AvailabilityService $availability, PricingService $pricing): ?array
    {
        if (blank($criteria['check_in_date'] ?? null) || blank($criteria['check_out_date'] ?? null)) {
            return null;
        }

        $stay = new StayPeriod($criteria['check_in_date'], $criteria['check_out_date']);
        $adults = (int) ($criteria['adults'] ?? 2);
        $children = (int) ($criteria['children'] ?? 0);
        $currency = Currency::from($criteria['currency'] ?? 'USD');
        $ratePlan = $ratePlans->firstWhere('id', (int) ($criteria['rate_plan_id'] ?? 0)) ?? $ratePlans->firstWhere('is_default', true);

        try {
            $types = $availability->roomTypesFor($stay, $adults, $children)->map(function (array $row) use ($stay, $adults, $children, $currency, $ratePlan, $availability, $pricing): array {
                $type = $row['room_type'];

                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'image' => $type->featured_image_url,
                    'max_occupancy' => $type->max_occupancy,
                    'bed_type' => $type->bed_type,
                    'available' => $row['available'],
                    'quote' => $row['available'] > 0 ? $pricing->quote($type, $ratePlan, $stay, $adults, $children, $currency)->toArray() : null,
                    'rooms' => $row['available'] > 0
                        ? $availability->availableRooms($stay, $type->id)->orderBy('floor')->orderBy('number')->get(['id', 'number', 'floor', 'housekeeping_status'])
                            ->map(fn (Room $room): array => ['id' => $room->id, 'number' => $room->number, 'floor' => $room->floor])
                        : [],
                ];
            });
        } catch (MissingExchangeRateException $exception) {
            return ['nights' => $stay->nights(), 'types' => [], 'error' => $exception->getMessage().' Ask an administrator to set the exchange rate.'];
        }

        return ['nights' => $stay->nights(), 'types' => $types->values(), 'error' => null];
    }

    /**
     * Rooms the reservation could move to for its remaining nights.
     *
     * @return list<array<string, mixed>>
     */
    private function roomOptions(Reservation $reservation, ?int $currentTypeId, AvailabilityService $availability): array
    {
        if (! $reservation->status->holdsInventory()) {
            return [];
        }

        $from = $reservation->status === ReservationStatus::CheckedIn
            ? max(CarbonImmutable::today(), $reservation->check_in_date)
            : $reservation->check_in_date;

        if ($from->greaterThanOrEqualTo($reservation->check_out_date)) {
            return [];
        }

        $rooms = $availability->availableRooms(new StayPeriod($from, $reservation->check_out_date), ignore: $reservation)
            ->with('roomType:id,name,max_adults,max_children,max_occupancy')
            ->orderByRaw('room_type_id = ? desc', [$currentTypeId ?? 0])
            ->orderBy('floor')
            ->orderBy('number')
            ->limit(100)
            ->get()
            ->filter(fn (Room $room) => $room->roomType->max_adults >= $reservation->adults
                && $room->roomType->max_children >= $reservation->children
                && $room->roomType->max_occupancy >= $reservation->adults + $reservation->children)
            ->map(fn (Room $room): array => [
                'id' => $room->id,
                'number' => $room->number,
                'floor' => $room->floor,
                'room_type' => $room->roomType->name,
                'same_type' => $room->room_type_id === $currentTypeId,
            ])
            ->all();

        return array_values($rooms);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'statuses' => collect(ReservationStatus::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
            'paymentStatuses' => collect(PaymentStatus::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
            'sources' => collect(BookingSource::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
        ];
    }
}
