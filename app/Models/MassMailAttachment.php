<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MassMailAttachment extends Model
{
    protected $fillable = [
        'campaign_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MassMailCampaign::class, 'campaign_id');
    }
}