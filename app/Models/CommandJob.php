<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommandJob extends Model
{
 protected $fillable = [
    'user_id',
    'file',
    'output',
    'error',
    'status',
    'user_code',
    'verification_uri',
    'started_at',
    'login_detected_at',
    'timeout_seconds'
];
}