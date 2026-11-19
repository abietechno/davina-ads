<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Performa {{ $platform_label }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        /* DejaVu Sans ships with dompdf and only has normal and bold; other weights fall back to a serif */
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1f2937;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .header h1 {
            font-size: 18px;
            margin-bottom: 2px;
        }
        .header h2 {
            font-size: 13px;
            font-weight: bold;
            color: #1d4ed8;
            margin-bottom: 4px;
        }
        .meta {
            font-size: 10px;
            color: #6b7280;
        }
        .kpi {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .kpi td {
            width: 33.33%;
            padding: 10px 14px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
        }
        .kpi .label {
            font-size: 10px;
            color: #6b7280;
            margin-bottom: 2px;
        }
        .kpi .value {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
        }
        .secondary {
            font-size: 10px;
            color: #374151;
            margin-bottom: 20px;
        }
        .secondary strong {
            color: #111827;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 6px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data-table thead th {
            background: #1f2937;
            color: #ffffff;
            padding: 7px 6px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
        }
        table.data-table tbody td {
            padding: 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }
        table.data-table .num {
            text-align: right;
            white-space: nowrap;
        }
        table.data-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        .empty {
            text-align: center;
            padding: 20px;
            color: #6b7280;
        }
        .footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            font-size: 8px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $rp = fn ($v) => $v === null ? '-' : 'Rp ' . number_format($v, 0, ',', '.');
        $num = fn ($v) => number_format((float) $v, 0, ',', '.');
        // Google can report fractional conversions under data-driven attribution
        $conv = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 1, ',', '.');
        $ctr = fn ($clicks, $impressions) => $impressions > 0 ? number_format($clicks / $impressions * 100, 2, ',', '.') . '%' : '-';
        $perConv = fn ($spend, $conversions) => $conversions > 0 ? $rp($spend / $conversions) : '-';
        $detailColumns = 8 + ($show_reach ? 1 : 0) + (in_array($level, ['adset', 'ad']) ? 1 : 0) + ($level === 'ad' ? 1 : 0);
    @endphp

    <div class="header">
        <h1>{{ $company->company_name ?? 'Ads Analytics' }}</h1>
        <h2>Laporan Performa {{ $platform_label }}</h2>
        <div class="meta">
            Akun: <strong>{{ $account->account_name }}</strong> |
            Level: <strong>{{ $level_label }}</strong> |
            Periode: <strong>{{ $period_label }}</strong>
        </div>
    </div>

    <table class="kpi">
        <tr>
            <td>
                <div class="label">{{ $conversion_label }}</div>
                <div class="value">{{ $conv($summary['total_conversions']) }}</div>
            </td>
            <td>
                <div class="label">{{ $cost_per_conversion_label }}</div>
                <div class="value">{{ $rp($summary['cost_per_conversion']) }}</div>
            </td>
            <td>
                <div class="label">Total spend</div>
                <div class="value">{{ $rp($summary['total_spend']) }}</div>
            </td>
        </tr>
    </table>
    <div class="secondary">
        Klik: <strong>{{ $num($summary['total_clicks']) }}</strong> |
        CTR: <strong>{{ $ctr($summary['total_clicks'], $summary['total_impressions']) }}</strong> |
        Biaya per klik: <strong>{{ $rp($summary['cpc']) }}</strong> |
        Impresi: <strong>{{ $num($summary['total_impressions']) }}</strong>
    </div>

    <div class="section-title">Total per {{ strtolower($level_label) }}</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 34%;">{{ $level_label }}</th>
                <th class="num">Spend</th>
                <th class="num">{{ $conversion_label }}</th>
                <th class="num">{{ $cost_per_conversion_label }}</th>
                <th class="num">Klik</th>
                <th class="num">CTR</th>
                <th class="num">Impresi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($totals as $total)
                <tr>
                    <td>{{ $total->name ?: '-' }}</td>
                    <td class="num">{{ $rp($total->spend) }}</td>
                    <td class="num">{{ $conv($total->conversions) }}</td>
                    <td class="num">{{ $perConv($total->spend, $total->conversions) }}</td>
                    <td class="num">{{ $num($total->clicks) }}</td>
                    <td class="num">{{ $ctr($total->clicks, $total->impressions) }}</td>
                    <td class="num">{{ $num($total->impressions) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">Tidak ada iklan yang tayang di periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Rincian harian</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Campaign</th>
                @if(in_array($level, ['adset', 'ad']))
                    <th>Ad set</th>
                @endif
                @if($level === 'ad')
                    <th>Ad</th>
                @endif
                <th class="num">Spend</th>
                <th class="num">{{ $conversion_label }}</th>
                <th class="num">{{ $cost_per_conversion_label }}</th>
                <th class="num">Klik</th>
                <th class="num">CTR</th>
                <th class="num">Impresi</th>
                @if($show_reach)
                    <th class="num">Reach</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td style="white-space: nowrap;">{{ \Illuminate\Support\Carbon::parse($row->date)->locale('id')->translatedFormat('j M Y') }}</td>
                    <td>{{ $row->campaign_name ?: '-' }}</td>
                    @if(in_array($level, ['adset', 'ad']))
                        <td>{{ $row->adset_name ?: '-' }}</td>
                    @endif
                    @if($level === 'ad')
                        <td>{{ $row->ad_name ?: '-' }}</td>
                    @endif
                    <td class="num">{{ $rp($row->spend) }}</td>
                    <td class="num">{{ $conv($row->conversions) }}</td>
                    <td class="num">{{ $perConv($row->spend, $row->conversions) }}</td>
                    <td class="num">{{ $num($row->clicks) }}</td>
                    <td class="num">{{ $ctr($row->clicks, $row->impressions) }}</td>
                    <td class="num">{{ $num($row->impressions) }}</td>
                    @if($show_reach)
                        <td class="num">{{ $num($row->reach) }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $detailColumns }}" class="empty">Tidak ada iklan yang tayang di periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        @if($synced_at)
            Data terakhir disinkronkan dari {{ $platform_label }}: {{ $synced_at }}.
        @endif
        Laporan dibuat {{ $generated_at }}.
    </div>
</body>
</html>
