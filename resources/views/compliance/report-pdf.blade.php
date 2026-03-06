<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $reportTitle }} — RideConnect</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #166534;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .brand { font-size: 18px; font-weight: bold; color: #166534; }
        .brand-sub { font-size: 9px; color: #555; margin-top: 2px; }
        .report-meta { text-align: right; }
        .report-meta .title { font-size: 13px; font-weight: bold; color: #166534; }
        .report-meta .period { font-size: 9px; color: #666; margin-top: 3px; }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            margin: 16px 0 6px;
            padding-bottom: 3px;
            border-bottom: 1px solid #d1fae5;
            color: #166534;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 9px;
        }
        thead tr { background-color: #166534; color: #fff; }
        thead th { padding: 5px 6px; text-align: left; }
        tbody tr:nth-child(even) { background-color: #f0fdf4; }
        tbody td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }

        .summary-grid {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }
        .summary-card {
            border: 1px solid #d1fae5;
            border-radius: 4px;
            padding: 8px 12px;
            min-width: 120px;
        }
        .summary-card .label { font-size: 8px; color: #666; text-transform: uppercase; }
        .summary-card .value { font-size: 13px; font-weight: bold; color: #166534; margin-top: 2px; }

        .footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #d1fae5;
            font-size: 8px;
            color: #888;
            display: flex;
            justify-content: space-between;
        }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 9999px;
            font-size: 8px;
            font-weight: bold;
        }
        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-warning { background-color: #fef9c3; color: #854d0e; }
        .badge-danger  { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    {{-- ===== HEADER ===== --}}
    <div class="header">
        <div>
            <div class="brand">RideConnect</div>
            <div class="brand-sub">Compliance & Finance Division</div>
        </div>
        <div class="report-meta">
            <div class="title">{{ $reportTitle }}</div>
            <div class="period">
                Period: {{ $periodFrom }} — {{ $periodTo }}
                &nbsp;|&nbsp; Generated: {{ $generatedAt }}
                &nbsp;|&nbsp; Currency: RWF
            </div>
        </div>
    </div>

    {{-- ===== SUMMARY CARDS ===== --}}
    @if (! empty($summaryData))
        <div class="section-title">Summary</div>
        <div class="summary-grid">
            @foreach ($summaryData as $key => $value)
                <div class="summary-card">
                    <div class="label">{{ str_replace('_', ' ', $key) }}</div>
                    <div class="value">{{ is_numeric($value) ? number_format($value, 2) : $value }}</div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ===== DATA TABLE ===== --}}
    @if (! empty($rows))
        <div class="section-title">Detail Records</div>
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($columns as $col)
                            <td>{{ $row[$col] ?? '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" style="text-align:center; color:#999; padding:12px;">
                            No records found for the selected period.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    {{-- ===== FOOTER ===== --}}
    <div class="footer">
        <span>Report ID: {{ $reportId }} &nbsp;|&nbsp; Type: {{ $reportType }}</span>
        <span>RideConnect Ltd — Confidential — For regulatory use only</span>
    </div>

</body>
</html>
