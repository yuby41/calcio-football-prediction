<?php

namespace App\Events;

use App\Models\FootballMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchScoreUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $match;

    /**
     * Create a new event instance.
     */
    public function __construct(FootballMatch $match)
    {
        $this->match = $match;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('matches'),
            new Channel('match.' . $this->match->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'score.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->match->id,
            'home_team' => $this->match->homeTeam->name,
            'away_team' => $this->match->awayTeam->name,
            'home_goals' => $this->match->home_goals,
            'away_goals' => $this->match->away_goals,
            'status' => $this->match->status,
            'minute' => $this->match->minute,
            'match_date' => $this->match->match_date,
            'prediction' => $this->match->prediction ? [
                'predicted_outcome' => $this->match->prediction->predicted_outcome,
                'confidence' => $this->match->prediction->confidence,
                'is_correct' => $this->match->prediction->is_correct,
            ] : null,
        ];
    }
}