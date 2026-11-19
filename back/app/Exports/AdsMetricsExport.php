<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdsMetricsExport
{
    private const MONEY = '"Rp "#,##0';
    private const INTEGER = '#,##0';
    private const PERCENT = '0.00%';

    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function download(string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();

        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Ringkasan');
        $this->writeSummary($summarySheet);

        $dailySheet = $spreadsheet->createSheet();
        $dailySheet->setTitle('Harian');
        $this->writeDaily($dailySheet);

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function writeSummary(Worksheet $sheet): void
    {
        $data = $this->data;
        $summary = $data['summary'];

        $sheet->setCellValue('A1', $data['company']->company_name ?? 'Ads Analytics');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', "Laporan Performa {$data['platform_label']}");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

        $sheet->setCellValue('A3', "Akun: {$data['account']->account_name} | Level: {$data['level_label']} | Periode: {$data['period_label']}");
        $sheet->getStyle('A3')->getFont()->setSize(9);

        $kpis = [
            [$data['conversion_label'], $summary['total_conversions'], null],
            [$data['cost_per_conversion_label'], $summary['cost_per_conversion'], self::MONEY],
            ['Total spend', $summary['total_spend'], self::MONEY],
            ['Klik', $summary['total_clicks'], self::INTEGER],
            ['CTR', $summary['ctr'] / 100, self::PERCENT],
            ['Biaya per klik', $summary['cpc'], self::MONEY],
            ['Impresi', $summary['total_impressions'], self::INTEGER],
        ];

        $row = 5;
        foreach ($kpis as [$label, $value, $format]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            if ($format) {
                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode($format);
            }
            $row++;
        }
        $sheet->getStyle('B5:B7')->getFont()->setBold(true);

        $row++;
        $sheet->setCellValue("A{$row}", "Total per " . strtolower($data['level_label']));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $rows = $data['totals']->map(fn ($total) => [
            $total->name ?: '-',
            (float) $total->spend,
            (float) $total->conversions,
            $total->conversions > 0 ? $total->spend / $total->conversions : null,
            (int) $total->clicks,
            $total->impressions > 0 ? $total->clicks / $total->impressions : null,
            (int) $total->impressions,
        ])->all();

        $this->writeTable(
            $sheet,
            $row + 1,
            [$data['level_label'], 'Spend', $data['conversion_label'], $data['cost_per_conversion_label'], 'Klik', 'CTR', 'Impresi'],
            [null, self::MONEY, null, self::MONEY, self::INTEGER, self::PERCENT, self::INTEGER],
            $rows,
        );

        $footerRow = $row + count($rows) + 3;
        if ($data['synced_at']) {
            $sheet->setCellValue("A{$footerRow}", "Data terakhir disinkronkan dari {$data['platform_label']}: {$data['synced_at']}");
            $footerRow++;
        }
        $sheet->setCellValue("A{$footerRow}", "Laporan dibuat {$data['generated_at']}");
        $sheet->getStyle("A" . ($footerRow - 1) . ":A{$footerRow}")->getFont()->setSize(9);

        // Merged cells are left out of auto-size, so long title lines don't stretch column A
        foreach ([1, 2, 3, $footerRow - 1, $footerRow] as $line) {
            $sheet->mergeCells("A{$line}:G{$line}");
        }
    }

    private function writeDaily(Worksheet $sheet): void
    {
        $data = $this->data;
        $level = $data['level'];

        $headings = ['Tanggal', 'Campaign'];
        $formats = ['yyyy-mm-dd', null];
        if (in_array($level, ['adset', 'ad'])) {
            $headings[] = 'Ad set';
            $formats[] = null;
        }
        if ($level === 'ad') {
            $headings[] = 'Ad';
            $formats[] = null;
        }
        $headings = array_merge($headings, ['Spend', $data['conversion_label'], $data['cost_per_conversion_label'], 'Klik', 'CTR', 'Impresi']);
        $formats = array_merge($formats, [self::MONEY, null, self::MONEY, self::INTEGER, self::PERCENT, self::INTEGER]);
        if ($data['show_reach']) {
            $headings[] = 'Reach';
            $formats[] = self::INTEGER;
        }

        $rows = $data['rows']->map(function ($row) use ($level, $data) {
            $date = Date::PHPToExcel(new \DateTime(substr((string) $row->date, 0, 10)));
            $cells = [$date, $row->campaign_name ?: '-'];
            if (in_array($level, ['adset', 'ad'])) {
                $cells[] = $row->adset_name ?: '-';
            }
            if ($level === 'ad') {
                $cells[] = $row->ad_name ?: '-';
            }

            $cells = array_merge($cells, [
                (float) $row->spend,
                (float) $row->conversions,
                $row->conversions > 0 ? $row->spend / $row->conversions : null,
                (int) $row->clicks,
                $row->impressions > 0 ? $row->clicks / $row->impressions : null,
                (int) $row->impressions,
            ]);
            if ($data['show_reach']) {
                $cells[] = (int) $row->reach;
            }

            return $cells;
        })->all();

        $this->writeTable($sheet, 1, $headings, $formats, $rows);
        $sheet->freezePane('A2');
    }

    private function writeTable(Worksheet $sheet, int $headerRow, array $headings, array $formats, array $rows): void
    {
        $lastCol = Coordinate::stringFromColumnIndex(count($headings));

        $sheet->fromArray($headings, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 9],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1F2937'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        if ($rows) {
            // strictNullComparison keeps real zeros instead of blanking them
            $sheet->fromArray($rows, null, 'A' . ($headerRow + 1), true);
        }

        $lastDataRow = $headerRow + count($rows);

        foreach ($formats as $index => $format) {
            if ($format && $rows) {
                $col = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->getStyle("{$col}" . ($headerRow + 1) . ":{$col}{$lastDataRow}")->getNumberFormat()->setFormatCode($format);
            }
        }

        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
        ]);

        for ($r = $headerRow + 2; $r <= $lastDataRow; $r += 2) {
            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFF9FAFB');
        }

        foreach (range(1, count($headings)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }
    }
}
