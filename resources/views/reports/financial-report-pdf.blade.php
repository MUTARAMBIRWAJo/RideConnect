<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $reportTitle }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
            margin: 0;
            padding: 18px;
        }
        .header {
            border-bottom: 2px solid #166534;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-left {
            width: 60%;
            vertical-align: top;
        }
        .header-right {
            width: 40%;
            text-align: right;
            vertical-align: top;
            font-size: 9px;
        }
        .logo {
            height: 36px;
            margin-bottom: 6px;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            color: #166534;
        }
        .meta-line {
            margin-top: 3px;
        }
        .summary {
            margin-bottom: 12px;
        }
        .summary table,
        .section table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary td {
            border: 1px solid #d1d5db;
            padding: 6px;
        }
        .summary .label {
            width: 45%;
            background: #f9fafb;
            font-weight: bold;
        }
        .section {
            margin-top: 14px;
        }
        .section h3 {
            margin: 0 0 6px;
            color: #166534;
            font-size: 12px;
        }
        .section th,
        .section td {
            border: 1px solid #d1d5db;
            padding: 5px;
        }
        .section th {
            background: #f3f4f6;
            text-align: left;
        }
        .notes {
            margin-top: 12px;
            font-size: 9px;
            color: #374151;
        }
        .footer {
            margin-top: 16px;
            border-top: 1px solid #d1d5db;
            padding-top: 6px;
            font-size: 8px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-left">
                    @if (! empty($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="RideConnect Logo" class="logo">
                    @endif
                    <div class="title">{{ $reportTitle }}</div>
                    <div class="meta-line">RideConnect Financial Reporting</div>
                </td>
                <td class="header-right">
                    <div class="meta-line"><strong>Printed By:</strong> {{ $printedBy }}</div>
                    <div class="meta-line"><strong>Printed At:</strong> {{ $printedAt }}</div>
                    <div class="meta-line"><strong>Period:</strong> {{ $periodFrom }} to {{ $periodTo }}</div>
                    <div class="meta-line"><strong>Currency:</strong> RWF</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="summary">
        <table>
            @foreach ($summary as $label => $value)
                <tr>
                    <td class="label">{{ $label }}</td>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    @foreach ($sections as $section)
        <div class="section">
            <h3>{{ $section['title'] }}</h3>
            <table>
                <thead>
                    <tr>
                        @foreach ($section['columns'] as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section['rows'] as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($section['columns']) }}">No data in selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    @if (! empty($notes))
        <div class="notes">
            <strong>Notes:</strong>
            <ul>
                @foreach ($notes as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="footer">
        This document was system-generated from ledger and financial operational data.
    </div>
</body>
</html>
