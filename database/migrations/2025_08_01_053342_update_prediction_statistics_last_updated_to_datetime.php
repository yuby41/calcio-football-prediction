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
        Schema::table('prediction_statistics', function (Blueprint $table) {
            $table->datetime('last_updated')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prediction_statistics', function (Blueprint $table) {
            $table->date('last_updated')->change();
        });
    }
};