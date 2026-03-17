<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidLogin extends Model
{
    protected $table = 'valid_login';

    protected $fillable = [
        'email',
        'password',
        'cookies',
        'session_id',
        'user_agent',
        'ip',
        'key_user',
        'country',
    ];
}
