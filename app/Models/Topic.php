<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A research topic. Reference data — seeded once, rarely edited.
 *
 * The belongsToMany relationships are declared HERE rather than on Study and
 * User, so that adding topics to the project required no edit to either of
 * those shared model files.
 */
class Topic extends Model
{
    protected $fillable = ['name', 'slug'];

    public function studies(): BelongsToMany
    {
        return $this->belongsToMany(Study::class, 'study_topic')->withTimestamps();
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'participant_topics')->withTimestamps();
    }
}
