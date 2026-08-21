<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    protected $fillable = [
        'posted_by', 'name', 'organizer', 'description', 'external_url',
        'deadline', 'team_min', 'team_max', 'prize', 'location',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function saves(): HasMany
    {
        return $this->hasMany(CompetitionSave::class);
    }

    /**
     * Still worth showing.
     *
     * A competition with no deadline is open indefinitely — we cannot know it
     * has closed, and hiding it because a field is blank would silently drop
     * listings. A dated one drops off the board the day after its deadline.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('deadline')
              ->orWhereDate('deadline', '>=', now()->toDateString());
        });
    }

    /** Null when there is no deadline; negative never happens on the board. */
    public function daysLeft(): ?int
    {
        if (! $this->deadline) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->deadline, false);
    }

    /** "3 – 5" · "Up to 4" · "Solo" · null when unspecified. */
    public function teamSizeLabel(): ?string
    {
        $min = $this->team_min;
        $max = $this->team_max;

        return match (true) {
            $min && $max && $min === $max => $min === 1 ? 'Solo' : $min . ' people',
            $min && $max                  => $min . ' – ' . $max . ' people',
            (bool) $max                   => 'Up to ' . $max . ' people',
            (bool) $min                   => $min . '+ people',
            default                       => null,
        };
    }

    /**
     * The bare domain, shown beside the link.
     *
     * People should be able to see where a button will take them before they
     * press it. This is the cheapest honest substitute for link moderation.
     */
    public function destinationHost(): ?string
    {
        $host = parse_url($this->external_url, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', $host) : null;
    }
}
