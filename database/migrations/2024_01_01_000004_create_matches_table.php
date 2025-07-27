<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_team_id')->constrained('teams');
            $table->foreignId('away_team_id')->constrained('teams');
            $table->string('external_id')->unique();
            $table->datetime('match_date');
            $table->integer('home_goals')->nullable();
            $table->integer('away_goals')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, live, finished, postponed
            $table->string('league')->nullable();
            $table->string('season')->nullable();
            $table->integer('round')->nullable();
            $table->json('odds')->nullable(); // Store betting odds as JSON
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};