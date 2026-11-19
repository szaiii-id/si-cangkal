<?php

namespace App\Models;

use Database\Factories\PackageDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model PackageDay — itinerary per hari dalam sebuah paket.
 *
 * @property int $id
 * @property int $package_id
 * @property int $hari_ke
 * @property string $judul
 * @property string|null $deskripsi
 * @property string|null $jam_mulai
 * @property string|null $jam_selesai
 * @property string|null $lokasi
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Package|null $package
 *
 * @use HasFactory<PackageDayFactory>
 */
class PackageDay extends Model
{
    /** @use HasFactory<PackageDayFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'package_id',
        'hari_ke',
        'judul',
        'deskripsi',
        'jam_mulai',
        'jam_selesai',
        'lokasi',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hari_ke' => 'integer',
        ];
    }

    // ==========================================
    // RELASI
    // ==========================================

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    // ==========================================
    // HELPER
    // ==========================================

    public function jamMulaiShort(): ?string
    {
        if ($this->jam_mulai === null || $this->jam_mulai === '') {
            return null;
        }

        return substr($this->jam_mulai, 0, 5);
    }

    public function jamSelesaiShort(): ?string
    {
        if ($this->jam_selesai === null || $this->jam_selesai === '') {
            return null;
        }

        return substr($this->jam_selesai, 0, 5);
    }
}
