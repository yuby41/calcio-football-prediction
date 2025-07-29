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
            $table->decimal('first_half_over_0_5_probability', 5, 4)->nullable()->after('under_2_5_probability');
            $table->decimal('first_half_over_1_5_probability', 5, 4)->nullable()->after('first_half_over_0_5_probability');
            $table->boolean('first_half_over_0_5_correct')->nullable()->after('over_under_correct');
            $table->boolean('first_half_over_1_5_correct')->nullable()->after('first_half_over_0_5_correct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_predictions', function (Blueprint $table) {
            $table->dropColumn([
                'first_half_over_0_5_probability',
                'first_half_over_1_5_probability',
                'first_half_over_0_5_correct',
                'first_half_over_1_5_correct'
            ]);
        });
    }
};