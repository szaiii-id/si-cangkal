<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $google_id
 * @property string $role
 * @property string|null $timezone
 * @property bool $must_change_password
 * @property string|null $fcm_token
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Profile|null $profile
 * @property-read Collection<int, Booking> $bookings
 * @property-read Collection<int, Package> $packages
 * @property-read Collection<int, Trip> $trips
 * @property-read Collection<int, Booking> $mitraBookings
 * @property-read Collection<int, Refund> $initiatedRefunds
 * @property-read Collection<int, Refund> $mitraRefunds
 * @property-read Collection<int, MitraBankAccount> $bankAccounts
 *
 * @use HasFactory<UserFactory>
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    public const DEFAULT_TIMEZONE = 'Asia/Makassar';

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'google_id',
        'role',
        'timezone',
        'must_change_password',
        'fcm_token',
    ];

    protected $hidden = ['password', 'remember_token', 'google_id', 'fcm_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Kirim notifikasi verifikasi email memakai template kustom SI-CANGKAL.
     *
     * Override method dari trait `Illuminate\Auth\MustVerifyEmail` agar
     * tidak memakai notifikasi bawaan Laravel (copy Inggris & tanpa
     * branding aplikasi).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isMitra(): bool
    {
        return $this->role === 'mitra';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function timezoneOrDefault(): string
    {
        return $this->timezone ?: self::DEFAULT_TIMEZONE;
    }

    /**
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'user_id');
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'mitra_id');
    }

    /**
     * @return HasMany<Trip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'mitra_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function mitraBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'mitra_id');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function initiatedRefunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'initiated_by_mitra');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function mitraRefunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'mitra_id');
    }

    /**
     * @return HasMany<MitraBankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(MitraBankAccount::class, 'user_id');
    }
}
