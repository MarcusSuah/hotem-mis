<?php

namespace App\Http\Controllers\Admin\Rooms;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rooms\SaveCancellationPolicyRequest;
use App\Models\CancellationPolicy;
use App\Models\RatePlan;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Editing a policy never changes existing reservations: each booking keeps
 * a snapshot of the terms that applied when it was made.
 */
class CancellationPolicyController extends Controller
{
    public function store(SaveCancellationPolicyRequest $request, AuditLogger $audit): RedirectResponse
    {
        $policy = $this->persist(new CancellationPolicy, $request->policyAttributes());
        $audit->record('cancellation_policy.created', $policy, newValues: $policy->snapshot());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cancellation policy created.')]);

        return back();
    }

    public function update(SaveCancellationPolicyRequest $request, CancellationPolicy $cancellationPolicy, AuditLogger $audit): RedirectResponse
    {
        $before = $cancellationPolicy->snapshot();
        $this->persist($cancellationPolicy, $request->policyAttributes());
        $audit->record('cancellation_policy.updated', $cancellationPolicy, $before, $cancellationPolicy->snapshot());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cancellation policy updated.')]);

        return back();
    }

    public function destroy(CancellationPolicy $cancellationPolicy, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize(Permission::RatePlansManage->value);

        if (RatePlan::query()->where('cancellation_policy_id', $cancellationPolicy->id)->exists()) {
            throw ValidationException::withMessages([
                'cancellation_policy' => 'Rate plans still use this policy. Assign them another policy first.',
            ]);
        }

        $cancellationPolicy->delete();
        $audit->record('cancellation_policy.deleted', $cancellationPolicy, ['name' => $cancellationPolicy->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cancellation policy deleted.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(CancellationPolicy $policy, array $attributes): CancellationPolicy
    {
        return DB::transaction(function () use ($policy, $attributes): CancellationPolicy {
            if ($attributes['is_default'] ?? false) {
                CancellationPolicy::query()->whereKeyNot($policy->id ?? 0)->update(['is_default' => false]);
            }

            $policy->fill($attributes)->save();

            return $policy;
        });
    }
}
