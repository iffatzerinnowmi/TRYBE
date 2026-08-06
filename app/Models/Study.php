<?php
namespace App\Models;
use App\Enums\IncentiveType;
use App\Enums\StudyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Study extends Model {
    protected $fillable = [
        'researcher_id','title','description','category','eligibility_criteria','method','duration_minutes',
        'incentive_type','compensation_amount','slots','deadline','status','participants_count',
        'irb_document_path','irb_flagged','irb_board','irb_ref','irb_valid_until',
    ];
    protected $casts = [
        'deadline' => 'date',
        'irb_flagged' => 'boolean',
        'incentive_type' => IncentiveType::class,
        'status' => StudyStatus::class,
        'compensation_amount' => 'decimal:2',
    ];
    public function researcher(): BelongsTo { return $this->belongsTo(User::class, 'researcher_id'); }
    public function participations(): HasMany { return $this->hasMany(StudyParticipation::class); }

    /** Whether the given user posted this listing. */
    public function isOwnedBy(?User $user): bool
    {
        return $user && $this->researcher_id === $user->id;
    }

    /** How many of the study's slots are still open, floored at 0. */
    public function spotsRemaining(): int
    {
        $taken = $this->participations_count ?? $this->participations()->count();

        return max(0, $this->slots - $taken);
    }
}
