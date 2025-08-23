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
            // Change from decimal(5,4) to decimal(8,6) to allow more precision
            // This changes from 0.5500 max precision to 0.543281 precision
            $table->decimal('both_teams_score_probability', 8, 6)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_predictions', function (Blueprint $table) {
            // Revert back to original precision
            $table->decimal('both_teams_score_probability', 5, 4)->nullable()->change();
        });
    }
};
