<?php

namespace App\Exports;

use App\Services\Report\ReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RevenueExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected string $startDate;

    protected string $endDate;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * @return Collection<int, array<int, mixed>>
     */
    public function collection(): Collection
    {
        $data = app(ReportService::class)->getRevenueData($this->startDate, $this->endDate);

        /** @var array<int, array<int, mixed>> $summaryRows */
        $summaryRows = [
            ['LAPORAN PENDAPATAN SI-CANGKAL'],
            ['Periode:', $this->startDate.' s/d '.$this->endDate],
            [''],
            ['Pendapatan Kotor', 'Rp '.number_format($data['gross_revenue'], 0, ',', '.')],
            ['Total Refund', '- Rp '.number_format($data['total_refund'], 0, ',', '.')],
            ['Pendapatan Bersih', 'Rp '.number_format($data['total_revenue'], 0, ',', '.')],
            [''],
            ['Kode Booking', 'Tanggal', 'Nama Client', 'Paket', 'Total Harga', 'Status'],
        ];

        /** @var array<int, array<string, mixed>> $rawTransactions */
        $rawTransactions = $data['transactions'] ?? [];

        /** @var array<int, array<int, mixed>> $transactionRows */
        $transactionRows = [];

        foreach ($rawTransactions as $t) {
            $transactionRows[] = [
                (string) ($t['kode'] ?? ''),
                Carbon::parse($t['tanggal'] ?? now())->format('Y-m-d'),
                (string) ($t['client'] ?? ''),
                (string) ($t['paket'] ?? ''),
                (string) ($t['total'] ?? ''),
                strtoupper((string) ($t['status'] ?? '')),
            ];
        }

        return collect(array_merge($summaryRows, $transactionRows));
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [];
    }

    /**
     * @param  mixed  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return is_array($row) ? $row : $row->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            8 => ['font' => ['bold' => true]],
        ];
    }
}
