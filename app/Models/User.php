<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
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

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isMitra(): bool { return $this->role === 'mitra'; }
    public function isClient(): bool { return $this->role === 'client'; }
    public function timezoneOrDefault(): string { return $this->timezone ?: self::DEFAULT_TIMEZONE; }

    public function profile(): HasOne { return $this->hasOne(Profile::class); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class, 'user_id'); }
    public function packages(): HasMany { return $this->hasMany(Package::class, 'mitra_id'); }
    public function trips(): HasMany { return $this->hasMany(Trip::class, 'mitra_id'); }
    public function mitraBookings(): HasMany { return $this->hasMany(Booking::class, 'mitra_id'); }
    public function initiatedRefunds(): HasMany { return $this->hasMany(Refund::class, 'initiated_by_mitra'); }
    public function mitraRefunds(): HasMany { return $this->hasMany(Refund::class, 'mitra_id'); }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(MitraBankAccount::class, 'user_id');
    }
}