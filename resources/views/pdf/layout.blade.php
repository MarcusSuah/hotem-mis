<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #263238; font-size: 11px; line-height: 1.45; }
        .header { border-bottom: 3px solid #D6B477; padding-bottom: 12px; margin-bottom: 18px; }
        .brand { font-family: DejaVu Serif, serif; font-size: 22px; color: #123B3A; font-weight: bold; }
        .muted { color: #5b6a6f; }
        .doc-title { font-size: 18px; color: #123B3A; letter-spacing: 2px; text-transform: uppercase; text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .lines th { background: #123B3A; color: #F7F4EC; text-align: left; padding: 6px 8px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .lines td { padding: 6px 8px; border-bottom: 1px solid #e3dccb; }
        .right { text-align: right; }
        .totals td { padding: 4px 8px; }
        .totals .grand td { border-top: 2px solid #123B3A; font-weight: bold; font-size: 13px; color: #123B3A; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-void { background: #fde8e7; color: #b42318; }
        .badge-paid { background: #e3f2ea; color: #2f7a55; }
        .badge-due { background: #fbf1df; color: #b7791f; }
        .box { background: #F7F4EC; border: 1px solid #e3dccb; padding: 10px 12px; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; text-align: center; font-size: 9px; color: #5b6a6f; border-top: 1px solid #e3dccb; padding-top: 6px; }
        .signature { margin-top: 40px; border-top: 1px solid #263238; width: 220px; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="brand">{{ $hotel['name'] ?? config('app.name') }}</div>
                    <div class="muted">{{ $hotel['address'] ?? '' }}</div>
                    <div class="muted">{{ $hotel['phone'] ?? '' }} @if(!empty($hotel['email'])) · {{ $hotel['email'] }} @endif</div>
                </td>
                <td class="doc-title">@yield('title')</td>
            </tr>
        </table>
    </div>

    @yield('content')

    <div class="footer">{{ $hotel['name'] ?? config('app.name') }} · @yield('footer')</div>
</body>
</html>
