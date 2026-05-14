<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement — {{ $booking->code }}</title>
    <style>
        @page { margin: 28mm 18mm 24mm 18mm; }
        body {
            font-family: 'Helvetica', sans-serif;
            color: #1f2937;
            font-size: 10pt;
            line-height: 1.4;
        }
        .header { border-bottom: 3px solid {{ $branding['primary'] }}; padding-bottom: 12px; margin-bottom: 18px; }
        .brand { color: {{ $branding['primary'] }}; font-size: 22pt; font-weight: bold; letter-spacing: 0.5px; }
        .brand-sub { color: {{ $branding['accent'] }}; font-size: 9pt; text-transform: uppercase; letter-spacing: 2px; }
        .doc-title { float: right; text-align: right; }
        .doc-title h1 { margin: 0; font-size: 16pt; color: #111827; }
        .doc-title .meta { color: #6b7280; font-size: 9pt; }
        .clear { clear: both; }

        .panel { margin-top: 16px; }
        .panel h3 { font-size: 8pt; text-transform: uppercase; letter-spacing: 1.5px; color: #6b7280; margin: 0 0 6px; }
        .grid-3 { width: 100%; }
        .grid-3 td { width: 33%; vertical-align: top; padding: 0 6px; }
        .grid-3 td:first-child { padding-left: 0; }
        .grid-3 td:last-child { padding-right: 0; }

        .kv { padding: 1px 0; }
        .kv .k { color: #6b7280; font-size: 8pt; }
        .kv .v { font-size: 10pt; }

        table.ledger { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.ledger th {
            background: {{ $branding['primary'] }};
            color: #ffffff;
            padding: 8px 6px;
            font-size: 9pt;
            text-align: left;
            font-weight: 600;
        }
        table.ledger td {
            padding: 6px;
            font-size: 9pt;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.ledger tr:nth-child(even) td { background: #f9fafb; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .muted { color: #6b7280; }

        .totals { margin-top: 18px; width: 100%; }
        .totals td { padding: 4px 6px; }
        .totals .label { color: #6b7280; text-align: right; }
        .totals .val { text-align: right; font-variant-numeric: tabular-nums; width: 100px; }
        .totals tr.grand td { font-weight: bold; font-size: 12pt; border-top: 2px solid {{ $branding['primary'] }}; padding-top: 8px; }

        .footer {
            position: fixed; bottom: -16mm; left: 0; right: 0;
            font-size: 7.5pt; color: #6b7280; text-align: center;
            border-top: 1px solid #e5e7eb; padding-top: 4px;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="doc-title">
        <h1>Account Statement</h1>
        <div class="meta">Generated {{ $generated_at->format('d M Y, H:i') }} PKT</div>
    </div>
    <div class="brand">Zen Retreats</div>
    <div class="brand-sub">Booking Statement · {{ $booking->code }}</div>
    <div class="clear"></div>
</div>

<table class="grid-3"><tr>
    <td class="panel">
        <h3>Client</h3>
        <div class="kv"><div class="v">{{ $booking->client->full_name }}</div></div>
        <div class="kv"><div class="k">Code</div><div class="v">{{ $booking->client->code }}</div></div>
        <div class="kv"><div class="k">Phone</div><div class="v">{{ $booking->client->primary_phone }}</div></div>
        @if($booking->client->email)
            <div class="kv"><div class="k">Email</div><div class="v">{{ $booking->client->email }}</div></div>
        @endif
        @if($booking->client->address_line1)
            <div class="kv"><div class="k">Address</div><div class="v">
                {{ $booking->client->address_line1 }}@if($booking->client->city), {{ $booking->client->city }}@endif
            </div></div>
        @endif
    </td>
    <td class="panel">
        <h3>Unit</h3>
        <div class="kv"><div class="v">{{ $booking->unit->name ?? $booking->unit->code }}</div></div>
        <div class="kv"><div class="k">Code</div><div class="v">{{ $booking->unit->code }}</div></div>
        <div class="kv"><div class="k">Project</div><div class="v">{{ $booking->unit->project->name ?? '—' }}</div></div>
        <div class="kv"><div class="k">Category</div><div class="v">{{ $booking->unit->category->name ?? '—' }}</div></div>
        @if($booking->unit->size_value)
            <div class="kv"><div class="k">Size</div><div class="v">{{ $booking->unit->size_value }} {{ $booking->unit->size_unit }}</div></div>
        @endif
    </td>
    <td class="panel">
        <h3>Booking</h3>
        <div class="kv"><div class="k">Booked on</div><div class="v">{{ $booking->booking_date->format('d M Y') }}</div></div>
        <div class="kv"><div class="k">Plan</div><div class="v">{{ $booking->planTemplate->name ?? '—' }}</div></div>
        <div class="kv"><div class="k">Total price</div><div class="v">Rs. {{ number_format($booking->total_price_minor / 100, 2) }}</div></div>
        <div class="kv"><div class="k">Status</div><div class="v">{{ ucfirst($booking->status) }}</div></div>
    </td>
</tr></table>

<table class="ledger">
    <thead>
        <tr>
            <th style="width: 14%">Date</th>
            <th>Description</th>
            <th class="num" style="width: 14%">Debit</th>
            <th class="num" style="width: 14%">Credit</th>
            <th class="num" style="width: 16%">Balance</th>
        </tr>
    </thead>
    <tbody>
        @foreach($ledger as $row)
            <tr>
                <td>{{ $row['date']?->format('d M Y') }}</td>
                <td>{{ $row['description'] }}<br><span class="muted">{{ $row['category'] }}</span></td>
                <td class="num">{{ $row['debit'] ? 'Rs. ' . number_format($row['debit']/100, 2) : '—' }}</td>
                <td class="num">{{ $row['credit'] ? 'Rs. ' . number_format($row['credit']/100, 2) : '—' }}</td>
                <td class="num">Rs. {{ number_format($row['balance']/100, 2) }}</td>
            </tr>
        @endforeach
        @if(count($ledger) === 0)
            <tr><td colspan="5" style="text-align:center; padding: 24px; color:#6b7280;">No ledger entries yet for this booking.</td></tr>
        @endif
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="label">Total scheduled</td>
        <td class="val">Rs. {{ number_format(array_sum(array_column($ledger, 'debit')) / 100, 2) }}</td>
    </tr>
    <tr>
        <td class="label">Total paid</td>
        <td class="val">Rs. {{ number_format(array_sum(array_column($ledger, 'credit')) / 100, 2) }}</td>
    </tr>
    <tr class="grand">
        <td class="label">Outstanding</td>
        <td class="val">Rs. {{ number_format($outstanding / 100, 2) }}</td>
    </tr>
</table>

<div class="footer">
    Zen Retreats · Barian, Murree, Pakistan · zenretreatspk.com<br>
    For questions, contact us with your booking code <strong>{{ $booking->code }}</strong>.
</div>

</body>
</html>
