<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediction_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('prediction_type'); // 'match_outcome', 'both_teams_score', 'over_under_2_5'
            $table->integer('total_predictions')->default(0);
            $table->integer('correct_predictions')->default(0);
            $table->decimal('accuracy_percentage', 5, 2)->default(0);
            $table->json('monthly_stats')->nullable(); // Stats per month
            $table->json('league_stats')->nullable(); // Stats per league
            $table->date('last_updated');
            $table->timestamps();
            
            $table->unique('prediction_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_statistics');
    }
};