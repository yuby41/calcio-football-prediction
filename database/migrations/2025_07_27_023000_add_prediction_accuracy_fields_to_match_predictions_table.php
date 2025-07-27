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
            $table->boolean('both_teams_score_correct')->nullable()->after('is_correct');
            $table->boolean('over_under_correct')->nullable()->after('both_teams_score_correct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_predictions', function (Blueprint $table) {
            $table->dropColumn(['both_teams_score_correct', 'over_under_correct']);
        });
    }
};