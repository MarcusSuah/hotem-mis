<?php

namespace App\Http\Controllers\Admin\FrontDesk;

use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FrontDesk\RunNightAuditRequest;
use App\Models\NightAudit;
use App\Services\FrontDesk\NightAuditService;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NightAuditController extends Controller
{
    public function index(NightAuditService $audits): Response
    {
        Gate::authorize(Permission::NightAuditRun->value);

        return Inertia::render('admin/front-desk/night-audit', [
            'preview' => $audits->preview(),
            'history' => NightAudit::query()
                ->with('runner:id,name')
                ->orderByDesc('business_date')
                ->limit(30)
                ->get()
                ->map(fn (NightAudit $audit) => [
                    'id' => $audit->id,
                    'business_date' => $audit->business_date->toDateString(),
                    'run_by' => $audit->runner?->name,
                    'completed_at' => $audit->completed_at->toIso8601String(),
                    'summary' => $audit->summary,
                    'room_revenue' => collect((array) ($audit->summary['room_revenue_by_currency'] ?? []))
                        ->map(fn ($minor, $currency) => Money::ofMinor((int) $minor, (string) $currency)->format())
                        ->values(),
                    'payments' => collect((array) ($audit->summary['payments'] ?? []))
                        ->map(fn (array $row) => Money::ofMinor((int) $row['total_minor'], (string) $row['currency'])->format().' '.PaymentMethod::from($row['method'])->label())
                        ->values(),
                ]),
        ]);
    }

    public function store(RunNightAuditRequest $request, NightAuditService $audits): RedirectResponse
    {
        $audit = $audits->run($request->user(), $request->boolean('mark_no_shows'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business day :date closed.', ['date' => $audit->business_date->format('d M Y')])]);

        return back();
    }
}
