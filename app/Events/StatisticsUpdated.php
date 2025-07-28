<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StatisticsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $statistics;

    /**
     * Create a new event instance.
     */
    public function __construct(array $statistics)
    {
        $this->statistics = $statistics;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('statistics'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'statistics.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'accuracy' => $this->statistics['accuracy'] ?? 0,
            'total_predictions' => $this->statistics['total_predictions'] ?? 0,
            'correct_predictions' => $this->statistics['correct_predictions'] ?? 0,
            'timestamp' => now()->toISOString(),
        ];
    }
}