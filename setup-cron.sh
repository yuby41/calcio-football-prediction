#!/bin/bash

# Script para configurar el cron job de Laravel Scheduler
# Este script debe ejecutarse una sola vez para configurar el sistema

echo "🔧 Configurando cron job para Laravel Scheduler..."

# Crear el cron job
(crontab -l 2>/dev/null; echo "* * * * * cd /home/yualbe/Homestead/code/Calcio && php artisan schedule:run >> /dev/null 2>&1") | crontab -

echo "✅ Cron job configurado exitosamente!"
echo ""
echo "📋 Verificación de cron jobs activos:"
crontab -l

echo ""
echo "🕐 El comando 'predictions:fix-accuracy' se ejecutará automáticamente cada hora."
echo "📊 Puedes verificar los logs en: storage/logs/predictions-fix-accuracy.log"
echo ""
echo "⚡ Para probar inmediatamente, ejecuta:"
echo "   php artisan predictions:fix-accuracy"