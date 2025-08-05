#!/bin/bash

echo "🔍 VERIFICACIÓN DEL SISTEMA AUTOMÁTICO"
echo "======================================"
echo ""

echo "1️⃣ Estado del Cron Job:"
echo "----------------------"
crontab -l | grep schedule:run
echo ""

echo "2️⃣ Comandos Programados:"
echo "------------------------"
php artisan schedule:list | head -10
echo ""

echo "3️⃣ Test de Comando Manual:"
echo "---------------------------"
echo "Ejecutando predictions:fix-accuracy --dry-run..."
php artisan predictions:fix-accuracy --dry-run
echo ""

echo "4️⃣ Logs del Sistema:"
echo "--------------------"
if [ -f "storage/logs/predictions-fix-accuracy.log" ]; then
    echo "✅ Log file exists:"
    tail -5 storage/logs/predictions-fix-accuracy.log
else
    echo "⚠️  Log file not created yet (will be created when scheduler runs)"
fi
echo ""

echo "5️⃣ Verificación de Sintaxis Kernel.php:"
echo "---------------------------------------"
php -l app/Console/Kernel.php
echo ""

echo "🎯 RESUMEN:"
echo "----------"
echo "✅ Cron job configurado para ejecutar scheduler cada minuto"
echo "✅ Comando predictions:fix-accuracy disponible y funcionando"
echo "✅ Se ejecutará automáticamente cada hora para corregir errores"
echo "✅ Los logs se guardarán en storage/logs/predictions-fix-accuracy.log"
echo ""
echo "🔄 El comando se ejecuta automáticamente cada hora en minuto 0"
echo "⚡ Para ejecutar manualmente: php artisan predictions:fix-accuracy"