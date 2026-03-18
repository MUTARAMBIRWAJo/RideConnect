<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Operations Intelligence Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin: 18px 0 6px; }
        p.meta { color: #4b5563; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 700; }
        .grid { width: 100%; }
        .grid td { width: 50%; vertical-align: top; }
        .kpi { font-size: 13px; font-weight: 700; margin-bottom: 8px; }
    </style>
</head>
<body>
    <h1>Operations Intelligence Report</h1>
    <p class="meta">Generated at: {{ $generatedAt }}</p>
    <p class="meta">Period: {{ $snapshot['period']['from'] ?? '-' }} to {{ $snapshot['period']['to'] ?? '-' }}</p>

    <h2>KPIs</h2>
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($snapshot['kpis'] ?? []) as $metric => $value)
                <tr>
                    <td>{{ str_replace('_', ' ', ucfirst((string) $metric)) }}</td>
                    <td>{{ number_format((int) $value) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Daily Trend (Last 7 Days)</h2>
    <table>
        <thead>
            <tr>
                <th>Day</th>
                <th>Rides</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($snapshot['daily_trend']['labels'] ?? []) as $i => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ (int) (($snapshot['daily_trend']['values'][$i] ?? 0)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Status Mix</h2>
    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>Count</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($snapshot['status_mix']['labels'] ?? []) as $i => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ (int) (($snapshot['status_mix']['values'][$i] ?? 0)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Top Routes</h2>
    <table>
        <thead>
            <tr>
                <th>From</th>
                <th>To</th>
                <th>Trips</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($snapshot['top_routes'] ?? []) as $route)
                <tr>
                    <td>{{ $route['from'] ?? '' }}</td>
                    <td>{{ $route['to'] ?? '' }}</td>
                    <td>{{ (int) ($route['total'] ?? 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No route data available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
