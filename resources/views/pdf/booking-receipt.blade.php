<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Receipt #{{ $booking->id }}</title>
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
        .status-completed { background: #16a34a; }
        .status-confirmed { background: #2563eb; }
        .status-pending { background: #d97706; }
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
        $status = strtolower(trim((string) $booking->status));
        $statusClass = match ($status) {
            'completed' => 'status-completed',
            'confirmed' => 'status-confirmed',
            'pending' => 'status-pending',
            'cancelled' => 'status-cancelled',
            default => '',
        };

        $userName = $user?->name ?? trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''));
        if ($userName === '') {
            $userName = 'N/A';
        }
    @endphp

    <div class="header">
        <h1 class="brand">RideConnect</h1>
        <p class="subtitle">Official Booking Receipt</p>
    </div>

    <table class="invoice-meta">
        <tr>
            <td class="label">Receipt Number</td>
            <td class="value">REC-BKG-{{ str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT) }}</td>
        </tr>
        <tr>
            <td class="label">Booking ID</td>
            <td class="value">#{{ $booking->id }}</td>
        </tr>
        <tr>
            <td class="label">Date</td>
            <td class="value">{{ optional($booking->created_at)->format('Y-m-d H:i') ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td class="value">
                <span class="status {{ $statusClass }}">{{ strtoupper((string) $booking->status) }}</span>
            </td>
        </tr>
    </table>

    <div class="section-title">Passenger Details</div>
    <div class="card">
        <strong>Name:</strong> {{ $userName }}<br>
        <strong>Email:</strong> {{ $user?->email ?? 'N/A' }}<br>
        <strong>Phone:</strong> {{ $user?->phone ?? 'N/A' }}
    </div>

    <div class="section-title">Ride Details</div>
    <div class="card">
        <strong>Ride ID:</strong> {{ $ride?->id ?? 'N/A' }}<br>
        <strong>Pickup:</strong> {{ $booking->pickup_address ?? $ride?->origin_address ?? 'N/A' }}<br>
        <strong>Dropoff:</strong> {{ $booking->dropoff_address ?? $ride?->destination_address ?? 'N/A' }}<br>
        <strong>Seats Booked:</strong> {{ $booking->seats_booked ?? 'N/A' }}
    </div>

    <div class="amount-box">
        <p class="amount-label">TOTAL AMOUNT</p>
        <p class="amount-value">{{ $booking->currency ?? 'RWF' }} {{ number_format((float) $booking->total_price, 2) }}</p>
    </div>

    <div class="footer">
        This receipt is system-generated and valid without a signature.
    </div>
</body>
</html>
