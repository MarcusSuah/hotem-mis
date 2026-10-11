<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\ReasonRequest;
use App\Models\Folio;
use App\Models\Invoice;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::InvoicesView->value);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'balance' => ['nullable', Rule::in(['outstanding', 'settled'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $invoices = Invoice::query()
            ->with(['guest', 'reservation:id,reference', 'folio:id,number'])
            ->when($filters['search'] ?? null, fn ($q, string $search) => $q->where(fn ($inner) => $inner
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('reservation', fn ($reservations) => $reservations->where('reference', 'like', "%{$search}%"))
                ->orWhereHas('guest', fn ($guests) => $guests->search($search))))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when(($filters['balance'] ?? null) === 'outstanding', fn ($q) => $q->where('balance_minor', '>', 0)->where('status', InvoiceStatus::Issued->value))
            ->when(($filters['balance'] ?? null) === 'settled', fn ($q) => $q->where('balance_minor', '<=', 0))
            ->when($filters['from'] ?? null, fn ($q, string $from) => $q->whereDate('issued_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, string $to) => $q->whereDate('issued_at', '<=', $to))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Invoice $invoice) => [
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'guest' => $invoice->guest->full_name,
                'reservation' => $invoice->reservation?->reference,
                'folio' => $invoice->folio->number,
                'total' => $invoice->money($invoice->total_minor),
                'paid' => $invoice->money($invoice->paid_minor),
                'balance' => $invoice->money($invoice->balance_minor),
                'issued_at' => $invoice->issued_at->toIso8601String(),
                'void_reason' => $invoice->void_reason,
            ]);

        return Inertia::render('admin/billing/invoices', [
            'invoices' => $invoices,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'balance' => $filters['balance'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ]);
    }

    public function store(Request $request, Folio $folio, InvoiceService $invoices): RedirectResponse
    {
        Gate::authorize(Permission::InvoicesCreate->value);

        $invoice = $invoices->issue($folio, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice :number issued.', ['number' => $invoice->number])]);

        return back();
    }

    public function show(Invoice $invoice, InvoiceService $invoices): HttpResponse
    {
        Gate::authorize(Permission::InvoicesView->value);

        return $invoices->pdf($invoice);
    }

    public function void(ReasonRequest $request, Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        $reason = $request->string('reason')->toString();

        if ($request->boolean('reissue')) {
            $replacement = $invoices->voidAndReissue($invoice, $reason, $request->user());
            $message = __('Invoice :old voided and replaced by :new.', ['old' => $invoice->number, 'new' => $replacement->number]);
        } else {
            $invoices->void($invoice, $reason, $request->user());
            $message = __('Invoice :number voided.', ['number' => $invoice->number]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
