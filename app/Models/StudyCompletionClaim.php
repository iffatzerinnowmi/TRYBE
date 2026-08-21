<?php

namespace App\Models;

use App\Enums\CompletionClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyCompletionClaim extends Model
{
    protected $fillable = [
        'study_id', 'participant_id', 'status', 'form_url',
        'opened_form_at', 'submitted_at', 'reviewed_at', 'reviewed_by', 'note',
    ];

    protected $casts = [
        'status'         => CompletionClaimStatus::class,
        'opened_form_at' => 'datetime',
        'submitted_at'   => 'datetime',
        'reviewed_at'    => 'datetime',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The shape the participant's page and the researcher's list both read. */
    public function toPayload(): array
    {
        return [
            'claim_id'       => $this->id,
            'status'         => $this->status->value,
            'status_label'   => $this->status->label(),
            'note'           => $this->note,
            'form_url'       => $this->form_url,
            'opened_form_at' => $this->opened_form_at?->toIso8601String(),
            'submitted_at'   => $this->submitted_at?->toIso8601String(),
            'submitted_on'   => $this->submitted_at?->format('d M Y'),
            'reviewed_at'    => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
