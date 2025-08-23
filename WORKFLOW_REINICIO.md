# 🚀 Workflow de Reinicio - Calcio App

## Comandos para ejecutar ordenadamente después de días de apagado

### 🔄 **Recuperación de Datos Perdidos (PRIORIDAD ALTA)**

#### **Si la app estuvo apagada 2+ días:**
```bash
# Recuperar partidos de los últimos 7 días
php artisan football:sync-historical --days=7

# Actualizar resultados de partidos finalizados
php artisan football:update-results --from=7days-ago

# Regenerar predicciones para partidos perdidos
php artisan ml:generate-missing-predictions --days=7

# Actualizar estadísticas con datos recuperados
php artisan statistics:recalculate-all
```

---

## Comandos para ejecutar ordenadamente después de reiniciar la aplicación

### 📋 **Orden de Ejecución Obligatorio**

#### 1. **Verificación del Entorno ML**
```bash
# Verificar y reparar automáticamente el entorno ML
php artisan ml:verify-env --fix
```

#### 2. **Limpiar Caches del Sistema**
```bash
# Limpiar todos los caches de Laravel
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

#### 3. **Verificar Base de Datos**
```bash
# Ejecutar migraciones pendientes (si las hay)
php artisan migrate --force

# Verificar conexión a BD y estadísticas
php artisan statistics:update
```

#### 4. **Actualizar Datos de Partidos**
```bash
# Actualizar partidos del día actual
php artisan football:update-today --silent
```

#### 5. **Optimizar Aplicación**
```bash
# Optimizar autoloader y configuración
composer dump-autoload --optimize
php artisan config:cache
php artisan route:cache
```

#### 6. **Verificación Final**
```bash
# Test de funcionamiento ML
php artisan ml:verify-env

# Verificar que la app esté funcionando
curl -s http://localhost:8000/api/live-data | jq '.counters'
```

---

## 🔧 **Comandos Adicionales (Si es necesario)**

### **Si hay problemas con permisos:**
```bash
chmod -R 755 storage bootstrap/cache
chmod -R 777 storage/logs storage/framework
```

### **Si hay problemas con ML Environment:**
```bash
# Reconfigurar entorno ML desde cero
./setup_ml_env.sh
```

### **Si hay problemas con estadísticas:**
```bash
# Forzar actualización completa de estadísticas
php artisan statistics:force-update
```

### **Si hay problemas con presupuestos:**
```bash
# Sincronizar balances de presupuestos
php artisan budget:sync-balances
```

---

## 📊 **Verificaciones de Estado**

### **Contadores de la App:**
```bash
# Verificar contadores principales
curl -s http://localhost:8000/ | grep -A2 -B2 "text-lg font-medium text-gray-900"
```

### **Estado de Estadísticas:**
```bash
# Verificar que las estadísticas no muestren 0%
curl -s http://localhost:8000/statistics/card-values | jq '.cards[] | {name: .name, percentage: .percentage}'
```

### **Predicciones ML:**
```bash
# Test de predicción con equipos reales
curl -s http://localhost:8000/debug/match/17304 | jq '.calculated_percentages'
```

---

## ⚡ **Script de Ejecución Rápida**

### **Script para Recuperación Completa (Después de días apagada):**
```bash
# Crear archivo de script para recuperación completa
cat > recuperacion_datos.sh << 'EOF'
#!/bin/bash
echo "🔄 Iniciando recuperación de datos perdidos..."

echo "1. Verificando entorno ML..."
php artisan ml:verify-env --fix

echo "2. Recuperando partidos perdidos (últimos 7 días)..."
php artisan football:sync-historical --days=7

echo "3. Actualizando resultados finalizados..."
php artisan football:update-results --from=7days-ago

echo "4. Regenerando predicciones faltantes..."
php artisan ml:generate-missing-predictions --days=7

echo "5. Recalculando estadísticas completas..."
php artisan statistics:recalculate-all

echo "6. Limpiando caches..."
php artisan cache:clear

echo "7. Optimizando aplicación..."
php artisan config:cache && php artisan route:cache

echo "✅ Recuperación de datos completada!"
EOF

# Dar permisos de ejecución
chmod +x recuperacion_datos.sh
```

### **Script para Reinicio Normal:**
```bash
# Crear archivo de script para reinicio normal
cat > reinicio_app.sh << 'EOF'
#!/bin/bash
echo "🚀 Iniciando workflow de reinicio..."

echo "1. Verificando entorno ML..."
php artisan ml:verify-env --fix

echo "2. Limpiando caches..."
php artisan cache:clear && php artisan config:clear && php artisan view:clear

echo "3. Actualizando estadísticas..."
php artisan statistics:update

echo "4. Actualizando partidos..."
php artisan football:update-today --silent

echo "5. Optimizando aplicación..."
php artisan config:cache && php artisan route:cache

echo "6. Verificación final..."
php artisan ml:verify-env

echo "✅ Workflow completado!"
EOF

# Dar permisos de ejecución
chmod +x reinicio_app.sh
```

### **Ejecutar scripts:**
```bash
# Para recuperación después de días apagada:
./recuperacion_datos.sh

# Para reinicio normal (mismo día):
./reinicio_app.sh
```

---

## 📅 **Comandos Específicos para Recuperación de Datos**

### **Recuperar partidos de días específicos:**
```bash
# Recuperar partidos de los últimos X días
php artisan football:sync-historical --days=X

# Recuperar partidos de fechas específicas
php artisan football:sync-date --date=2025-08-20
php artisan football:sync-date --date=2025-08-21
php artisan football:sync-date --date=2025-08-22
```

### **Actualizar resultados perdidos:**
```bash
# Actualizar todos los partidos sin resultado final
php artisan football:update-missing-results

# Actualizar partidos de rango de fechas
php artisan football:update-results --from=2025-08-20 --to=2025-08-23
```

### **Regenerar predicciones faltantes:**
```bash
# Generar predicciones para partidos sin predicción
php artisan ml:generate-missing-predictions

# Generar predicciones para fechas específicas
php artisan ml:predict-date --date=2025-08-21
```

---

## 🚨 **Troubleshooting Común**

### **Si falla ml:verify-env:**
```bash
# Revisar logs
tail -50 storage/logs/laravel.log

# Reconfigurar desde cero
./setup_ml_env.sh
```

### **Si fallan las estadísticas:**
```bash
# Verificar conexión BD
php artisan tinker --execute="dump(\App\Models\FootballMatch::count());"

# Limpiar cache específico de estadísticas
php artisan cache:forget current_accuracy
```

### **Si fallan las predicciones:**
```bash
# Verificar campos de predicción
curl -s http://localhost:8000/statistics/debug | jq '.statistics_count'

# Test de predicción individual
php artisan ml:verify-env
```

---

## 📝 **Notas Importantes**

- ⚠️ **Ejecutar comandos en orden**: Algunos comandos dependen de otros
- ⏱️ **Tiempo estimado**: 3-5 minutos para workflow completo  
- 🔄 **Frecuencia recomendada**: Cada vez que se reinicie la aplicación
- 📊 **Verificar resultados**: Siempre verificar que contadores y estadísticas funcionen
- 🚀 **ML Environment**: Crítico que funcione para predicciones

---

**✅ ESTADO OBJETIVO FINAL:**
- Entorno ML: ✅ Funcionando
- Estadísticas: ✅ Datos reales (no 0%)
- Contadores: ✅ Valores correctos 
- Predicciones: ✅ Porcentajes reales
- Cache: ✅ Limpio y optimizado