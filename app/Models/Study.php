<?php
namespace App\Models;
use App\Enums\IncentiveType;
use App\Enums\StudyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Study extends Model {
    protected $fillable = [
        'researcher_id','title','description','category','method','duration_minutes',
        'incentive_type','compensation_amount','slots','deadline','status','participants_count',
        'irb_document_path','irb_flagged','irb_board','irb_ref','irb_valid_until',
        'escrow_locked','escrow_locked_at','course_credit_institution','course_credit_document_path',
    ];
    protected $casts = [
        'deadline' => 'date',
        'irb_flagged' => 'boolean',
        'incentive_type' => IncentiveType::class,
        'status' => StudyStatus::class,
        'compensation_amount' => 'decimal:2',
        'escrow_locked' => 'boolean',
        'escrow_locked_at' => 'datetime',
    ];
    public function researcher(): BelongsTo { return $this->belongsTo(User::class, 'researcher_id'); }
    public function participations(): HasMany { return $this->hasMany(StudyParticipation::class); }
    public function slots(): HasMany { return $this->hasMany(StudySlot::class); }
    public function slotBookings(): HasMany { return $this->hasMany(StudySlotBooking::class); }
    public function escrow(): HasOne { return $this->hasOne(PaymentEscrow::class); }
    public function payouts(): HasMany { return $this->hasMany(PaymentPayout::class); }
}