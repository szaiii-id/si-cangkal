<?php

namespace App\Models;

use Database\Factories\MitraBankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $tipe
 * @property string $nama_penyedia
 * @property string $nomor_rekening
 * @property string $nama_pemilik
 * @property bool $is_primary
 * @property string|null $settlement_bank_account_id
 * @property Carbon|null $settlement_verified_at
 * @property string|null $doku_profile_id
 * @property string|null $doku_account_no
 * @property string|null $doku_split_rule_id
 * @property Carbon|null $doku_registered_at
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
        'settlement_bank_account_id',
        'settlement_verified_at',
        'doku_profile_id',
        'doku_account_no',
        'doku_split_rule_id',
        'doku_registered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'nomor_rekening' => 'encrypted',
            'settlement_verified_at' => 'datetime',
            'doku_registered_at' => 'datetime',
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
