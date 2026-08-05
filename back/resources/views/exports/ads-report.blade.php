<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Performa Iklan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1f2937;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 2px;
        }
        .header h2 {
            font-size: 13px;
            color: #3b82f6;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .header .meta {
            font-size: 10px;
            color: #6b7280;
        }
        .summary {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .summary-card {
            display: table-cell;
            width: 25%;
            padding: 10px 12px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        .summary-card .label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .summary-card .value {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }
        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data-table thead th {
            background: #1f2937;
            color: #ffffff;
            padding: 8px 6px;
            text-align: left;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.data-table thead th.text-right {
            text-align: right;
        }
        table.data-table tbody td {
            padding: 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }
        table.data-table tbody td.text-right {
            text-align: right;
        }
        table.data-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            font-size: 8px;
            color: #9ca3af;
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            background: #dbeafe;
            color: #1d4ed8;
            border-radius: 4px;
            font-size: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    {{-- HEADER --}}
    <div class="header">
        <h1>{{ $company->company_name ?? 'Ads Analytics' }}</h1>
        <h2>Laporan Performa Iklan — {{ ucfirst($account->platform) }} Ads</h2>
        <div class="meta">
            Akun: <strong>{{ $account->account_name }}</strong> |
            Level: <strong>{{ ucfirst($level) }}</strong> |
            Periode: <strong>{{ $start_date }} s/d {{ $end_date }}</strong> |
            Digenerate: {{ now()->format('d M Y H:i') }}
        </div>
    </div>

    {{-- SUMMARY --}}
    <div class="summary">
        <div class="summary-card">
            <div class="label">Total Spend</div>
            <div class="value">Rp {{ number_format($summary['total_spend'], 0, ',', '.') }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Impressions</div>
            <div class="value">{{ number_format($summary['total_impressions'], 0, ',', '.') }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Clicks</div>
            <div class="value">{{ number_format($summary['total_clicks'], 0, ',', '.') }}</div>
        </div>
        <div class="summary-card">
            <div class="label">CTR</div>
            <div class="value">{{ $summary['ctr'] }}%</div>
        </div>
    </div>

    {{-- DETAIL TABLE --}}
    <div class="section-title">Detail per {{ ucfirst($level) }}</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Campaign</th>
                @if(in_array($level, ['adset', 'ad']))
                    <th>Ad Set</th>
                @endif
                @if($level === 'ad')
                    <th>Ad</th>
                @endif
                <th class="text-right">Spend</th>
                <th class="text-right">Impr.</th>
                <th class="text-right">Clicks</th>
                <th class="text-right">Reach</th>
                <th class="text-right">CTR</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->date instanceof \Carbon\Carbon ? $row->date->format('Y-m-d') : $row->date }}</td>
                    <td>{{ $row->campaign_name ?? '—' }}</td>
                    @if(in_array($level, ['adset', 'ad']))
                        <td>{{ $row->adset_name ?? '—' }}</td>
                    @endif
                    @if($level === 'ad')
                        <td>{{ $row->ad_name ?? '—' }}</td>
                    @endif
                    <td class="text-right">Rp {{ number_format($row->spend, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row->impressions, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row->clicks, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row->reach, 0, ',', '.') }}</td>
                    <td class="text-right">
                        <span class="badge">
                            {{ $row->impressions > 0 ? number_format(($row->clicks / $row->impressions) * 100, 2) : '0.00' }}%
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px; color: #9ca3af;">
                        Tidak ada data untuk periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- FOOTER --}}
    <div class="footer">
        {{ $company->company_name ?? 'Ads Analytics' }} &mdash; Laporan ini digenerate secara otomatis pada {{ now()->format('d M Y, H:i') }} WIB
    </div>
</body>
</html>
