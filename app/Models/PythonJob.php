<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PythonJob extends Model
{
    protected $table = 'python_jobs';

    protected $fillable = [
        'status',
        'result',
        'user_id',
        'error',
    ];

    protected $casts = [
        'result' => 'array', // otomatis json → array
    ];
}