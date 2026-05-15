<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Possession Letter — {{ $booking->code }}</title>
    <style>
        @page { margin: 28mm 22mm 26mm 22mm; }
        body {
            font-family: 'Helvetica', sans-serif;
            color: #1f2937;
            font-size: 11pt;
            line-height: 1.55;
        }
        .header { border-bottom: 3px solid {{ $branding['primary'] }}; padding-bottom: 14px; margin-bottom: 24px; }
        .brand { color: {{ $branding['primary'] }}; font-size: 22pt; font-weight: bold; letter-spacing: 0.5px; }
        .brand-sub { color: {{ $branding['accent'] }}; font-size: 9pt; text-transform: uppercase; letter-spacing: 2px; }
        .doc-title { float: right; text-align: right; }
        .doc-title h1 { margin: 0; font-size: 16pt; color: #111827; }
        .doc-title .meta { color: #6b7280; font-size: 9pt; }
        .clear { clear: both; }

        h2 { font-size: 14pt; color: #111827; margin: 18px 0 8px; }
        .ref-block { float: right; font-size: 9.5pt; color: #4b5563; text-align: right; }
        .salutation { margin: 20px 0 10px; }
        p { margin: 8px 0; }
        .panel {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-left: 4px solid {{ $branding['primary'] }};
            padding: 12px 16px;
            margin: 14px 0;
        }
        .panel h3 { margin: 0 0 6px; font-size: 9pt; text-transform: uppercase; letter-spacing: 1.4px; color: #6b7280; }
        .panel table { width: 100%; }
        .panel table td { padding: 3px 8px 3px 0; font-size: 10.5pt; vertical-align: top; }
        .panel table td.label { color: #6b7280; font-size: 9pt; width: 38%; }

        .signature-block {
            margin-top: 60px;
            width: 100%;
        }
        .signature-block td {
            width: 50%;
            padding-top: 40px;
            border-top: 1px solid #6b7280;
            vertical-align: top;
            font-size: 9.5pt;
            color: #4b5563;
        }
        .signature-block td.left { padding-right: 30px; }
        .signature-block td.right { padding-left: 30px; }

        .stamp {
            position: absolute;
            top: 240px;
            right: 30px;
            border: 3px solid {{ $branding['primary'] }};
            color: {{ $branding['primary'] }};
            padding: 12px 18px;
            transform: rotate(-12deg);
            font-weight: bold;
            font-size: 14pt;
            letter-spacing: 2px;
            opacity: 0.45;
        }

        .footer {
            position: fixed; bottom: -18mm; left: 0; right: 0;
            font-size: 8pt; color: #6b7280; text-align: center;
            border-top: 1px solid #e5e7eb; padding-top: 6px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $branding['company'] ?? $app_name }}</div>
        <div class="brand-sub">{{ $branding['tagline'] ?? '' }}</div>
        <div class="doc-title">
            <h1>POSSESSION LETTER</h1>
            <div class="meta">Ref: PL/{{ $booking->code }}/{{ $generated_at->format('Y') }}</div>
            <div class="meta">Issued: {{ $generated_at->format('d F Y') }}</div>
        </div>
        <div class="clear"></div>
    </div>

    <div style="float:right; text-align:right; font-size:10pt; color:#374151;">
        Booking: <strong>{{ $booking->code }}</strong><br>
        Date: {{ $generated_at->format('d F Y') }}
    </div>
    <div class="clear"></div>

    <p class="salutation">
        <strong>{{ $booking->client->full_name }}</strong><br>
        @if ($booking->client->address_line1) {{ $booking->client->address_line1 }}<br> @endif
        @if ($booking->client->address_line2) {{ $booking->client->address_line2 }}<br> @endif
        @if ($booking->client->city) {{ $booking->client->city }}, @endif {{ $booking->client->country ?? 'Pakistan' }}
    </p>

    <p>Dear {{ $booking->client->full_name }},</p>

    <p>
        We are pleased to confirm that all financial obligations against the
        below-mentioned unit have been satisfied in full, and the unit is
        hereby handed over to you for possession with effect from
        <strong>{{ $generated_at->format('d F Y') }}</strong>.
    </p>

    <div class="panel">
        <h3>Unit details</h3>
        <table>
            <tr><td class="label">Project</td><td>{{ $booking->unit->project->name ?? '—' }}</td></tr>
            <tr><td class="label">Unit code</td><td><strong>{{ $booking->unit->code }}</strong></td></tr>
            <tr><td class="label">Type</td><td>{{ $booking->unit->category->name ?? '—' }}</td></tr>
            @if ($booking->unit->size_value)
            <tr><td class="label">Size</td><td>{{ rtrim(rtrim($booking->unit->size_value, '0'), '.') }} {{ $booking->unit->size_unit }}</td></tr>
            @endif
            <tr><td class="label">Booking date</td><td>{{ $booking->booking_date?->format('d F Y') }}</td></tr>
        </table>
    </div>

    <div class="panel">
        <h3>Financial summary</h3>
        <table>
            <tr><td class="label">Total contract value</td><td>{{ money_format_pkr($booking->total_price_minor) }}</td></tr>
            <tr><td class="label">Total paid</td><td>{{ money_format_pkr($paid_minor) }}</td></tr>
            <tr><td class="label">Outstanding</td><td><strong>{{ money_format_pkr($outstanding_minor) }}</strong></td></tr>
        </table>
    </div>

    <p>
        Kindly retain this letter for your records. The keys and access
        credentials for the unit will be handed over upon presentation of this
        letter along with a valid CNIC. Any maintenance, utility, or society
        charges accruing from the date of possession onwards shall be borne by you.
    </p>

    <p>
        We thank you for your trust in {{ $branding['company'] ?? $app_name }} and look forward to a continued relationship.
    </p>

    <p>Sincerely,</p>

    <table class="signature-block">
        <tr>
            <td class="left">
                <strong>For {{ $branding['company'] ?? $app_name }}</strong><br>
                Authorised signatory
            </td>
            <td class="right">
                <strong>Received by:</strong> {{ $booking->client->full_name }}<br>
                CNIC: ____________________________
            </td>
        </tr>
    </table>

    <div class="footer">
        This is a system-generated document from {{ $app_name }}.
        For verification, contact {{ $branding['email'] ?? 'sales@zenretreatspk.com' }}.
    </div>
</body>
</html>
