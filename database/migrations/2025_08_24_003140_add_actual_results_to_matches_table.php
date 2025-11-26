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
        Schema::table('matches', function (Blueprint $table) {
            $table->integer('actual_home_goals')->nullable()->after('away_goals');
            $table->integer('actual_away_goals')->nullable()->after('actual_home_goals');
            $table->integer('actual_first_half_home_goals')->nullable()->after('actual_away_goals');
            $table->integer('actual_first_half_away_goals')->nullable()->after('actual_first_half_home_goals');
            $table->enum('match_status', ['scheduled', 'live', 'finished', 'postponed', 'cancelled'])->default('scheduled')->after('actual_first_half_away_goals');
            $table->timestamp('result_updated_at')->nullable()->after('match_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn([
                'actual_home_goals',
                'actual_away_goals', 
                'actual_first_half_home_goals',
                'actual_first_half_away_goals',
                'match_status',
                'result_updated_at'
            ]);
        });
    }
};
