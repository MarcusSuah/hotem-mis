@extends('pdf.layout')

@section('title', 'Registration card')
@section('footer', 'Registration '.$reservation->reference)

@section('content')
    <table class="meta">
        <tr>
            <td style="width: 50%">
                <div class="muted">Guest</div>
                <strong style="font-size: 14px">{{ $reservation->guest->full_name }}</strong><br>
                {{ $reservation->guest->email }}<br>
                {{ $reservation->guest->phone }}<br>
                @if($reservation->guest->address){{ $reservation->guest->address }}<br>@endif
                @if($reservation->guest->country_code)Country: {{ $reservation->guest->country_code }}@endif
            </td>
            <td>
                <table>
                    <tr><td class="muted">Booking</td><td class="right"><strong>{{ $reservation->reference }}</strong></td></tr>
                    <tr><td class="muted">Room</td><td class="right">{{ $room?->number ?? '—' }} · {{ $room?->roomType?->name }}</td></tr>
                    <tr><td class="muted">Arrival</td><td class="right">{{ $reservation->check_in_date->format('d M Y') }}</td></tr>
                    <tr><td class="muted">Departure</td><td class="right">{{ $reservation->check_out_date->format('d M Y') }}</td></tr>
                    <tr><td class="muted">Guests</td><td class="right">{{ $reservation->adults }} adult(s), {{ $reservation->children }} child(ren)</td></tr>
                    <tr><td class="muted">Checked in</td><td class="right">{{ $reservation->checked_in_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if($showIdentity && $reservation->guest->identity_type)
        <p class="box" style="margin-top: 14px">
            Identification: {{ str($reservation->guest->identity_type)->headline() }}
            ending {{ \Illuminate\Support\Str::of((string) $reservation->guest->identity_number)->substr(-4) }}
        </p>
    @endif

    @if($reservation->occupants->where('is_primary', false)->isNotEmpty())
        <h4 style="color: #123B3A">Accompanying guests</h4>
        <ul>
            @foreach($reservation->occupants->where('is_primary', false) as $occupant)
                <li>{{ $occupant->full_name }} ({{ $occupant->age_category }})</li>
            @endforeach
        </ul>
    @endif

    <h4 style="color: #123B3A; margin-top: 16px">Stay terms</h4>
    <p>
        Total for the stay: <strong>{{ $reservation->total()->format() }}</strong>.
        Check-in from {{ $checkInTime }}, check-out by {{ $checkOutTime }}.
        {{ $reservation->cancellation_policy_snapshot['description'] ?? '' }}
    </p>
    @if($reservation->special_requests)
        <p><strong>Special requests:</strong> {{ $reservation->special_requests }}</p>
    @endif

    <p style="margin-top: 18px">
        I agree to settle all charges incurred during my stay and to abide by the hotel's policies.
    </p>
    <div class="signature">Guest signature</div>
@endsection
