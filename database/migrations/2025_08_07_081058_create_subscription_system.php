<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Subscription Plans
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->enum('billing_cycle', ['monthly', 'yearly']);
            $table->json('features'); // Store plan features as JSON
            $table->integer('daily_picks_limit');
            $table->json('allowed_leagues')->nullable(); // Specific leagues for each plan
            $table->boolean('has_ai_analysis')->default(false);
            $table->boolean('has_budget_strategies')->default(false);
            $table->integer('budget_strategies_count')->default(0);
            $table->boolean('has_detailed_ai')->default(false);
            $table->boolean('has_priority_support')->default(false);
            $table->boolean('has_early_access')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. User Subscriptions
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained()->onDelete('cascade');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->enum('status', ['active', 'cancelled', 'expired', 'suspended'])->default('active');
            $table->decimal('price_paid', 8, 2);
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
        });

        // 3. Daily Pick Allocations (track daily picks usage)
        Schema::create('daily_pick_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('allocation_date');
            $table->integer('picks_allocated');
            $table->integer('picks_used')->default(0);
            $table->json('used_matches')->nullable(); // Track which matches were used
            $table->timestamps();
            
            $table->unique(['user_id', 'allocation_date']);
        });

        // 4. Add subscription fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_subscription_id')->nullable()->constrained('user_subscriptions')->nullOnDelete();
            $table->enum('subscription_status', ['free', 'active', 'cancelled', 'expired'])->default('free');
            $table->timestamp('last_login_at')->nullable();
            $table->string('timezone')->default('UTC');
            $table->json('preferences')->nullable(); // User preferences
        });

        // 5. Payment History
        Schema::create('payment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained();
            $table->decimal('amount', 8, 2);
            $table->string('currency', 3)->default('EUR');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded']);
            $table->string('payment_method');
            $table->string('transaction_id')->unique();
            $table->json('payment_data')->nullable(); // Additional payment gateway data
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_subscription_id']);
            $table->dropColumn(['current_subscription_id', 'subscription_status', 'last_login_at', 'timezone', 'preferences']);
        });
        
        Schema::dropIfExists('payment_history');
        Schema::dropIfExists('daily_pick_allocations');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};