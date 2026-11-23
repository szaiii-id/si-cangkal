<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Review — ulasan client untuk sebuah booking/paket.
 *
 * @property int $id
 * @property int $booking_id
 * @property int $package_id
 * @property int $user_id
 * @property int $rating
 * @property string $komentar
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read Package $package
 * @property-read User|null $client
 *
 * @use HasFactory<ReviewFactory>
 */
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'booking_id',
        'package_id',
        'user_id',
        'rating',
        'komentar',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Penulis review (client). Memakai `user_id` supaya konsisten
     * dengan Booking::client() dan kolom di tabel.
     *
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Urutan tampil katalog: terbaru lebih dulu.
     * `id` dipakai sebagai tie-breaker saat timestamp identik.
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeTerbaru(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
