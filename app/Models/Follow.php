<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Follow extends Model {
    protected $fillable = ['follower_id','researcher_id'];
    public function follower(): BelongsTo { return $this->belongsTo(User::class, 'follower_id'); }
    public function researcher(): BelongsTo { return $this->belongsTo(User::class, 'researcher_id'); }
}
