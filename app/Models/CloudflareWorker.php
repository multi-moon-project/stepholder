<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CloudflareWorker extends Model
{
    protected $fillable = [
        'user_id',
        'worker_name',
        'script_name',
        'worker_url',
        'script_content',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cloudflareAccount()
    {
        return $this->belongsTo(CloudflareAccount::class);
    }
}