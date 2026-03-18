<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Matrix Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin: 18px 0 6px; }
        p.meta { color: #4b5563; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 700; }
    </style>
</head>
<body>
    <h1>Financial Matrix Report</h1>
    <p class="meta">Generated at: {{ $generatedAt }}</p>
    <p class="meta">Period: {{ $snapshot['period']['from'] ?? '-' }} to {{ $snapshot['period']['to'] ?? '-' }}</p>

    <h2>Matrix KPIs</h2>
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Gross Revenue (Range)</td><td>RWF {{ number_format((float) ($snapshot['matrix']['gross_range'] ?? 0), 0) }}</td></tr>
            <tr><td>Gross Revenue (Last 7d)</td><td>RWF {{ number_format((float) ($snapshot['matrix']['gross_7d'] ?? 0), 0) }}</td></tr>
            <tr><td>Platform Commission (Range)</td><td>RWF {{ number_format((float) ($snapshot['matrix']['commission_range'] ?? 0), 0) }}</td></tr>
            <tr><td>Driver Payouts (Range)</td><td>RWF {{ number_format((float) ($snapshot['matrix']['payouts_range'] ?? 0), 0) }}</td></tr>
            <tr><td>Pending Payouts</td><td>{{ number_format((int) ($snapshot['matrix']['pending_payouts'] ?? 0)) }}</td></tr>
            <tr><td>Take Rate</td><td>{{ number_format((float) ($snapshot['matrix']['take_rate'] ?? 0), 2) }}%</td></tr>
        </tbody>
    </table>

    <h2>Daily Financial Rows</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Gross</th>
                <th>Transactions</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($snapshot['daily_rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['day'] ?? '' }}</td>
                    <td>RWF {{ number_format((float) ($row['gross'] ?? 0), 0) }}</td>
                    <td>{{ number_format((int) ($row['txns'] ?? 0)) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No financial rows available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
