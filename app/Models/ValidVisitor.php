<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidVisitor extends Model
{
    protected $fillable = [
        'ip',
        'country',
        'user_agent',
        'key_user',
        'session_id',
    ];
}
