@extends('pdf.layout')

@section('title', 'Payment receipt')
@section('footer', 'Receipt '.$payment->reference)

@section('content')
    <table class="meta">
        <tr>
            <td style="width: 55%">
                <div class="muted">Received from</div>
                <strong>{{ $payment->folio->guest->full_name }}</strong><br>
                @if($payment->folio->reservation)
                    Booking {{ $payment->folio->reservation->reference }}
                @endif
            </td>
            <td>
                <table>
                    <tr><td class="muted">Receipt no.</td><td class="right"><strong>{{ $payment->reference }}</strong></td></tr>
                    <tr><td class="muted">Date</td><td class="right">{{ $payment->received_at->format('d M Y H:i') }}</td></tr>
                    <tr><td class="muted">Method</td><td class="right">{{ $payment->method->label() }}</td></tr>
                    @if($payment->external_reference)
                        <tr><td class="muted">Transaction ref.</td><td class="right">{{ $payment->external_reference }}</td></tr>
                    @endif
                    <tr><td class="muted">Received by</td><td class="right">{{ $payment->receiver?->name ?? '—' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="box" style="margin-top: 20px; text-align: center">
        <div class="muted">Amount received</div>
        <div style="font-size: 24px; color: #123B3A; font-weight: bold">{{ $payment->amount()->format() }}</div>
        @if($payment->exchange_rate)
            <div class="muted">
                Applied to folio {{ $payment->folio->number }} as {{ \App\Support\Money\Money::ofMinor($payment->applied_minor, $payment->folio->currency)->format() }}
                at 1 {{ $payment->currency->value }} = {{ rtrim(rtrim((string) $payment->exchange_rate, '0'), '.') }} {{ $payment->folio->currency->value }}
            </div>
        @endif
    </div>

    <p style="margin-top: 16px">Folio balance after this payment: <strong>{{ $balance->format() }}</strong></p>

    <div class="signature">Authorised signature</div>
@endsection
