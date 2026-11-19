<?php

namespace App\Exports;

use App\Services\Report\ReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MitraPerformanceExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
        $data = app(ReportService::class)->getMitraPerformanceData($this->startDate, $this->endDate);

        /** @var array<int, array<int, mixed>> $summaryRows */
        $summaryRows = [
            ['LAPORAN KINERJA MITRA SI-CANGKAL'],
            ['Periode:', $this->startDate.' s/d '.$this->endDate],
            [''],
            ['Total Mitra Aktif', $data['total_mitra'].' Mitra'],
            ['Total Trip Diselesaikan', $data['total_trip_completed'].' Trip'],
            [''],
            ['No', 'Nama Mitra', 'Email', 'Total Trip Selesai', 'Total Pendapatan (Rp)'],
        ];

        /** @var array<int, array<string, mixed>> $rawMitras */
        $rawMitras = $data['mitras'] ?? [];

        /** @var array<int, array<int, mixed>> $mitraRows */
        $mitraRows = [];

        foreach ($rawMitras as $index => $m) {
            $mitraRows[] = [
                $index + 1,
                (string) ($m['name'] ?? ''),
                (string) ($m['email'] ?? ''),
                (int) ($m['total_trip'] ?? 0),
                (float) ($m['total_revenue'] ?? 0),
            ];
        }

        return collect(array_merge($summaryRows, $mitraRows));
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
            7 => ['font' => ['bold' => true]],
        ];
    }
}
