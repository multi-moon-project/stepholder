<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'key_link',
        'telegram_id_1',
        'telegram_id_2',
        'telegram_bot_1',
        'telegram_bot_2',
        'subscription_status',
        'subscription_until',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
