<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams');
            $table->string('season');
            $table->integer('matches_played')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('draws')->default(0);
            $table->integer('losses')->default(0);
            $table->integer('goals_for')->default(0);
            $table->integer('goals_against')->default(0);
            $table->integer('goals_difference')->default(0);
            $table->integer('points')->default(0);
            $table->decimal('avg_goals_for', 3, 2)->default(0);
            $table->decimal('avg_goals_against', 3, 2)->default(0);
            $table->json('form')->nullable(); // Last 5 matches results
            $table->json('home_stats')->nullable(); // Home performance stats
            $table->json('away_stats')->nullable(); // Away performance stats
            $table->timestamps();
            
            $table->unique(['team_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_statistics');
    }
};