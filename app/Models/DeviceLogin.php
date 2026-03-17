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
        'completed'
    ];

    public function user()
{
    return $this->belongsTo(User::class);
}
}
