<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
      protected $fillable = [
        'user_id',
        'provider',
        'email',
        'tenant_id'
    ];

    public function user()
{
    return $this->belongsTo(User::class);
}

public function tokens()
{
    return $this->hasMany(Token::class);
}
}
