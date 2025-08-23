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
        // Add indexes to matches table for frequently queried columns
        Schema::table('matches', function (Blueprint $table) {
            // Check if indexes exist before creating them
            if (!$this->hasIndex('matches', 'idx_matches_status_date_new')) {
                $table->index(['status', 'match_date'], 'idx_matches_status_date_new');
            }
            if (!$this->hasIndex('matches', 'idx_matches_status_home')) {
                $table->index(['status', 'home_team_id'], 'idx_matches_status_home');
            }
            if (!$this->hasIndex('matches', 'idx_matches_status_away')) {
                $table->index(['status', 'away_team_id'], 'idx_matches_status_away');
            }
            if (!$this->hasIndex('matches', 'idx_matches_league_status')) {
                $table->index(['league', 'status'], 'idx_matches_league_status');
            }
        });

        // Add indexes to match_predictions table
        Schema::table('match_predictions', function (Blueprint $table) {
            if (!$this->hasIndex('match_predictions', 'idx_predictions_predicted_at')) {
                $table->index(['predicted_at'], 'idx_predictions_predicted_at');
            }
            if (!$this->hasIndex('match_predictions', 'idx_predictions_model_date')) {
                $table->index(['model_version', 'predicted_at'], 'idx_predictions_model_date');
            }
        });

        // Add indexes to teams table
        Schema::table('teams', function (Blueprint $table) {
            if (!$this->hasIndex('teams', 'idx_teams_active_country')) {
                $table->index(['is_active', 'country'], 'idx_teams_active_country');
            }
            if (!$this->hasIndex('teams', 'idx_teams_name')) {
                $table->index(['name'], 'idx_teams_name');
            }
        });

        // Add indexes to team_statistics table
        Schema::table('team_statistics', function (Blueprint $table) {
            if (!$this->hasIndex('team_statistics', 'idx_team_stats_matches')) {
                $table->index(['matches_played'], 'idx_team_stats_matches');
            }
        });
    }

    /**
     * Helper function to check if an index exists
     */
    private function hasIndex($table, $index)
    {
        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $doctrineTable = $sm->listTableDetails($table);
        return $doctrineTable->hasIndex($index);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('idx_matches_status_date');
            $table->dropIndex('idx_matches_status_home');
            $table->dropIndex('idx_matches_status_away');
            $table->dropIndex('idx_matches_league_status');
            $table->dropIndex('idx_matches_date_status');
        });

        Schema::table('match_predictions', function (Blueprint $table) {
            $table->dropIndex('idx_predictions_predicted_at');
            $table->dropIndex('idx_predictions_model_date');
            $table->dropIndex('idx_predictions_match_date');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex('idx_teams_active_country');
            $table->dropIndex('idx_teams_name');
        });

        Schema::table('team_statistics', function (Blueprint $table) {
            $table->dropIndex('idx_team_stats_season_team');
            $table->dropIndex('idx_team_stats_matches');
        });
    }
};
