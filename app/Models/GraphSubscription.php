<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraphSubscription extends Model
{
    protected $fillable = [
        'token_id',
        'subscription_id',
        'resource',
        'expires_at',
        'client_state'
    ];

    public function token()
    {
        return $this->belongsTo(Token::class);
    }
}