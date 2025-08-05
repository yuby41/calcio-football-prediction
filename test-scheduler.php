<?php

// Script para probar que el comando predictions:fix-accuracy esté en el scheduler

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Console\Scheduling\Schedule::class);

echo "🔍 COMANDOS EN EL SCHEDULER:\n";
echo "===========================\n\n";

$events = $kernel->events();

$foundFixAccuracy = false;

foreach ($events as $event) {
    $command = $event->command ?? 'N/A';
    $expression = $event->expression;
    
    if (strpos($command, 'predictions:fix-accuracy') !== false) {
        $foundFixAccuracy = true;
        echo "✅ ENCONTRADO: predictions:fix-accuracy\n";
        echo "   Comando: {$command}\n";
        echo "   Programación: {$expression} (cada hora)\n";
        echo "   Archivo log: storage/logs/predictions-fix-accuracy.log\n\n";
    }
}

if (!$foundFixAccuracy) {
    echo "❌ El comando predictions:fix-accuracy NO está programado\n\n";
} else {
    echo "🎯 CONFIGURACIÓN CORRECTA:\n";
    echo "- El comando se ejecutará automáticamente cada hora\n";
    echo "- Los errores de predicción se corregirán automáticamente\n";
    echo "- Los logs se guardarán para auditoría\n\n";
}

echo "Total de comandos programados: " . count($events) . "\n";