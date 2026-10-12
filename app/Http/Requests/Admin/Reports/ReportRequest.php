<?php

namespace App\Http\Requests\Admin\Reports;

use App\Enums\Permission;
use App\Services\Reports\ReportCatalog;
use App\Services\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Report selection and period. Periods are capped at a year so a single
 * request can never scan the whole history.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route()?->getName() === 'admin.reports.export' ? Permission::ReportsExport : Permission::ReportsView;

        return $this->user()->can($permission->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'report' => ['nullable', Rule::in(array_keys(ReportCatalog::REPORTS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.CarbonImmutable::today()->addYear()->toDateString()],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->isEmpty() && $this->period()->days() > 366) {
                $validator->errors()->add('to', 'Choose a period of one year or less.');
            }
        }];
    }

    public function report(): string
    {
        return $this->string('report', 'performance')->toString();
    }

    /**
     * Defaults: the last 30 days, or the next 30 days for the forecast.
     */
    public function period(): ReportPeriod
    {
        $today = CarbonImmutable::today();
        $forecast = $this->report() === 'forecast';

        $from = $this->filled('from') ? $this->string('from')->toString() : ($forecast ? $today : $today->subDays(29));
        $to = $this->filled('to') ? $this->string('to')->toString() : ($forecast ? $today->addDays(29) : $today);

        return new ReportPeriod($from, $to);
    }
}
