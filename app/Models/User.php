<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Token;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'login_key',
        'owner_id', // 🔥 penting untuk sub-user
    ];

    /**
     * Hidden attributes
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    // 🔹 Relasi ke owner (jika dia sub-user)
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // 🔹 Relasi ke sub-users (jika dia owner)
    public function subUsers()
    {
        return $this->hasMany(User::class, 'owner_id');
    }

    // 🔹 Token milik user (owner)
    public function tokens()
    {
        return $this->hasMany(Token::class);
    }

    // 🔹 Token yang bisa diakses sub-user
    public function accessibleTokens()
    {
        return $this->belongsToMany(Token::class, 'user_token_access');
    }

    // 🔹 Settings
    public function settings()
    {
        return $this->hasOne(UserSetting::class);
    }

    // 🔹 Accounts (milik owner)
    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    // 🔹 Device logins
    public function deviceLogins()
    {
        return $this->hasMany(DeviceLogin::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS (IMPORTANT)
    |--------------------------------------------------------------------------
    */

    // 🔥 Ambil owner ID (dipakai untuk semua query resource)
    public function getOwnerId(): int
    {
        return $this->owner_id ?? $this->id;
    }

    // 🔥 Check apakah user adalah sub-user
    public function isSubUser(): bool
    {
        return !is_null($this->owner_id);
    }

    // 🔥 Check apakah user adalah owner
    public function isOwner(): bool
    {
        return is_null($this->owner_id);
    }

    // 🔥 Check apakah sub-user boleh akses token tertentu
    public function canAccessToken(Token $token): bool
    {
        // owner bebas akses semua token miliknya
        if ($this->isOwner()) {
            return $token->user_id === $this->id;
        }

        // sub-user: harus milik owner & di-assign
        if ($token->user_id !== $this->owner_id) {
            return false;
        }

        return $this->accessibleTokens()
            ->where('tokens.id', $token->id)
            ->exists();
    }

    public function cloudflareAccount()
    {
        return $this->hasOne(CloudflareAccount::class);
    }

    public function cloudflareWorkers()
    {
        return $this->hasMany(CloudflareWorker::class);
    }
    public function isSubscriptionExpired(): bool
    {
        return $this->created_at->diffInDays(now()) > 30;
    }
}
