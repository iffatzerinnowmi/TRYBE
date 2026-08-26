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
        'total_volunteer_count','volunteer_progress','paid_used', // NEW
    ];
    protected $casts = [
        'last_active_week' => 'date',
        'is_verified_participant' => 'boolean',
        'credential_level' => CredentialLevel::class,
        'paid_studies_unlocked' => 'boolean',
        'paid_studies_unlocked_at' => 'datetime',
        'free_studies_completed','paid_studies_unlocked','paid_studies_unlocked_at',
        'total_volunteer_count' => 'integer', // NEW
        'volunteer_progress' => 'integer',    // NEW
        'paid_used' => 'integer',             // NEW

    ];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
