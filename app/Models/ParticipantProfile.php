<?php
namespace App\Models;
use App\Enums\CredentialLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ParticipantProfile extends Model {
    protected $fillable = [
        'user_id','age','gender','occupation','health_background','interests','skills','linkedin','github',
        'rel_attendance','rel_completion','rel_reviews','reliability_score',
        'completed_studies_count','credential_level',
        'current_streak_weeks','longest_streak_weeks','last_active_week',
        'endorsement_count','is_verified_participant',
        'free_studies_completed','paid_studies_unlocked','paid_studies_unlocked_at', 
    ];
    protected $casts = [
        'last_active_week' => 'date',
        'is_verified_participant' => 'boolean',
        'credential_level' => CredentialLevel::class,
        'paid_studies_unlocked' => 'boolean',          // ADD
    'paid_studies_unlocked_at' => 'datetime',      //timestamp when the participant unlocked paid studies   
    ];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
