@extends('pdf.layout')

@php
    $money = fn (int $minor) => $invoice->money($minor)->format();
    $details = $invoice->billing_details;
@endphp

@section('title', 'Invoice')
@section('footer', 'Invoice '.$invoice->number.' · Amounts in '.$invoice->currency->value)

@section('content')
    <table class="meta">
        <tr>
            <td style="width: 55%">
                <div class="muted">Billed to</div>
                <strong>{{ $details['guest_name'] ?? '' }}</strong><br>
                @if(!empty($details['guest_address'])){{ $details['guest_address'] }}<br>@endif
                @if(!empty($details['guest_email'])){{ $details['guest_email'] }}<br>@endif
                @if(!empty($details['guest_phone'])){{ $details['guest_phone'] }}@endif
            </td>
            <td>
                <table>
                    <tr><td class="muted">Invoice no.</td><td class="right"><strong>{{ $invoice->number }}</strong></td></tr>
                    <tr><td class="muted">Issued</td><td class="right">{{ $invoice->issued_at->format('d M Y H:i') }}</td></tr>
                    @if(!empty($details['reservation']))
                        <tr><td class="muted">Booking</td><td class="right">{{ $details['reservation'] }}</td></tr>
                        <tr><td class="muted">Stay</td><td class="right">{{ \Carbon\CarbonImmutable::parse($details['check_in'])->format('d M Y') }} – {{ \Carbon\CarbonImmutable::parse($details['check_out'])->format('d M Y') }}</td></tr>
                        <tr><td class="muted">Room(s)</td><td class="right">{{ implode(', ', $details['rooms'] ?? []) }}</td></tr>
                    @endif
                    <tr>
                        <td class="muted">Status</td>
                        <td class="right">
                            @if($invoice->status->value === 'void')
                                <span class="badge badge-void">Void</span>
                            @elseif($invoice->balance_minor <= 0)
                                <span class="badge badge-paid">Paid</span>
                            @else
                                <span class="badge badge-due">Balance due</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if($invoice->status->value === 'void')
        <p class="box"><strong>This invoice was voided</strong> on {{ $invoice->voided_at?->format('d M Y') }}: {{ $invoice->void_reason }}</p>
    @endif

    <table class="lines" style="margin-top: 16px">
        <thead>
            <tr>
                <th style="width: 14%">Date</th>
                <th>Description</th>
                <th class="right" style="width: 8%">Qty</th>
                <th class="right" style="width: 16%">Unit</th>
                <th class="right" style="width: 18%">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->service_date->format('d M Y') }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ $money($item->unit_amount_minor) }}</td>
                    <td class="right">{{ $money($item->amount_minor) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top: 12px; width: 45%; margin-left: 55%">
        <tr><td>Subtotal</td><td class="right">{{ $money($invoice->subtotal_minor) }}</td></tr>
        @if($invoice->discount_minor > 0)
            <tr><td>Discounts</td><td class="right">-{{ $money($invoice->discount_minor) }}</td></tr>
        @endif
        <tr><td>Service charge</td><td class="right">{{ $money($invoice->service_charge_minor) }}</td></tr>
        <tr><td>Tax</td><td class="right">{{ $money($invoice->tax_minor) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="right">{{ $money($invoice->total_minor) }}</td></tr>
        <tr><td>Paid</td><td class="right">{{ $money($invoice->paid_minor) }}</td></tr>
        <tr class="grand"><td>Balance due</td><td class="right">{{ $money($invoice->balance_minor) }}</td></tr>
    </table>

    @if($invoice->folio->payments->isNotEmpty())
        <h4 style="color: #123B3A; margin-top: 18px">Payments received</h4>
        <table class="lines">
            <thead><tr><th>Date</th><th>Reference</th><th>Method</th><th class="right">Paid</th><th class="right">Applied</th></tr></thead>
            <tbody>
                @foreach($invoice->folio->payments as $payment)
                    @if($payment->status->value === 'completed' && $payment->received_at->lessThanOrEqualTo($invoice->issued_at))
                        <tr>
                            <td>{{ $payment->received_at->format('d M Y') }}</td>
                            <td>{{ $payment->reference }}</td>
                            <td>{{ $payment->method->label() }}</td>
                            <td class="right">{{ $payment->amount()->format() }}</td>
                            <td class="right">{{ $money($payment->applied_minor) }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    @if($invoice->exchange_rate)
        <p class="muted" style="margin-top: 14px">
            Charged in {{ $invoice->currency->value }} at the booking exchange rate of
            1 {{ $invoice->base_currency->value }} = {{ rtrim(rtrim((string) $invoice->exchange_rate, '0'), '.') }} {{ $invoice->currency->value }}.
        </p>
    @endif
@endsection
