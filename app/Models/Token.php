<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Token extends Model
{
 use SoftDeletes;
    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        "email",
        "name",
        'expires_at',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
{
    return $this->belongsTo(Account::class);
}

}