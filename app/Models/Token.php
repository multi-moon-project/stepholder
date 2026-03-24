<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Token extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'email',
        'name',
        'expires_at',
        'status'
    ];

    /**
     * Casts
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    // 🔹 Owner dari token
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 🔹 Relasi ke account (kalau memang 1 token = 1 account)
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    // 🔹 Sub-user yang boleh akses token ini
    public function allowedUsers()
    {
        return $this->belongsToMany(User::class, 'user_token_access')
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    // 🔥 Cek apakah token masih aktif
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // 🔥 Cek apakah token expired
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // 🔥 Cek apakah token valid (gabungan)
    public function isValid(): bool
    {
        return $this->isActive() && !$this->isExpired();
    }

    // 🔥 Cek apakah token milik owner tertentu
    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    // 🔥 Update last used (kalau nanti kamu tambah field)
    public function markAsUsed(): void
    {
        $this->last_used_at = now();
        $this->save();
    }

    public function rules()
{
    return $this->hasMany(\App\Models\MailRule::class);
}
}