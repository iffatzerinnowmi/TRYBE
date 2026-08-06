<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ResearcherProfile extends Model {
    protected $fillable = [
        'user_id','title','institution','department','bio','linkedin','institutional_email','research_areas',
        'avg_rating','ratings_count','rating_distribution','followers_count',
    ];
    protected $casts = ['rating_distribution' => 'array'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
