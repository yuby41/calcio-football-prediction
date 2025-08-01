<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FootballMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'home_team_id',
        'away_team_id',
        'external_id',
        'match_date',
        'home_goals',
        'away_goals',
        'home_goals_first_half',
        'away_goals_first_half',
        'status',
        'league',
        'season',
        'round',
        'odds',
    ];

    protected $casts = [
        'match_date' => 'datetime',
        'odds' => 'array',
        'home_goals' => 'integer',
        'away_goals' => 'integer',
        'round' => 'integer',
    ];

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function prediction(): HasOne
    {
        return $this->hasOne(MatchPrediction::class, 'match_id');
    }

    public function getResultAttribute(): ?string
    {
        if ($this->status !== 'finished' || is_null($this->home_goals) || is_null($this->away_goals)) {
            return null;
        }

        if ($this->home_goals > $this->away_goals) {
            return 'home_win';
        } elseif ($this->home_goals < $this->away_goals) {
            return 'away_win';
        } else {
            return 'draw';
        }
    }

    public function getTotalGoalsAttribute(): ?int
    {
        if (is_null($this->home_goals) || is_null($this->away_goals)) {
            return null;
        }

        return $this->home_goals + $this->away_goals;
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }
}