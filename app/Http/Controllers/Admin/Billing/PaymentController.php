<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\RecordPaymentRequest;
use App\Http\Requests\Admin\Billing\RefundPaymentRequest;
use App\Models\Folio;
use App\Models\Payment;
use App\Services\Billing\FolioService;
use App\Services\Billing\PaymentService;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::PaymentsView->value);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Payment::query()
            ->with(['folio.guest', 'folio.reservation:id,reference', 'receiver:id,name'])
            ->when($filters['search'] ?? null, fn ($q, string $search) => $q->where(fn ($inner) => $inner
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('external_reference', 'like', "%{$search}%")
                ->orWhereHas('folio.reservation', fn ($reservations) => $reservations->where('reference', 'like', "%{$search}%"))
                ->orWhereHas('folio.guest', fn ($guests) => $guests->search($search))))
            ->when($filters['method'] ?? null, fn ($q, string $method) => $q->where('method', $method))
            ->when($filters['currency'] ?? null, fn ($q, string $currency) => $q->where('currency', $currency))
            ->when($filters['from'] ?? null, fn ($q, string $from) => $q->whereDate('received_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, string $to) => $q->whereDate('received_at', '<=', $to));

        $totals = (clone $query)
            ->where('status', TransactionStatus::Completed->value)
            ->selectRaw('currency, sum(amount_minor) as total')
            ->groupBy('currency')
            ->toBase()
            ->get()
            ->map(fn (object $row) => Money::ofMinor((int) $row->total, (string) $row->currency));

        $payments = $query->orderByDesc('received_at')->orderByDesc('id')->paginate(25)->withQueryString()
            ->through(fn (Payment $payment) => [
                'reference' => $payment->reference,
                'folio' => $payment->folio->number,
                'reservation' => $payment->folio->reservation?->reference,
                'guest' => $payment->folio->guest->full_name,
                'method_label' => $payment->method->label(),
                'status' => $payment->status->value,
                'amount' => $payment->amount(),
                'external_reference' => $payment->external_reference,
                'received_by' => $payment->receiver?->name,
                'received_at' => $payment->received_at->toIso8601String(),
            ]);

        return Inertia::render('admin/billing/payments', [
            'payments' => $payments,
            'totals' => $totals,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'method' => $filters['method'] ?? '',
                'currency' => $filters['currency'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'methods' => collect(PaymentMethod::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
            'currencies' => Currency::codes(),
        ]);
    }

    public function store(RecordPaymentRequest $request, Folio $folio, PaymentService $payments): RedirectResponse
    {
        $payment = $payments->record(
            $folio,
            PaymentMethod::from($request->string('method')->toString()),
            Money::of($request->string('amount')->toString(), $request->string('currency')->toString()),
            $request->user(),
            $request->filled('external_reference') ? $request->string('external_reference')->toString() : null,
            $request->filled('notes') ? $request->string('notes')->toString() : null,
            'payment:'.$request->string('idempotency_key')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment :reference of :amount recorded.', [
            'reference' => $payment->reference,
            'amount' => $payment->amount()->format(),
        ])]);

        return back();
    }

    public function refund(RefundPaymentRequest $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $refund = $payments->refund(
            $payment,
            Money::of($request->string('amount')->toString(), $payment->currency),
            $request->string('reason')->toString(),
            PaymentMethod::from($request->string('method')->toString()),
            $request->user(),
            $request->filled('external_reference') ? $request->string('external_reference')->toString() : null,
            'refund:'.$request->string('idempotency_key')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Refund :reference of :amount processed.', [
            'reference' => $refund->reference,
            'amount' => $refund->amount()->format(),
        ])]);

        return back();
    }

    public function receipt(Request $request, Payment $payment, FolioService $folios, SettingsRepository $settings): HttpResponse
    {
        abort_unless($request->user()->can(Permission::PaymentsView->value) || $request->user()->can(Permission::PaymentsCreate->value), 403);
        abort_unless($payment->status === TransactionStatus::Completed, 404);

        $payment->load(['folio.guest', 'folio.reservation', 'receiver']);

        return Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
            'balance' => $folios->summary($payment->folio)->balance,
            'hotel' => $settings->group('hotel'),
        ])->setPaper('a5')->stream("receipt-{$payment->reference}.pdf");
    }
}
