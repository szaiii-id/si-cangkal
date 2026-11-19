<?php

namespace App\Models;

use Database\Factories\MitraBankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $tipe
 * @property string $nama_penyedia
 * @property string $nomor_rekening
 * @property string $nama_pemilik
 * @property bool $is_primary
 * @property-read User|null $user
 *
 * @use HasFactory<MitraBankAccountFactory>
 */
class MitraBankAccount extends Model
{
    /** @use HasFactory<MitraBankAccountFactory> */
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
