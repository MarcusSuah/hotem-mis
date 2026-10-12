<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\ReportRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\AnalyticsService;
use App\Services\Reports\ReportCatalog;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportRequest $request, ReportCatalog $catalog): Response
    {
        $period = $request->period();

        return Inertia::render('admin/reports/index', [
            'reports' => collect(ReportCatalog::REPORTS)->map(fn (array $meta, string $key) => ['key' => $key, ...$meta])->values(),
            'report' => $catalog->build($request->report(), $period),
            'filters' => [
                'report' => $request->report(),
                'from' => $period->from->toDateString(),
                'to' => $period->to->toDateString(),
            ],
            'can' => ['export' => $request->user()->can(Permission::ReportsExport->value)],
        ]);
    }

    public function export(ReportRequest $request, ReportCatalog $catalog, ReportExporter $exporter, AuditLogger $audit): StreamedResponse
    {
        $period = $request->period();
        $report = $catalog->build($request->report(), $period);
        $format = $request->string('format', 'csv')->toString();

        $audit->record('report.exported', newValues: [
            'report' => $report['key'],
            'from' => $period->from->toDateString(),
            'to' => $period->to->toDateString(),
            'format' => $format,
            'rows' => count($report['rows']),
        ]);

        $suffix = ReportCatalog::REPORTS[$report['key']]['periodic']
            ? $period->from->toDateString().'_'.$period->to->toDateString()
            : CarbonImmutable::today()->toDateString();

        return $exporter->download($report, $format, "{$report['key']}_{$suffix}");
    }

    public function heatmap(Request $request, AnalyticsService $analytics): Response
    {
        Gate::authorize(Permission::ReportsView->value);

        $month = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? null;
        $start = $month ? CarbonImmutable::createFromFormat('!Y-m', $month) : CarbonImmutable::today()->startOfMonth();

        return Inertia::render('admin/reports/heatmap', [
            'month' => $start->format('Y-m'),
            'heatmap' => $analytics->heatmap(ReportPeriod::monthOf($start)),
        ]);
    }
}
