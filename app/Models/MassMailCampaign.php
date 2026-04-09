<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MassMailCampaign extends Model
{
    protected $fillable = [
        'user_id',
        'token_id',
        'name',
        'subject',
        'body',
        'body_mode',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'started_at',
        'finished_at',
        'error_message',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(MassMailRecipient::class, 'campaign_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MassMailAttachment::class, 'campaign_id');
    }
}