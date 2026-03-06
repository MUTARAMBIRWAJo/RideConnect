<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket Invoice #{{ $ticket->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            font-size: 12px;
            margin: 0;
            padding: 24px;
            background: #ffffff;
        }
        .header {
            border-bottom: 2px solid #1f2937;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .brand {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }
        .subtitle {
            margin: 6px 0 0;
            color: #4b5563;
            font-size: 12px;
        }
        .invoice-meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .invoice-meta td {
            padding: 6px 0;
            vertical-align: top;
        }
        .label {
            color: #6b7280;
            width: 160px;
            font-weight: 600;
        }
        .value {
            color: #111827;
            font-weight: 500;
        }
        .section-title {
            margin: 18px 0 8px;
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            border-left: 4px solid #2563eb;
            padding-left: 8px;
        }
        .card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }
        .amount-box {
            margin-top: 18px;
            border: 2px solid #111827;
            border-radius: 8px;
            padding: 14px;
            text-align: right;
            background: #f9fafb;
        }
        .amount-label {
            color: #6b7280;
            font-size: 11px;
            font-weight: 600;
            margin: 0;
        }
        .amount-value {
            margin: 4px 0 0;
            font-size: 24px;
            font-weight: 700;
            color: #111827;
        }
        .status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 14px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: #ffffff;
            background: #6b7280;
        }
        .status-paid { background: #16a34a; }
        .status-open, .status-pending { background: #d97706; }
        .status-cancelled { background: #dc2626; }
        .footer {
            margin-top: 26px;
            color: #6b7280;
            font-size: 10px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    @php
        $status = strtolower(trim((string) $ticket->status));
        $statusClass = match ($status) {
            'paid' => 'status-paid',
            'open' => 'status-open',
            'pending' => 'status-pending',
            'cancelled' => 'status-cancelled',
            default => '',
        };

        $passengerName = $passenger?->full_name ?? trim(($passenger?->first_name ?? '') . ' ' . ($passenger?->last_name ?? ''));
        if ($passengerName === '') {
            $passengerName = 'N/A';
        }
    @endphp

    <div class="header">
        <h1 class="brand">RideConnect</h1>
        <p class="subtitle">Official Ticket Invoice</p>
    </div>

    <table class="invoice-meta">
        <tr>
            <td class="label">Invoice Number</td>
            <td class="value">INV-TKT-{{ str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT) }}</td>
        </tr>
        <tr>
            <td class="label">Ticket ID</td>
            <td class="value">#{{ $ticket->id }}</td>
        </tr>
        <tr>
            <td class="label">Issue Date</td>
            <td class="value">{{ optional($ticket->issued_at ?? $ticket->created_at)->format('Y-m-d H:i') ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td class="value">
                <span class="status {{ $statusClass }}">{{ strtoupper((string) $ticket->status) }}</span>
            </td>
        </tr>
    </table>

    <div class="section-title">Passenger Details</div>
    <div class="card">
        <strong>Name:</strong> {{ $passengerName }}<br>
        <strong>Email:</strong> {{ $passenger?->email ?? 'N/A' }}<br>
        <strong>Phone:</strong> {{ $passenger?->phone ?? 'N/A' }}
    </div>

    <div class="section-title">Trip Details</div>
    <div class="card">
        <strong>Trip ID:</strong> {{ $trip?->id ?? 'N/A' }}<br>
        <strong>Pickup:</strong> {{ $trip?->pickup_location ?? 'N/A' }}<br>
        <strong>Dropoff:</strong> {{ $trip?->dropoff_location ?? 'N/A' }}
    </div>

    <div class="section-title">Ticket Reason</div>
    <div class="card">
        {{ $ticket->reason }}
    </div>

    <div class="amount-box">
        <p class="amount-label">TOTAL AMOUNT DUE</p>
        <p class="amount-value">RWF {{ number_format((float) $ticket->amount, 2) }}</p>
    </div>

    <div class="section-title">Issued By</div>
    <div class="card">
        {{ $issuer?->name ?? 'System' }}
    </div>

    <div class="footer">
        This invoice is system-generated and valid without a signature.
    </div>
</body>
</html>
