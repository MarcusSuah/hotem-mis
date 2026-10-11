<?php

namespace App\Http\Controllers\Admin\Rooms;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rooms\SaveSeasonalRateRequest;
use App\Models\SeasonalRate;
use App\Services\Audit\AuditLogger;
use App\Services\Currency\CurrencyConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Seasonal rates affect only quotes made after the change; existing
 * reservations keep the nightly rates stored when they were booked.
 */
class SeasonalRateController extends Controller
{
    public function store(SaveSeasonalRateRequest $request, CurrencyConverter $converter, AuditLogger $audit): RedirectResponse
    {
        $season = SeasonalRate::create($request->seasonAttributes($converter));
        $audit->record('seasonal_rate.created', $season, newValues: $season->only(['room_type_id', 'name', 'rate_minor']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Seasonal rate created.')]);

        return back();
    }

    public function update(SaveSeasonalRateRequest $request, SeasonalRate $seasonalRate, CurrencyConverter $converter, AuditLogger $audit): RedirectResponse
    {
        $seasonalRate->update($request->seasonAttributes($converter));
        $audit->recordChanges('seasonal_rate.updated', $seasonalRate);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Seasonal rate updated.')]);

        return back();
    }

    public function destroy(SeasonalRate $seasonalRate, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize(Permission::RatePlansManage->value);

        $seasonalRate->delete();
        $audit->record('seasonal_rate.deleted', $seasonalRate, ['name' => $seasonalRate->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Seasonal rate deleted.')]);

        return back();
    }
}
