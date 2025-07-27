<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_predictions', function (Blueprint $table) {
            $table->decimal('both_teams_score_probability', 5, 4)->nullable()->after('away_win_probability');
            $table->decimal('over_2_5_probability', 5, 4)->nullable()->after('both_teams_score_probability');
            $table->decimal('under_2_5_probability', 5, 4)->nullable()->after('over_2_5_probability');
        });
    }

    public function down(): void
    {
        Schema::table('match_predictions', function (Blueprint $table) {
            $table->dropColumn(['both_teams_score_probability', 'over_2_5_probability', 'under_2_5_probability']);
        });
    }
};