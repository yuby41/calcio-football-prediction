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
        Schema::table('team_statistics', function (Blueprint $table) {
            // Increase precision for average goals to handle values up to 99.99
            $table->decimal('avg_goals_for', 5, 2)->default(0.00)->change();
            $table->decimal('avg_goals_against', 5, 2)->default(0.00)->change();
            
            // Add new statistical fields that were missing
            $table->decimal('win_rate', 5, 2)->default(0.00)->after('avg_goals_against');
            $table->decimal('draw_rate', 5, 2)->default(0.00)->after('win_rate');
            $table->decimal('loss_rate', 5, 2)->default(0.00)->after('draw_rate');
            
            // Add home/away detailed statistics
            $table->integer('home_matches')->default(0)->after('loss_rate');
            $table->integer('away_matches')->default(0)->after('home_matches');
            $table->integer('home_wins')->default(0)->after('away_matches');
            $table->integer('away_wins')->default(0)->after('home_wins');
            $table->integer('home_draws')->default(0)->after('away_wins');
            $table->integer('away_draws')->default(0)->after('home_draws');
            $table->integer('home_losses')->default(0)->after('away_draws');
            $table->integer('away_losses')->default(0)->after('home_losses');
            $table->integer('home_goals_for')->default(0)->after('away_losses');
            $table->integer('home_goals_against')->default(0)->after('home_goals_for');
            $table->integer('away_goals_for')->default(0)->after('home_goals_against');
            $table->integer('away_goals_against')->default(0)->after('away_goals_for');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_statistics', function (Blueprint $table) {
            // Revert precision changes
            $table->decimal('avg_goals_for', 3, 2)->default(0.00)->change();
            $table->decimal('avg_goals_against', 3, 2)->default(0.00)->change();
            
            // Drop new fields
            $table->dropColumn([
                'win_rate', 'draw_rate', 'loss_rate',
                'home_matches', 'away_matches',
                'home_wins', 'away_wins',
                'home_draws', 'away_draws', 
                'home_losses', 'away_losses',
                'home_goals_for', 'home_goals_against',
                'away_goals_for', 'away_goals_against'
            ]);
        });
    }
};
