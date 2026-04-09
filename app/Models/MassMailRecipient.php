<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MassMailRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'email',
        'status',
        'attempts',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MassMailCampaign::class, 'campaign_id');
    }
}