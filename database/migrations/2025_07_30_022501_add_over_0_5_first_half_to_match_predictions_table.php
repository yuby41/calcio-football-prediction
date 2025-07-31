<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('match_predictions', function (Blueprint $table) {
            $table->decimal('over_0_5_first_half_probability', 5, 4)->nullable()->after('under_2_5_probability');
            $table->decimal('home_goals_first_half_prediction', 4, 2)->nullable()->after('over_0_5_first_half_probability');
            $table->decimal('away_goals_first_half_prediction', 4, 2)->nullable()->after('home_goals_first_half_prediction');
            $table->boolean('over_0_5_first_half_correct')->nullable()->after('over_under_correct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_predictions', function (Blueprint $table) {
            $table->dropColumn([
                'over_0_5_first_half_probability',
                'home_goals_first_half_prediction', 
                'away_goals_first_half_prediction',
                'over_0_5_first_half_correct'
            ]);
        });
    }
};