<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Endorsement extends Model {
    public const TAGS = ['Punctual','Detail-Oriented','Clear Communicator','Reliable','Engaged','Prepared','Great Feedback'];
    protected $fillable = ['participant_id','researcher_id','study_id','tags'];
    protected $casts = ['tags' => 'array'];
    public function participant(): BelongsTo { return $this->belongsTo(User::class, 'participant_id'); }
    public function researcher(): BelongsTo { return $this->belongsTo(User::class, 'researcher_id'); }
}
