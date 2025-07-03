<?php

namespace App\Exports;

use App\Models\Models\Order;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class OrdersExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    use Exportable;
    
    protected $orders;
    
    public function __construct()
    {
        $this->orders = Order::with('orderType')->get();
    }

    public function collection()
    {
        return $this->orders;
    }

    public function title(): string
    {
        return 'Orders ' . now()->format('Y-m-d');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Order Number',
            'Client Name',
            'Client Email',
            'Client Phone',
            'Article Name',
            'Post URL',
            'Live Link Status',
            'Live Link URL',
            'Order Type',
            'Status',
            'Invoice Status',
            'Client Price',
            'Admin Fee',
            'Net Profit',
            'Admin Invoice URL',
            'Client Invoice URL',
            'Notes',
            'Created At',
            'Updated At',
            'Completed At',
        ];
    }

    public function map($order): array
    {
        return [
            $order->id,
            $order->order_number,
            $order->client_name,
            $order->client_email,
            $order->client_phone,
            $order->article_name,
            $order->post_url,
            $order->live_link_status,
            $order->live_link_url,
            $order->orderType->name ?? 'N/A',
            ucfirst(str_replace('_', ' ', $order->status)),
            ucfirst(str_replace('_', ' ', $order->invoice_status)),
            number_format($order->client_price, 2),
            number_format($order->admin_fee, 2),
            number_format($order->net_profit, 2),
            $order->admin_invoice_url,
            $order->client_invoice_url,
            $order->notes,
            $order->created_at->format('Y-m-d H:i:s'),
            $order->updated_at->format('Y-m-d H:i:s'),
            $order->completed_at ? $order->completed_at->format('Y-m-d H:i:s') : 'N/A',
        ];
    }
    


    public function styles(Worksheet $sheet)
    {
        // Set default font
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        // Title style
        $sheet->getStyle('A1:U1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => '2C3E50'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Header style
        $sheet->getStyle('A2:U2')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2C3E50'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Auto-size all columns
        foreach (range('A', 'U') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set row heights
        $sheet->getRowDimension(1)->setRowHeight(25); // Title row
        $sheet->getRowDimension(2)->setRowHeight(25); // Header row

        // Style for data rows
        $sheet->getStyle('A3:U' . ($this->orders->count() + 2))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'DDDDDD'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ]);

        // Style for numeric columns (client_price, admin_fee, net_profit)
        if ($this->orders->count() > 0) {
            $numericColumns = ['M', 'N', 'O'];
            foreach ($numericColumns as $col) {
                $sheet->getStyle($col . '3:' . $col . ($this->orders->count() + 2))->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        // Style for date columns
        if ($this->orders->count() > 0) {
            $dateColumns = ['S', 'T', 'U'];
            foreach ($dateColumns as $col) {
                $sheet->getStyle($col . '3:' . $col . ($this->orders->count() + 2))->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm:ss');
            }
        }

        // Add alternating row colors
        foreach (range(3, $this->orders->count() + 2) as $row) {
            $fillColor = $row % 2 == 1 ? 'FFFFFF' : 'F8F9FA';
            $sheet->getStyle('A' . $row . ':U' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($fillColor);
        }

        // Set width for specific columns
        $sheet->getColumnDimension('F')->setWidth(40); // Article Name
        $sheet->getColumnDimension('G')->setWidth(30); // Post URL
        $sheet->getColumnDimension('I')->setWidth(30); // Live Link URL
        $sheet->getColumnDimension('P')->setWidth(30); // Admin Invoice URL
        $sheet->getColumnDimension('Q')->setWidth(30); // Client Invoice URL
        $sheet->getColumnDimension('R')->setWidth(40); // Notes

        // Set alignment for specific columns
        $centerColumns = ['A', 'B', 'K', 'L', 'M', 'N', 'O'];
        foreach ($centerColumns as $col) {
            $sheet->getStyle($col . '2:' . $col . ($this->orders->count() + 2))->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Add filter to header row
        $sheet->setAutoFilter('A2:U2');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Add a title and subtitle
                $sheet = $event->sheet;
                
                // Add title
                $sheet->mergeCells('A1:U1');
                $sheet->setCellValue('A1', 'ORDERS EXPORT - ' . strtoupper(now()->format('F j, Y')));
                
                // Add headers in row 2
                $headers = $this->headings();
                foreach ($headers as $key => $value) {
                    $cell = chr(65 + $key) . '2'; // A2, B2, C2, etc.
                    $sheet->setCellValue($cell, $value);
                }
                
                // Style the title
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
                
                // Add export timestamp
                $sheet->setCellValue('A2', 'Exported on: ' . now()->format('Y-m-d H:i:s'));
                $sheet->getStyle('A2')->getFont()->setItalic(true);
                
                // Set the header row to be frozen
                $sheet->freezePane('A3');
                
                // Set print settings
                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                
                // Set print area
                $sheet->getPageSetup()->setPrintArea('A1:U' . ($this->orders->count() + 2));
                
                // Set print titles (rows to repeat at top)
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(3, 3);
            },
        ];
    }
}
