<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ResearcherProfile extends Model {
    protected $fillable = [
        'user_id','title','institution','department','bio','linkedin','institutional_email','research_areas',
        'avg_rating','ratings_count','rating_distribution','followers_count',
        'payout_method','payout_details',
    ];
    protected $casts = [
        'rating_distribution' => 'array',
        'payout_method' => \App\Enums\PayoutMethod::class,
        'payout_details' => 'array',
    ];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}