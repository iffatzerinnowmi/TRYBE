<?php

namespace App\Models;

use App\Enums\IncentiveType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Study extends Model
{
    use HasFactory;

    protected $table = 'studies';

    protected $fillable = [
        'title',
        'description',
        'incentive_type',
        'incentive_amount',
        'currency',
        'course_credit_document_path',
        'course_credit_document_name',
        'escrow_locked_at',
        // denormalized escrow fields
        'escrow_provider_reference',
        'escrow_status',
        'escrow_amount',
    ];

    protected $casts = [
        'incentive_type' => IncentiveType::class,
        'incentive_amount' => 'decimal:2',
        'escrow_amount' => 'decimal:2',
        'escrow_locked_at' => 'datetime',
    ];

    public function escrows(): HasMany
    {
        return $this->hasMany(StudyEscrow::class);
    }

    public function incentiveDocuments(): HasMany
    {
        return $this->hasMany(StudyIncentiveDocument::class);
    }

    public function incentiveAudits(): HasMany
    {
        return $this->hasMany(StudyIncentiveAudit::class);
    }
}
