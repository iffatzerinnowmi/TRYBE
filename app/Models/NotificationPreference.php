<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class NotificationPreference extends Model {
    protected $fillable = ['user_id','notify_studies','notify_verify','notify_endorse','notify_streak','notify_credential','notify_unlock'];
    protected $casts = [
        'notify_studies'=>'boolean','notify_verify'=>'boolean','notify_endorse'=>'boolean',
        'notify_streak'=>'boolean','notify_credential'=>'boolean','notify_unlock'=>'boolean',
    ];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
