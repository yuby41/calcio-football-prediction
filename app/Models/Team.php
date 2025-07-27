<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'logo',
        'external_id',
        'country',
        'league',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function homeMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class, 'away_team_id');
    }

    public function matches(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->homeMatches->merge($this->awayMatches);
    }

    public function statistics(): HasMany
    {
        return $this->hasMany(TeamStatistic::class);
    }

    public function currentSeasonStats(string $season = null): ?TeamStatistic
    {
        $season = $season ?? date('Y');
        return $this->statistics()->where('season', $season)->first();
    }
}