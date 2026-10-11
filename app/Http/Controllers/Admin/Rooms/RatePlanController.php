<?php

namespace App\Http\Controllers\Admin\Rooms;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rooms\SaveRatePlanRequest;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RatePlanController extends Controller
{
    public function store(SaveRatePlanRequest $request, AuditLogger $audit): RedirectResponse
    {
        $plan = $this->persist(new RatePlan, $request->planAttributes());
        $audit->record('rate_plan.created', $plan, newValues: $request->planAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rate plan created.')]);

        return back();
    }

    public function update(SaveRatePlanRequest $request, RatePlan $ratePlan, AuditLogger $audit): RedirectResponse
    {
        $this->persist($ratePlan, $request->planAttributes());
        $audit->recordChanges('rate_plan.updated', $ratePlan);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rate plan updated.')]);

        return back();
    }

    public function destroy(RatePlan $ratePlan, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize(Permission::RatePlansManage->value);

        if (Reservation::query()->where('rate_plan_id', $ratePlan->id)->exists()) {
            throw ValidationException::withMessages([
                'rate_plan' => 'This rate plan is used by existing reservations. Deactivate it instead of deleting it.',
            ]);
        }

        $ratePlan->delete();
        $audit->record('rate_plan.deleted', $ratePlan, ['name' => $ratePlan->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rate plan deleted.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(RatePlan $plan, array $attributes): RatePlan
    {
        return DB::transaction(function () use ($plan, $attributes): RatePlan {
            if ($attributes['is_default'] ?? false) {
                RatePlan::query()->whereKeyNot($plan->id ?? 0)->update(['is_default' => false]);
            }

            $plan->fill($attributes)->save();

            return $plan;
        });
    }
}
