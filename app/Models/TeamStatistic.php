<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'season',
        'matches_played',
        'wins',
        'draws',
        'losses',
        'goals_for',
        'goals_against',
        'goals_difference',
        'points',
        'avg_goals_for',
        'avg_goals_against',
        'form',
        'home_stats',
        'away_stats',
    ];

    protected $casts = [
        'matches_played' => 'integer',
        'wins' => 'integer',
        'draws' => 'integer',
        'losses' => 'integer',
        'goals_for' => 'integer',
        'goals_against' => 'integer',
        'goals_difference' => 'integer',
        'points' => 'integer',
        'avg_goals_for' => 'decimal:2',
        'avg_goals_against' => 'decimal:2',
        'form' => 'array',
        'home_stats' => 'array',
        'away_stats' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getWinPercentageAttribute(): float
    {
        if ($this->matches_played === 0) {
            return 0;
        }

        return round(($this->wins / $this->matches_played) * 100, 2);
    }

    public function getDrawPercentageAttribute(): float
    {
        if ($this->matches_played === 0) {
            return 0;
        }

        return round(($this->draws / $this->matches_played) * 100, 2);
    }

    public function getLossPercentageAttribute(): float
    {
        if ($this->matches_played === 0) {
            return 0;
        }

        return round(($this->losses / $this->matches_played) * 100, 2);
    }

    public function getFormStringAttribute(): string
    {
        if (!$this->form || empty($this->form)) {
            return '';
        }

        return implode('', array_map(function ($result) {
            return strtoupper(substr($result, 0, 1));
        }, $this->form));
    }

    public function updateStats(array $matchData): void
    {
        $this->matches_played++;
        $this->goals_for += $matchData['goals_for'];
        $this->goals_against += $matchData['goals_against'];
        $this->goals_difference = $this->goals_for - $this->goals_against;

        if ($matchData['result'] === 'win') {
            $this->wins++;
            $this->points += 3;
        } elseif ($matchData['result'] === 'draw') {
            $this->draws++;
            $this->points += 1;
        } else {
            $this->losses++;
        }

        $this->avg_goals_for = round($this->goals_for / $this->matches_played, 2);
        $this->avg_goals_against = round($this->goals_against / $this->matches_played, 2);

        // Update form (last 5 matches)
        $form = $this->form ?? [];
        array_unshift($form, $matchData['result']);
        $this->form = array_slice($form, 0, 5);

        $this->save();
    }
}