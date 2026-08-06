<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StudyReview extends Model {
    protected $fillable = ['study_id','researcher_id','participant_id','stars','comment'];
    public function study(): BelongsTo { return $this->belongsTo(Study::class); }
    public function researcher(): BelongsTo { return $this->belongsTo(User::class, 'researcher_id'); }
    public function participant(): BelongsTo { return $this->belongsTo(User::class, 'participant_id'); }
}
