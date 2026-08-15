<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row = one researcher or organization asking the admin to verify them.
 *
 * Owned by Member 1 (Researcher Verification Badge + Admin Approval panel).
 * The admin reads the pending rows, then sets status to VERIFIED or REJECTED.
 */
class VerificationRequest extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'institutional_email',
        'institutional_affiliation',
        'credential_document_path',
        'organization_name',
        'organization_type',
        'registration_documents_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'role' => UserRole::class,
        'status' => VerificationStatus::class,
        'reviewed_at' => 'datetime',
    ];

    /** The researcher or organization who applied. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The admin who approved or rejected it (null while still pending). */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Only the rows waiting on the admin. Used by the approvals queue. */
    public function scopePending($query)
    {
        return $query->where('status', VerificationStatus::PENDING);
    }
}