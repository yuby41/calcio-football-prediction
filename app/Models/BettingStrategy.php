<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingStrategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'default_parameters',
        'is_active',
    ];

    protected $casts = [
        'default_parameters' => 'array',
        'is_active' => 'boolean',
    ];
}