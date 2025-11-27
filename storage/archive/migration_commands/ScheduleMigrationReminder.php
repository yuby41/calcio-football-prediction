<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class ScheduleMigrationReminder extends Command
{
    protected $signature = 'schedule:migration-reminder';
    protected $description = 'Set up daily migration reminder and automation';

    public function handle(): int
    {
        $this->info('📅 CONFIGURANDO RECORDATORIO DE MIGRACIÓN DIARIA');
        $this->newLine();

        // Create migration plan file
        $this->createMigrationPlan();
        
        // Create automated migration script
        $this->createAutomatedMigrationScript();
        
        // Create scheduler entry instructions
        $this->showSchedulerInstructions();
        
        // Create reminder notes
        $this->createReminderNotes();
        
        return 0;
    }

    private function createMigrationPlan(): void
    {
        $this->info('📋 Creando plan de migración...');
        
        $migrationPlan = [
            'migration_status' => [
                'total_predictions' => 19233,
                'current_real_data' => 193,
                'percentage_complete' => 1.0,
                'remaining_to_migrate' => 19040
            ],
            'daily_plan' => [
                'api_quota_daily' => 7500,
                'conservative_usage' => 2000, // Usar solo ~25% de la cuota diaria
                'matches_per_day' => 1000,
                'estimated_days_to_complete' => ceil(19040 / 1000)
            ],
            'commands_to_run' => [
                'check_quota' => 'php artisan api:check-quota',
                'migrate_daily' => 'php artisan data:migrate-quota --limit=1000',
                'health_check' => 'php artisan app:health-check',
                'maintenance' => 'php artisan app:maintenance --daily'
            ],
            'schedule' => [
                'preferred_time' => '09:00 AM',
                'backup_time' => '02:00 PM',
                'weekly_maintenance' => 'Sunday 08:00 AM'
            ]
        ];

        $planFile = storage_path('app/migration_plan.json');
        file_put_contents($planFile, json_encode($migrationPlan, JSON_PRETTY_PRINT));
        
        $this->line("   ✅ Plan guardado en: {$planFile}");
    }

    private function createAutomatedMigrationScript(): void
    {
        $this->info('🤖 Creando script de migración automática...');
        
        $scriptContent = '#!/bin/bash

# Automated Daily Migration Script for Calcio App
# Run this script daily to continue migrating synthetic data to real data

echo "🚀 INICIANDO MIGRACIÓN DIARIA AUTOMÁTICA - $(date)"
echo "=================================================="

# Navigate to app directory
cd /home/yualbe/Homestead/code/Calcio

# Check current migration status
echo "📊 ESTADO ACTUAL:"
php artisan tinker --execute="echo \'Predicciones con datos reales: \' . App\Models\MatchPrediction::where(\'model_version\', \'like\', \'%real%\')->count(); echo \' de \' . App\Models\MatchPrediction::count() . \' total\'; echo PHP_EOL;"

# Check API quota available
echo ""
echo "🔍 VERIFICANDO CUOTA API:"
php artisan api:check-quota

# Wait for user confirmation (remove --auto for manual confirmation)
echo ""
echo "⚡ INICIANDO MIGRACIÓN..."
php artisan data:migrate-quota --limit=1000

# Post-migration status
echo ""
echo "📈 ESTADO POST-MIGRACIÓN:"
php artisan tinker --execute="echo \'Predicciones reales: \' . App\Models\MatchPrediction::where(\'model_version\', \'like\', \'%real%\')->count(); echo \' (\' . round((App\Models\MatchPrediction::where(\'model_version\', \'like\', \'%real%\')->count() / App\Models\MatchPrediction::count()) * 100, 2) . \'%)\'; echo PHP_EOL;"

# Run daily maintenance
echo ""
echo "🔧 EJECUTANDO MANTENIMIENTO DIARIO:"
php artisan app:maintenance --daily

# Health check
echo ""
echo "🏥 VERIFICACIÓN DE SALUD:"
php artisan app:health-check

echo ""
echo "✅ MIGRACIÓN DIARIA COMPLETADA - $(date)"
echo "=================================================="

# Log completion
echo "$(date): Daily migration completed" >> storage/logs/migration_log.txt
';

        $scriptPath = base_path('daily_migration.sh');
        file_put_contents($scriptPath, $scriptContent);
        chmod($scriptPath, 0755);
        
        $this->line("   ✅ Script creado en: {$scriptPath}");
    }

    private function showSchedulerInstructions(): void
    {
        $this->info('⏰ INSTRUCCIONES PARA AUTOMATIZACIÓN:');
        $this->newLine();
        
        $this->line('📋 OPCIÓN 1: CRONTAB (Recomendado)');
        $this->line('Añadir esta línea a tu crontab (crontab -e):');
        $this->line('0 9 * * * cd /home/yualbe/Homestead/code/Calcio && ./daily_migration.sh >> storage/logs/cron_migration.log 2>&1');
        $this->newLine();
        
        $this->line('📋 OPCIÓN 2: LARAVEL SCHEDULER');
        $this->line('Añadir a app/Console/Kernel.php en el método schedule():');
        $this->line('$schedule->command("data:migrate-quota --limit=1000")->dailyAt("09:00");');
        $this->line('$schedule->command("app:maintenance --daily")->dailyAt("09:30");');
        $this->newLine();
        
        $this->line('📋 OPCIÓN 3: MANUAL DIARIO');
        $this->line('Ejecutar manualmente cada día:');
        $this->line('./daily_migration.sh');
        $this->newLine();
    }

    private function createReminderNotes(): void
    {
        $this->info('📝 Creando notas de recordatorio...');
        
        $reminderContent = "# 🚀 RECORDATORIO DE MIGRACIÓN DIARIA - CALCIO APP

## ✅ ESTADO ACTUAL ($(date +%Y-%m-%d))
- **Progreso:** 193/19233 predicciones migradas (1%)
- **Restantes:** ~19,040 predicciones sintéticas
- **Cuota API:** Plan Pro (7,500 requests/día)

## 📅 PLAN DIARIO
**Hora recomendada:** 9:00 AM
**Comando:** `php artisan data:migrate-quota --limit=1000`
**Duración estimada:** 5-10 minutos
**Requests usadas:** ~2,000 (conservador)

## 🎯 COMANDOS ESENCIALES

### Verificar cuota disponible:
\`\`\`bash
php artisan api:check-quota
\`\`\`

### Migrar 1000 matches:
\`\`\`bash
php artisan data:migrate-quota --limit=1000
\`\`\`

### Script automático completo:
\`\`\`bash
./daily_migration.sh
\`\`\`

### Verificar progreso:
\`\`\`bash
php artisan tinker --execute=\"echo 'Progreso: ' . round((App\Models\MatchPrediction::where('model_version', 'like', '%real%')->count() / App\Models\MatchPrediction::count()) * 100, 2) . '%';\"
\`\`\`

## 📈 META DE MIGRACIÓN
- **Objetivo:** 100% datos reales
- **Tiempo estimado:** 19 días (1000 matches/día)
- **Al 50%:** ~10 días
- **Al 80%:** ~16 días

## ⚠️ RECORDATORIOS
1. **Verificar cuota** antes de migrar
2. **No exceder 3000 matches/día** para conservar cuota
3. **Ejecutar mantenimiento** después de migración
4. **Monitorear salud** de la aplicación

## 🏆 HITOS IMPORTANTES
- [ ] 5% completado (~960 predicciones reales)
- [ ] 10% completado (~1923 predicciones reales)  
- [ ] 25% completado (~4808 predicciones reales)
- [ ] 50% completado (~9617 predicciones reales)
- [ ] 75% completado (~14425 predicciones reales)
- [ ] 100% completado (~19233 predicciones reales) 🎉

## 📞 EN CASO DE PROBLEMAS
1. Verificar logs: `tail -f storage/logs/laravel.log`
2. Health check: `php artisan app:health-check`
3. Reparar vistas: `php artisan fix:real-views-problem`
4. Mantenimiento: `php artisan app:maintenance --emergency`

---
**💡 TIP:** Ejecuta `./daily_migration.sh` cada mañana para automatizar todo el proceso.
";

        $reminderPath = base_path('MIGRATION_REMINDER.md');
        file_put_contents($reminderPath, $reminderContent);
        
        $this->line("   ✅ Recordatorio creado en: {$reminderPath}");
    }
}