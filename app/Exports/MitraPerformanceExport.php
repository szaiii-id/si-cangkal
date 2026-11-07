<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MitraPerformanceExport implements FromCollection, WithHeadings, WithMapping
{
    protected string $startDate;
    protected string $endDate;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Collection
    {
        return User::where('role', 'mitra')
            ->withCount(['trips as total_trip' => function ($q) {
                $q->where('status', 'completed')
                  ->whereBetween('tanggal_mulai', [$this->startDate, $this->endDate]);
            }])
            ->withSum(['mitraBookings as total_revenue' => function ($q) {
                $q->whereIn('status', ['confirmed', 'completed'])
                  ->whereBetween('created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59']);
            }], 'total_harga')
            ->orderByDesc('total_revenue')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID Mitra',
            'Nama Mitra',
            'Email',
            'Total Trip Selesai',
            'Total Revenue (Rp)',
        ];
    }

    public function map($mitra): array
    {
        return [
            $mitra->id,
            $mitra->name,
            $mitra->email,
            $mitra->total_trip ?? 0,
            (float) ($mitra->total_revenue ?? 0),
        ];
    }
}