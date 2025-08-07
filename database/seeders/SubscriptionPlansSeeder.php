<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => '1 pick diario aleatorio entre ligas. Perfecto para probar el servicio.',
                'price' => 0.00,
                'billing_cycle' => 'monthly',
                'features' => [
                    '1 pick diario aleatorio',
                    'Ligas variadas',
                    'Sin análisis detallado',
                    'Sin gestión de budget'
                ],
                'daily_picks_limit' => 1,
                'allowed_leagues' => null, // Random from all
                'has_ai_analysis' => false,
                'has_budget_strategies' => false,
                'budget_strategies_count' => 0,
                'has_detailed_ai' => false,
                'has_priority_support' => false,
                'has_early_access' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => '3-4 picks diarios de ligas top y menores. Análisis básico incluido.',
                'price' => 14.99,
                'billing_cycle' => 'monthly',
                'features' => [
                    '3-4 picks diarios',
                    'Ligas top + menores',
                    'Análisis básico IA',
                    'Sin estrategias de budget'
                ],
                'daily_picks_limit' => 4,
                'allowed_leagues' => ['PL', 'PD', 'BL1', 'SA', 'FL1'],
                'has_ai_analysis' => true,
                'has_budget_strategies' => false,
                'budget_strategies_count' => 0,
                'has_detailed_ai' => false,
                'has_priority_support' => false,
                'has_early_access' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => '5-7 picks diarios con análisis IA breve y acceso a 3 estrategias de budget.',
                'price' => 29.99,
                'billing_cycle' => 'monthly',
                'features' => [
                    '5-7 picks diarios',
                    'Todas las ligas principales',
                    'Análisis IA breve',
                    '3 estrategias de budget',
                    'Gestión avanzada'
                ],
                'daily_picks_limit' => 7,
                'allowed_leagues' => ['PL', 'PD', 'BL1', 'SA', 'FL1', 'CL', 'EL'],
                'has_ai_analysis' => true,
                'has_budget_strategies' => true,
                'budget_strategies_count' => 3,
                'has_detailed_ai' => false,
                'has_priority_support' => false,
                'has_early_access' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Elite',
                'slug' => 'elite',
                'description' => 'Todos los picks (~10/día) con IA explicada y 5 estrategias completas.',
                'price' => 49.99,
                'billing_cycle' => 'monthly',
                'features' => [
                    '~10 picks diarios',
                    'Todas las ligas',
                    'IA completamente explicada',
                    '5 estrategias de budget',
                    'Análisis detallado',
                    'Soporte prioritario'
                ],
                'daily_picks_limit' => 10,
                'allowed_leagues' => null, // All leagues
                'has_ai_analysis' => true,
                'has_budget_strategies' => true,
                'budget_strategies_count' => 5,
                'has_detailed_ai' => true,
                'has_priority_support' => true,
                'has_early_access' => false,
                'sort_order' => 4,
            ],
            [
                'name' => 'VIP Anual',
                'slug' => 'vip-annual',
                'description' => 'Plan anual con todo incluido + soporte directo + acceso anticipado (17% ahorro).',
                'price' => 399.00,
                'billing_cycle' => 'yearly',
                'features' => [
                    'Todo lo anterior',
                    'Soporte directo 1-a-1',
                    'Acceso anticipado',
                    'Partidos clave prioritarios',
                    '17% ahorro vs mensual',
                    'Sin compromiso de renovación'
                ],
                'daily_picks_limit' => 12,
                'allowed_leagues' => null, // All leagues
                'has_ai_analysis' => true,
                'has_budget_strategies' => true,
                'budget_strategies_count' => 5,
                'has_detailed_ai' => true,
                'has_priority_support' => true,
                'has_early_access' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}