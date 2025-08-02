<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds comprehensive database indexes to improve query performance
     * based on frequently used WHERE clauses, ORDER BY, and JOIN patterns.
     */
    public function up(): void
    {
        // ========================================
        // MATCHES TABLE INDEXES (High Priority)
        // ========================================
        
        Schema::table('matches', function (Blueprint $table) {
            // Skip matches table - indexes already created in previous run
            // All required indexes are already present based on SHOW INDEX output
        });
        
        // ========================================
        // MATCH_PREDICTIONS TABLE INDEXES
        // ========================================
        
        Schema::table('match_predictions', function (Blueprint $table) {
            // Foreign key and most common lookup
            if (!$this->indexExists('match_predictions', 'idx_predictions_match_id')) {
                $table->index('match_id', 'idx_predictions_match_id');
            }
            
            // Accuracy analysis queries
            $table->index('is_correct', 'idx_predictions_is_correct');
            $table->index('confidence_score', 'idx_predictions_confidence');
            
            // Date-based queries
            $table->index('predicted_at', 'idx_predictions_predicted_at');
            
            // Composite for accuracy calculations with date
            $table->index(['is_correct', 'predicted_at'], 'idx_predictions_correct_date');
        });
        
        // ========================================
        // BETS TABLE INDEXES (Medium Priority)
        // ========================================
        
        Schema::table('bets', function (Blueprint $table) {
            // Most frequent lookup patterns
            if (!$this->indexExists('bets', 'idx_bets_budget_config_id')) {
                $table->index('budget_configuration_id', 'idx_bets_budget_config_id');
            }
            if (!$this->indexExists('bets', 'idx_bets_match_id')) {
                $table->index('match_id', 'idx_bets_match_id');
            }
            
            $table->index('status', 'idx_bets_status');
            
            // Composite indexes for common query combinations
            $table->index(['budget_configuration_id', 'status'], 'idx_bets_budget_status');
            $table->index(['status', 'placed_at'], 'idx_bets_status_placed_at');
            
            // Bet type analysis
            $table->index(['bet_type', 'status'], 'idx_bets_bet_type_status');
            
            // Date range queries
            $table->index('created_at', 'idx_bets_created_at');
            $table->index('placed_at', 'idx_bets_placed_at');
            $table->index('resolved_at', 'idx_bets_resolved_at');
        });
        
        // ========================================
        // TEAMS TABLE INDEXES
        // ========================================
        
        Schema::table('teams', function (Blueprint $table) {
            // API sync and lookups
            $table->index('external_id', 'idx_teams_external_id');
            $table->index('league', 'idx_teams_league');
        });
        
        // ========================================
        // TEAM_STATISTICS TABLE INDEXES
        // ========================================
        
        Schema::table('team_statistics', function (Blueprint $table) {
            // Most common lookup patterns (if not already indexed by unique constraint)
            if (!$this->indexExists('team_statistics', 'idx_team_stats_team_id')) {
                $table->index('team_id', 'idx_team_stats_team_id');
            }
            $table->index('season', 'idx_team_stats_season');
            // Note: team_statistics table doesn't have 'league' column
        });
        
        // ========================================
        // ADDITIONAL PERFORMANCE INDEXES
        // ========================================
        
        // Add budget_history indexes if table exists
        if (Schema::hasTable('budget_history')) {
            Schema::table('budget_history', function (Blueprint $table) {
                $table->index('budget_configuration_id', 'idx_budget_history_config_id');
                $table->index('created_at', 'idx_budget_history_created_at');
                $table->index(['budget_configuration_id', 'created_at'], 'idx_budget_history_config_date');
            });
        }
        
        // Add prediction_statistics indexes if table exists
        if (Schema::hasTable('prediction_statistics')) {
            Schema::table('prediction_statistics', function (Blueprint $table) {
                $table->index('prediction_type', 'idx_pred_stats_type');
                $table->index('period', 'idx_pred_stats_period');
                $table->index(['prediction_type', 'period'], 'idx_pred_stats_type_period');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes in reverse order
        
        // prediction_statistics indexes
        if (Schema::hasTable('prediction_statistics')) {
            Schema::table('prediction_statistics', function (Blueprint $table) {
                $table->dropIndex('idx_pred_stats_type_period');
                $table->dropIndex('idx_pred_stats_period');
                $table->dropIndex('idx_pred_stats_type');
            });
        }
        
        // budget_history indexes
        if (Schema::hasTable('budget_history')) {
            Schema::table('budget_history', function (Blueprint $table) {
                $table->dropIndex('idx_budget_history_config_date');
                $table->dropIndex('idx_budget_history_created_at');
                $table->dropIndex('idx_budget_history_config_id');
            });
        }
        
        // team_statistics indexes
        Schema::table('team_statistics', function (Blueprint $table) {
            $table->dropIndex('idx_team_stats_season');
            if ($this->indexExists('team_statistics', 'idx_team_stats_team_id')) {
                $table->dropIndex('idx_team_stats_team_id');
            }
        });
        
        // teams indexes
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex('idx_teams_league');
            $table->dropIndex('idx_teams_external_id');
        });
        
        // bets indexes
        Schema::table('bets', function (Blueprint $table) {
            $table->dropIndex('idx_bets_resolved_at');
            $table->dropIndex('idx_bets_placed_at');
            $table->dropIndex('idx_bets_created_at');
            $table->dropIndex('idx_bets_bet_type_status');
            $table->dropIndex('idx_bets_status_placed_at');
            $table->dropIndex('idx_bets_budget_status');
            $table->dropIndex('idx_bets_status');
            if ($this->indexExists('bets', 'idx_bets_match_id')) {
                $table->dropIndex('idx_bets_match_id');
            }
            if ($this->indexExists('bets', 'idx_bets_budget_config_id')) {
                $table->dropIndex('idx_bets_budget_config_id');
            }
        });
        
        // match_predictions indexes
        Schema::table('match_predictions', function (Blueprint $table) {
            $table->dropIndex('idx_predictions_correct_date');
            $table->dropIndex('idx_predictions_predicted_at');
            $table->dropIndex('idx_predictions_confidence');
            $table->dropIndex('idx_predictions_is_correct');
            if ($this->indexExists('match_predictions', 'idx_predictions_match_id')) {
                $table->dropIndex('idx_predictions_match_id');
            }
        });
        
        // matches indexes
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('idx_matches_away_team_date');
            $table->dropIndex('idx_matches_home_team_date');
            $table->dropIndex('idx_matches_teams_status');
            $table->dropIndex('idx_matches_match_date');
            $table->dropIndex('idx_matches_external_id');
            if ($this->indexExists('matches', 'idx_matches_away_team_id')) {
                $table->dropIndex('idx_matches_away_team_id');
            }
            if ($this->indexExists('matches', 'idx_matches_home_team_id')) {
                $table->dropIndex('idx_matches_home_team_id');
            }
            $table->dropIndex('idx_matches_status_league_date');
            $table->dropIndex('idx_matches_league_status');
            $table->dropIndex('idx_matches_date_status');
            $table->dropIndex('idx_matches_status_date');
        });
    }
    
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return !empty($indexes);
    }
};
