<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyIncentiveDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'study_id',
        'document_type',
        'storage_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_at',
        'verified_at',
        'verification_note',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }
}
