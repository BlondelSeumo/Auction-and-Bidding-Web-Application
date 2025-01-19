<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeWriting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BalanceSheetExport implements FromArray, WithEvents, WithHeadings, WithStyles, WithColumnWidths, WithCustomStartCell
{
    /**
     * @return \Illuminate\Support\Collection
     */

    public function __construct($data, $startDate, $endDate, $companyName)
    {

        $formattedData = [];
        $liabilitiesOrEquityEncountered = false;
        $liabilitiesOrEquityEncountered1 = false;
        $liabilities = false;
        
        $assets = false;

        foreach ($data as $category => $subCategories) {
            $total = 0;
            $amountTotal = 0;
            foreach ($subCategories as $subkey => $subCategory) {

                foreach ($subCategory['account'] as $account) {
                    foreach ($account as $key => $record) {

                    if ($record['netAmount'] != null) {
                        if ($category == 'Liabilities' || $category == 'Equity') {
                            if (!$liabilitiesOrEquityEncountered) {
                                $formattedData[] = [
                                    'Account Name' => 'Liabilities & Equity',
                                    'Account No' => '',
                                    'Total' => '',
                                ];
                                $liabilitiesOrEquityEncountered = true;
                            }

                            if (!$liabilities) {

                                $formattedData[] = [
                                    'Account Name' => '  ' . $category,
                                    'Account No' => '',
                                    'Total' => '',

                                ];
                                $liabilities = true;

                            }
                        } else {
                            if (!$assets) {

                                $formattedData[] = [
                                    'Account Name' => $category,
                                    'Account No' => '',
                                    'Total' => '',

                                ];
                                $assets = true;
                            }
                        }
                    }
                }
            }
                break;
            }

            foreach ($subCategories as $subkey => $subCategory) {

                foreach ($subCategory['account'] as $account) {
                    if ($account != []) {
                        $formattedData[] = [
                            'Account Name' => '    ' . $subCategory['subType'],
                            'Account No' => '',
                            'Total' => '',
                        ];
                        break;
                    }
            }

                foreach ($subCategory['account'] as $key => $account) {
                    foreach ($account as $key => $record) {

                    if ($record['netAmount'] != null && $record['account_name'] == 'Total ' . $subCategory['subType']) {
                        $formattedData[] = [
                            'Account Name' => '    ' . $record['account_name'],
                            'Account No' => $record['account_code'],
                            'Total' => $record['netAmount'],
                        ];

                        $amountTotal += $record['netAmount'];
                    } 
                    elseif ($record['account'] == 'parent' || $record['account'] == 'parentTotal') {
                        $formattedData[] = [
                            'Account Name' => '       ' . $record['account_name'],
                            'Account No' => $record['account_code'],
                            'Total' => $record['netAmount'],
                        ];
                    }
                    elseif ($record['netAmount'] != null && !preg_match('/\bTotal\b/i', $record['account_name'])) {
                        $formattedData[] = [
                            'Account Name' => '         ' . $record['account_name'],
                            'Account No' => $record['account_code'],
                            'Total' => $record['netAmount'],
                        ];

                    }
                }
                }

            }

            if (($category == 'Liabilities' && $amountTotal != 0) || ($category == 'Equity' && $amountTotal != 0)) {
                $formattedData[] = [
                    'Account Name' => '  Total ' . $category,
                    'Account No' => '',
                    'Total' => $amountTotal,
                ];
            } else {
                if ($amountTotal != 0) {
                    $formattedData[] = [
                        'Account Name' => 'Total ' . $category,
                        'Account No' => '',
                        'Total' => $amountTotal,
                    ];
                }
            }
        }
        foreach ($formattedData as $a) {
            if ($a['Account Name'] == '  Total Liabilities' || $a['Account Name'] == '  Total Equity') {
                $total += $a['Total'];
            }
        }

        if (!$liabilitiesOrEquityEncountered1) {
            $formattedData[] = [
                'Account Name' => 'Total Liabilities & Equity',
                'Account No' => '',
                'Total' => $total,
            ];
            $liabilitiesOrEquityEncountered1 = true;

        }
        $this->data = $formattedData;
        $this->companyName = $companyName;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 15,
            'C' => 15,
            'D' => 15,

        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A5')->getFont()->setBold(true);
        $sheet->getStyle('B5')->getFont()->setBold(true);
        $sheet->getStyle('C5')->getFont()->setBold(true);
        $sheet->getStyle('D5')->getFont()->setBold(true);
        $sheet->getStyle('E5')->getFont()->setBold(true);
        $sheet->getStyle('F5')->getFont()->setBold(true);
    }

    public function array(): array
    {
        return $this->data;
    }

    public function registerEvents(): array
    {
        return [
            BeforeWriting::class => function (BeforeWriting $event) {

            },

            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->mergeCells('A1:F1');
                $event->sheet->getDelegate()->mergeCells('A2:F2');
                $event->sheet->getDelegate()->mergeCells('A3:F3');

                $event->sheet->getDelegate()->setCellValue('A1', 'Balance Sheet - ' . $this->companyName)->getStyle('A1')->getFont()->setBold(true);
                $event->sheet->getDelegate()->setCellValue('A2', 'Print Out Date : ' . date('Y-m-d H:i'));
                $event->sheet->getDelegate()->setCellValue('A3', 'Date : ' . $this->startDate . ' - ' . $this->endDate);

                $event->sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $event->sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $event->sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $event->sheet->getDelegate()->getStyle('A')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $event->sheet->getDelegate()->getStyle('B')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

                $data = $this->data;
                foreach ($data as $index => $row) {

                    if (isset($row['Account Name']) && ($row['Account Name'] == '  Total Liabilities' || $row['Account Name'] == 'Assets' ||
                        $row['Account Name'] == 'Total Assets' || $row['Account Name'] == '  Liabilities' || $row['Account Name'] == '  Equity' ||
                        $row['Account Name'] == '  Total Equity' || $row['Account Name'] == 'Liabilities & Equity' || $row['Account Name'] == 'Total Liabilities & Equity' ||
                        preg_match('/\bTotal\b/i', $row['Account Name']))) {
                        $rowIndex = $index + 6; // Adjust for 1-based indexing and header row
                        $event->sheet->getStyle('A' . $rowIndex . ':C' . $rowIndex)
                            ->applyFromArray([
                                'font' => [
                                    'bold' => true,
                                ],
                            ]);
                    }
                }
            },
        ];
    }

    public function headings(): array
    {
        return [
            "Account",
            "Account No",
            "Total",
        ];
    }
}
