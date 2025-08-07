<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class DailyPickAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'allocation_date',
        'picks_allocated',
        'picks_used',
        'used_matches',
    ];

    protected $casts = [
        'allocation_date' => 'date',
        'used_matches' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRemainingPicksAttribute(): int
    {
        return max(0, $this->picks_allocated - $this->picks_used);
    }

    public function getUsagePercentageAttribute(): float
    {
        return $this->picks_allocated > 0 
            ? round(($this->picks_used / $this->picks_allocated) * 100, 1) 
            : 0;
    }

    public function hasUsedMatch(int $matchId): bool
    {
        return in_array($matchId, $this->used_matches ?? []);
    }

    public function useMatch(int $matchId): bool
    {
        if ($this->getRemainingPicksAttribute() <= 0) {
            return false;
        }

        if ($this->hasUsedMatch($matchId)) {
            return false; // Already used this match
        }

        $usedMatches = $this->used_matches ?? [];
        $usedMatches[] = $matchId;

        $this->update([
            'picks_used' => $this->picks_used + 1,
            'used_matches' => $usedMatches,
        ]);

        return true;
    }

    public static function getOrCreateForUser(User $user, Carbon $date = null): self
    {
        $date = $date ?? now();
        
        return self::firstOrCreate([
            'user_id' => $user->id,
            'allocation_date' => $date->toDateString(),
        ], [
            'picks_allocated' => $user->getDailyPicksLimit(),
            'picks_used' => 0,
            'used_matches' => [],
        ]);
    }

    public static function getTodayAllocation(User $user): self
    {
        return self::getOrCreateForUser($user, now());
    }

    public function scopeForDate($query, Carbon $date)
    {
        return $query->where('allocation_date', $date->toDateString());
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}