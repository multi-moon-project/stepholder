<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceLogin extends Model
{

    protected $fillable = [
        'user_id',
        'device_code',
        'user_code',
        'expires_at',
        'completed',

        // 🔥 tambahan baru
        'status',
        'interval',
        'last_polled_at',
        'next_poll_at',
        'retry_count',
        'last_error'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_polled_at' => 'datetime',
        'next_poll_at' => 'datetime',
        'completed' => 'boolean'
    ];

    public function user()
{
    return $this->belongsTo(User::class);
}
}
