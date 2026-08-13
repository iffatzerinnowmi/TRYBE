<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyMatchCriteria extends Model
{
    /** Laravel would pluralise this to study_match_criterias. */
    protected $table = 'study_match_criteria';

    protected $fillable = [
        'study_id', 'age_min', 'age_max', 'location',
        'credential_min', 'required_skills', 'availability_days',
    ];

    protected $casts = [
        'required_skills'   => 'array',
        'age_min'           => 'integer',
        'age_max'           => 'integer',
        'availability_days' => 'integer',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }
}
