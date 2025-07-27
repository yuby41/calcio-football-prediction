<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches');
            $table->decimal('home_goals_prediction', 3, 2)->nullable();
            $table->decimal('away_goals_prediction', 3, 2)->nullable();
            $table->decimal('home_win_probability', 5, 4)->nullable(); // 0.0000 to 1.0000
            $table->decimal('draw_probability', 5, 4)->nullable();
            $table->decimal('away_win_probability', 5, 4)->nullable();
            $table->string('predicted_outcome')->nullable(); // home_win, draw, away_win
            $table->decimal('both_teams_score_probability', 5, 4)->nullable(); // Both teams to score
            $table->decimal('over_2_5_probability', 5, 4)->nullable(); // Over 2.5 goals
            $table->decimal('under_2_5_probability', 5, 4)->nullable(); // Under 2.5 goals
            $table->decimal('confidence_score', 5, 4)->nullable(); // Model confidence
            $table->string('model_version')->nullable();
            $table->json('features_used')->nullable(); // Features used for prediction
            $table->datetime('predicted_at');
            $table->boolean('is_correct')->nullable(); // Set after match completion
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_predictions');
    }
};