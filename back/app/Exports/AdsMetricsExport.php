<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdsMetricsExport
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function download(string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ads Report');

        $data = $this->data;

        // ── Header info ──
        $companyName = $data['company']->company_name ?? 'Ads Analytics';
        $accountName = $data['account']->account_name;
        $platform = ucfirst($data['account']->platform);
        $level = ucfirst($data['level']);
        $period = "{$data['start_date']} s/d {$data['end_date']}";

        $sheet->setCellValue('A1', $companyName);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', "Laporan Performa Iklan — {$platform} Ads");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

        $sheet->setCellValue('A3', "Akun: {$accountName} | Level: {$level} | Periode: {$period}");
        $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true);

        // ── Summary ──
        $summary = $data['summary'];
        $sheet->setCellValue('A5', 'RINGKASAN');
        $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(10);

        $sheet->setCellValue('A6', 'Total Spend');
        $sheet->setCellValue('B6', $summary['total_spend']);
        $sheet->getStyle('B6')->getNumberFormat()->setFormatCode('#,##0');

        $sheet->setCellValue('C6', 'Impressions');
        $sheet->setCellValue('D6', $summary['total_impressions']);
        $sheet->getStyle('D6')->getNumberFormat()->setFormatCode('#,##0');

        $sheet->setCellValue('E6', 'Clicks');
        $sheet->setCellValue('F6', $summary['total_clicks']);
        $sheet->getStyle('F6')->getNumberFormat()->setFormatCode('#,##0');

        $sheet->setCellValue('G6', 'CTR');
        $sheet->setCellValue('H6', $summary['ctr'] . '%');

        // ── Table headings ──
        $headerRow = 8;
        $col = 'A';

        $headings = ['Tanggal', 'Campaign'];
        if (in_array($data['level'], ['adset', 'ad'])) {
            $headings[] = 'Ad Set';
        }
        if ($data['level'] === 'ad') {
            $headings[] = 'Ad';
        }
        $headings = array_merge($headings, ['Spend', 'Impressions', 'Clicks', 'Reach', 'CTR (%)']);

        foreach ($headings as $heading) {
            $sheet->setCellValue($col . $headerRow, $heading);
            $col++;
        }

        $lastCol = chr(ord('A') + count($headings) - 1);

        // Style header row
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 9],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1F2937'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ── Data rows ──
        $rowNum = $headerRow + 1;
        foreach ($data['rows'] as $row) {
            $col = 'A';

            $dateVal = $row->date instanceof \Carbon\Carbon ? $row->date->format('Y-m-d') : ($row->date ?? '');
            $sheet->setCellValue($col++ . $rowNum, $dateVal);
            $sheet->setCellValue($col++ . $rowNum, $row->campaign_name ?? '—');

            if (in_array($data['level'], ['adset', 'ad'])) {
                $sheet->setCellValue($col++ . $rowNum, $row->adset_name ?? '—');
            }
            if ($data['level'] === 'ad') {
                $sheet->setCellValue($col++ . $rowNum, $row->ad_name ?? '—');
            }

            $impressions = (int) $row->impressions;
            $clicks = (int) $row->clicks;
            $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

            $sheet->setCellValue($col++ . $rowNum, (float) $row->spend);
            $sheet->setCellValue($col++ . $rowNum, $impressions);
            $sheet->setCellValue($col++ . $rowNum, $clicks);
            $sheet->setCellValue($col++ . $rowNum, (int) $row->reach);
            $sheet->setCellValue($col++ . $rowNum, $ctr);

            $rowNum++;
        }

        // Borders for data area
        $lastDataRow = $rowNum - 1;
        if ($lastDataRow >= $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFD1D5DB'],
                    ],
                ],
            ]);
        }

        // Alternate row colors
        for ($r = $headerRow + 1; $r <= $lastDataRow; $r++) {
            if (($r - $headerRow) % 2 === 0) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFF9FAFB'],
                    ],
                ]);
            }
        }

        // Auto-size columns
        foreach (range('A', $lastCol) as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // ── Write response ──
        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
