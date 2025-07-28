<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_configuration_id',
        'bet_id',
        'amount',
        'balance_before',
        'balance_after',
        'type',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function budgetConfiguration(): BelongsTo
    {
        return $this->belongsTo(BudgetConfiguration::class);
    }

    public function bet(): BelongsTo
    {
        return $this->belongsTo(Bet::class);
    }

    public function getTypeDisplayAttribute(): string
    {
        return match($this->type) {
            'bet_placed' => 'Apuesta Realizada',
            'bet_won' => 'Apuesta Ganada',
            'bet_lost' => 'Apuesta Perdida',
            'deposit' => 'Depósito',
            'withdrawal' => 'Retiro',
            'reset' => 'Reset de Budget',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match($this->type) {
            'bet_placed' => 'blue',
            'bet_won' => 'green',
            'bet_lost' => 'red',
            'deposit' => 'green',
            'withdrawal' => 'yellow',
            'reset' => 'purple',
            default => 'gray',
        };
    }
}