<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Enums\Currency;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\StoreExchangeRateRequest;
use App\Models\ExchangeRate;
use App\Services\Audit\AuditLogger;
use App\Services\Currency\CurrencyConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Exchange rates are append-only: a new rate takes effect from its
 * effective date, and past transactions keep the rate snapshot they used.
 */
class ExchangeRateController extends Controller
{
    public function index(Request $request, CurrencyConverter $converter): Response
    {
        $user = $request->user();
        abort_unless($user->can(Permission::ExchangeRatesManage->value) || $user->can(Permission::ReportsView->value), 403);

        $base = $converter->baseCurrency();
        $quote = $base === Currency::USD ? Currency::LRD : Currency::USD;
        $current = $converter->rateAt($base, $quote);

        return Inertia::render('admin/billing/exchange-rates', [
            'baseCurrency' => $base->value,
            'quoteCurrency' => $quote->value,
            'current' => $current ? [
                'rate' => $converter->factor($base, $quote, $current),
                'effective_at' => $current->effective_at->toIso8601String(),
            ] : null,
            'history' => ExchangeRate::query()
                ->with('creator:id,name')
                ->orderByDesc('effective_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (ExchangeRate $rate) => [
                    'id' => $rate->id,
                    'base_currency' => $rate->base_currency->value,
                    'quote_currency' => $rate->quote_currency->value,
                    'rate' => (string) $rate->rate,
                    'effective_at' => $rate->effective_at->toIso8601String(),
                    'created_by' => $rate->creator?->name,
                    'notes' => $rate->notes,
                    'is_future' => $rate->effective_at->isFuture(),
                ]),
            'can' => ['manage' => $user->can(Permission::ExchangeRatesManage->value)],
        ]);
    }

    public function store(StoreExchangeRateRequest $request, AuditLogger $audit): RedirectResponse
    {
        $rate = ExchangeRate::create([
            'base_currency' => $request->string('base_currency')->toString(),
            'quote_currency' => $request->string('quote_currency')->toString(),
            'rate' => $request->string('rate')->toString(),
            'effective_at' => $request->date('effective_at'),
            'created_by' => $request->user()->id,
            'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
        ]);

        $audit->record('exchange_rate.created', $rate, newValues: [
            'pair' => "{$rate->base_currency->value}/{$rate->quote_currency->value}",
            'rate' => (string) $rate->rate,
            'effective_at' => $rate->effective_at->toIso8601String(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exchange rate recorded.')]);

        return back();
    }
}
