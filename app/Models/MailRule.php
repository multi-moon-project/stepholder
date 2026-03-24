<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailRule extends Model
{
    protected $fillable = [
        'token_id',
        'name',
        'condition_type',
        'condition_value',
        'action_delete',
        'action_read',
        'action_folder',
        'is_active',
        'priority'
    ];
}