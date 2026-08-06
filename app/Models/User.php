<?php
namespace App\Models;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable {
    use Notifiable;

    protected $fillable = [
        'name','email','phone','password','role','location','verification_status','avatar_path',
        'organization_name','organization_type','registration_documents_path',
    ];
    protected $hidden = ['password','remember_token'];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'verification_status' => VerificationStatus::class,
    ];

    public function participantProfile(): HasOne { return $this->hasOne(ParticipantProfile::class); }
    public function researcherProfile(): HasOne { return $this->hasOne(ResearcherProfile::class); }
    public function notificationPreference(): HasOne { return $this->hasOne(NotificationPreference::class); }

    public function studies(): HasMany { return $this->hasMany(Study::class, 'researcher_id'); }
    public function participations(): HasMany { return $this->hasMany(StudyParticipation::class, 'participant_id'); }
    public function endorsementsReceived(): HasMany { return $this->hasMany(Endorsement::class, 'participant_id'); }
    public function endorsementsGiven(): HasMany { return $this->hasMany(Endorsement::class, 'researcher_id'); }
    public function following(): HasMany { return $this->hasMany(Follow::class, 'follower_id'); }
    public function followers(): HasMany { return $this->hasMany(Follow::class, 'researcher_id'); }

    public function initials(): string {
        $p = preg_split('/\s+/', trim(preg_replace('/^dr\.?\s*/i','',$this->name ?? '')));
        $p = array_filter($p);
        if (!$p) return 'TR';
        $p = array_values($p);
        return strtoupper(substr($p[0],0,1) . (isset($p[1]) ? substr($p[1],0,1) : ''));
    }
}
