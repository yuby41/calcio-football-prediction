#!/bin/bash

# Script de inicialización automática del entorno ML
# Se ejecuta al arrancar el sistema para verificar el entorno ML

echo "🚀 Inicializando entorno ML de Calcio..."

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$PROJECT_DIR"

# Verificar si ya existe el entorno y está funcionando
if [ -d "ml_env" ] && [ -f "ml_env/bin/activate" ]; then
    # Verificación rápida de dependencias
    source ml_env/bin/activate
    if python -c "import pandas, numpy, sklearn, xgboost, lightgbm; print('OK')" 2>/dev/null | grep -q "OK"; then
        echo "✅ Entorno ML ya configurado y funcional"
        exit 0
    else
        echo "⚠️  Entorno ML existe pero dependencias faltantes"
    fi
fi

# Configurar entorno automáticamente
echo "🔧 Configurando entorno ML automáticamente..."

# Ejecutar script de configuración
if [ -f "./setup_ml_env.sh" ]; then
    bash ./setup_ml_env.sh
    if [ $? -eq 0 ]; then
        echo "✅ Entorno ML configurado exitosamente"
    else
        echo "❌ Error configurando entorno ML"
        exit 1
    fi
else
    echo "❌ Script de configuración no encontrado"
    exit 1
fi

# Verificar con Laravel
if command -v php >/dev/null 2>&1; then
    php artisan ml:verify-env --fix
else
    echo "⚠️  PHP no disponible para verificación Laravel"
fi

echo "🎉 Inicialización del entorno ML completada"