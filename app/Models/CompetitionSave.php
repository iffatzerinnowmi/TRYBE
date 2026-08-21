<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionSave extends Model
{
    protected $fillable = ['competition_id', 'user_id', 'looking_for_team'];

    protected $casts = ['looking_for_team' => 'boolean'];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
