<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model MitraBankAccount — daftar rekening bank/e-wallet milik Mitra.
 *
 * @property int $id
 * @property int $user_id
 * @property string $tipe (bank | ewallet)
 * @property string $nama_penyedia (BCA, Mandiri, DANA, dll)
 * @property string $nomor_rekening
 * @property string $nama_pemilik
 * @property bool $is_primary
 * @property-read User|null $user
 */
class MitraBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tipe',
        'nama_penyedia',
        'nomor_rekening',
        'nama_pemilik',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'nomor_rekening' => 'encrypted',
        ];
    }

    // ==========================================
    // RELASI
    // ==========================================

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}