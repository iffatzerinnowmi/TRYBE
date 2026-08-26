<?php
namespace App\Models;
use App\Enums\PipelineStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
class StudyParticipation extends Model {
    protected $fillable = ['study_id','participant_id','stage','completed_at','attendance_status'];
    protected $casts = ['stage' => PipelineStage::class, 'completed_at' => 'datetime'];
    public function study(): BelongsTo { return $this->belongsTo(Study::class); }
    public function participant(): BelongsTo { return $this->belongsTo(User::class, 'participant_id'); }

    // ---- Verified Payment Escrow (Member 3) ----
    public function payout(): HasOne { return $this->hasOne(\App\Models\PaymentPayout::class); }
}