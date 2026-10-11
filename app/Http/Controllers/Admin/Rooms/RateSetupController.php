<?php

namespace App\Http\Controllers\Admin\Rooms;

use App\Enums\CancellationPenalty;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\CancellationPolicy;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Models\SeasonalRate;
use App\Services\Currency\CurrencyConverter;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One screen for rate plans, seasonal rates, cancellation policies and amenities.
 */
class RateSetupController extends Controller
{
    public function __invoke(Request $request, CurrencyConverter $converter): Response
    {
        Gate::authorize(Permission::RoomsView->value);

        $base = $converter->baseCurrency();

        return Inertia::render('admin/rates/index', [
            'baseCurrency' => $base->value,
            'ratePlans' => RatePlan::query()->with('cancellationPolicy:id,name')->orderBy('display_order')->orderBy('name')->get()
                ->map(fn (RatePlan $plan): array => [
                    ...$plan->only(['id', 'name', 'code', 'description', 'cancellation_policy_id', 'is_refundable', 'includes_breakfast', 'min_nights', 'is_public', 'is_active', 'is_default', 'display_order']),
                    'adjustment_percent' => (string) $plan->adjustment_percent,
                    'deposit_percent' => $plan->deposit_percent === null ? null : (string) $plan->deposit_percent,
                    'cancellation_policy' => $plan->cancellationPolicy->name,
                ]),
            'seasonalRates' => SeasonalRate::query()->with('roomType:id,name')->orderByDesc('start_date')->get()
                ->map(fn (SeasonalRate $season): array => [
                    ...$season->only(['id', 'room_type_id', 'name', 'priority', 'is_active']),
                    'room_type' => $season->roomType->name,
                    'start_date' => $season->start_date->toDateString(),
                    'end_date' => $season->end_date->toDateString(),
                    'rate' => Money::ofMinor($season->rate_minor, $base),
                    'weekend_rate' => $season->weekend_rate_minor === null ? null : Money::ofMinor($season->weekend_rate_minor, $base),
                ]),
            'cancellationPolicies' => CancellationPolicy::query()->orderBy('name')->get()
                ->map(fn (CancellationPolicy $policy): array => [
                    ...$policy->only(['id', 'name', 'description', 'free_cancellation_hours', 'is_default']),
                    'penalty_type' => $policy->penalty_type->value,
                    'penalty_percent' => (string) $policy->penalty_percent,
                    'summary' => $policy->summary(),
                ]),
            'amenities' => Amenity::query()->withCount('roomTypes')->orderBy('display_order')->orderBy('name')->get(),
            'roomTypes' => RoomType::query()->ordered()->get(['id', 'name']),
            'penaltyTypes' => collect(CancellationPenalty::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
            'can' => [
                'manageRates' => $request->user()->can(Permission::RatePlansManage->value),
                'manageAmenities' => $request->user()->can(Permission::RoomTypesManage->value),
            ],
        ]);
    }
}
