<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuración del budget
        Schema::create('budget_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ej: "Mansaniello Conservador"
            $table->enum('strategy', ['mansaniello', 'fibonacci', 'martingale', 'fixed', 'percentage']);
            $table->decimal('initial_budget', 10, 2);
            $table->decimal('current_budget', 10, 2);
            $table->decimal('target_profit', 10, 2)->nullable();
            $table->decimal('max_bet_percentage', 5, 2)->default(5.00); // % del budget
            $table->decimal('min_confidence', 5, 2)->default(60.00); // Confianza mínima para apostar
            $table->json('strategy_parameters')->nullable(); // Parámetros específicos
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Registro de apuestas
        Schema::create('bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_configuration_id')->constrained()->onDelete('cascade');
            $table->foreignId('match_id')->references('id')->on('matches')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->decimal('odds', 8, 3);
            $table->string('bet_type'); // 'home_win', 'away_win', 'draw', 'over_2_5', 'both_teams_score'
            $table->decimal('potential_profit', 10, 2);
            $table->enum('status', ['pending', 'won', 'lost', 'cancelled'])->default('pending');
            $table->decimal('actual_profit', 10, 2)->nullable();
            $table->decimal('confidence', 5, 2); // Confianza de la predicción
            $table->decimal('budget_before', 10, 2);
            $table->decimal('budget_after', 10, 2)->nullable();
            $table->integer('sequence_step')->nullable(); // Para estrategias secuenciales
            $table->text('notes')->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // Historial de budget
        Schema::create('budget_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_configuration_id')->constrained()->onDelete('cascade');
            $table->foreignId('bet_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->enum('type', ['bet_placed', 'bet_won', 'bet_lost', 'deposit', 'withdrawal', 'reset']);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Estrategias predefinidas
        Schema::create('betting_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description');
            $table->json('default_parameters');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_history');
        Schema::dropIfExists('bets');
        Schema::dropIfExists('budget_configurations');
        Schema::dropIfExists('betting_strategies');
    }
};