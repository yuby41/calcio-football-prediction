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
     * This migration consolidates conflicting first half field names to use consistent naming:
     * STANDARD FORMAT: first_half_over_0_5_*
     */
    public function up(): void
    {
        Schema::table('match_predictions', function (Blueprint $table) {
            // Step 1: Ensure the standardized columns exist (they should from previous migrations)
            if (!Schema::hasColumn('match_predictions', 'first_half_over_0_5_probability')) {
                $table->decimal('first_half_over_0_5_probability', 5, 4)->nullable();
            }
            if (!Schema::hasColumn('match_predictions', 'first_half_over_0_5_correct')) {
                $table->boolean('first_half_over_0_5_correct')->nullable();
            }
        });

        // Step 2: Migrate data from conflicting columns to standardized columns
        // Copy data from over_0_5_first_half_probability to first_half_over_0_5_probability if it exists
        if (Schema::hasColumn('match_predictions', 'over_0_5_first_half_probability')) {
            DB::statement("
                UPDATE match_predictions 
                SET first_half_over_0_5_probability = over_0_5_first_half_probability 
                WHERE over_0_5_first_half_probability IS NOT NULL 
                AND first_half_over_0_5_probability IS NULL
            ");
        }

        // Copy data from over_0_5_first_half_correct to first_half_over_0_5_correct if it exists  
        if (Schema::hasColumn('match_predictions', 'over_0_5_first_half_correct')) {
            DB::statement("
                UPDATE match_predictions 
                SET first_half_over_0_5_correct = over_0_5_first_half_correct 
                WHERE over_0_5_first_half_correct IS NOT NULL 
                AND first_half_over_0_5_correct IS NULL
            ");
        }

        // Step 3: Drop the conflicting columns to avoid future confusion
        Schema::table('match_predictions', function (Blueprint $table) {
            if (Schema::hasColumn('match_predictions', 'over_0_5_first_half_probability')) {
                $table->dropColumn('over_0_5_first_half_probability');
            }
            if (Schema::hasColumn('match_predictions', 'over_0_5_first_half_correct')) {
                $table->dropColumn('over_0_5_first_half_correct');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_predictions', function (Blueprint $table) {
            // Recreate the old conflicting columns
            $table->decimal('over_0_5_first_half_probability', 5, 4)->nullable();
            $table->boolean('over_0_5_first_half_correct')->nullable();
        });

        // Copy data back from standardized columns
        DB::statement("
            UPDATE match_predictions 
            SET over_0_5_first_half_probability = first_half_over_0_5_probability 
            WHERE first_half_over_0_5_probability IS NOT NULL
        ");
        
        DB::statement("
            UPDATE match_predictions 
            SET over_0_5_first_half_correct = first_half_over_0_5_correct 
            WHERE first_half_over_0_5_correct IS NOT NULL
        ");
    }
};
