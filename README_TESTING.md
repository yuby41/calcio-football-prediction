# Guía de Pruebas - Calcio Budget Management

## ✅ Problema Resuelto

### 1. Notificación sin botón de cerrar
- **Problema**: La notificación de "Resolver apuestas" no tenía botón de cerrar
- **Solución**: Agregado botón X y auto-close después de 5 segundos
- **Archivos modificados**: `resources/views/budget/show.blade.php`

### 2. Falta de datos históricos para pruebas
- **Problema**: Sin apuestas históricas para probar el sistema
- **Solución**: Comando para generar datos de prueba realistas

## 🔧 Comandos de Prueba

### Crear datos de prueba para budget
```bash
# Crear 15 apuestas de prueba para budget ID 1
php artisan budget:simple-test-bets 1 --count=15

# Crear más datos históricos
php artisan budget:simple-test-bets 1 --count=25
```

### Ver budgets disponibles
```bash
mysql -h 192.168.56.56 -u homestead -psecret calcio -e "SELECT id, name FROM budget_configurations;"
```

## 📊 Datos de Prueba Generados

El comando `budget:simple-test-bets` crea:
- ✅ Equipos de prueba (Real Madrid, Barcelona, Manchester United, Liverpool)
- ✅ Partidos históricos con resultados
- ✅ Apuestas variadas con diferentes tipos:
  - `match_result` (Resultado del partido)
  - `both_teams_score` (Ambos equipos marcan)
  - `over_under_2_5` (Más/Menos de 2.5 goles)
- ✅ Win rate realista (~60%)
- ✅ Amounts variables según estrategia
- ✅ Fechas históricas (últimos 30 días)
- ✅ Historial de budget automático

## 🎯 Funcionalidades Probadas

### ✅ Sistema de Notificaciones
- Botón X para cerrar manualmente
- Auto-close después de 5 segundos
- Transición suave con opacity

### ✅ Gráfico de Evolución del Budget
- Chart.js integrado
- Datos en tiempo real via API
- Responsive design
- Tooltips con formato de moneda

### ✅ Métricas Calculadas
- **Balance actual**: Actualizado con cada apuesta
- **Beneficio neto**: Initial vs Current budget
- **Win Rate**: Porcentaje de apuestas ganadas
- **ROI**: Return on Investment

### ✅ Estrategia Mansaniello
- Secuencia implementada: 1,1,2,2,3,4,5,7,9,12...
- Reset al ganar
- Progresión controlada con límites

## 🔍 Pruebas Realizadas

### Budget ID 1 - "Mansaniello Demo - Dic 2024"
```
📊 Resultados de prueba:
   Apuestas creadas: 15
   Balance inicial: €500.00
   Balance actual: €493.00
   Beneficio neto: €-7.00
   Win Rate: 53.3%
```

### URLs de Prueba
- **Dashboard**: http://localhost:8000/budget
- **Budget específico**: http://localhost:8000/budget/1
- **Crear nuevo**: http://localhost:8000/budget/create

## 🛠️ Debugging

### Verificar datos en base
```sql
-- Ver apuestas del budget
SELECT COUNT(*) as total_bets, 
       SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won_bets
FROM bets 
WHERE budget_configuration_id = 1;

-- Ver historial de budget
SELECT type, amount, balance_after, created_at 
FROM budget_history 
WHERE budget_configuration_id = 1 
ORDER BY created_at DESC 
LIMIT 10;
```

### Logs útiles
```bash
# Ver logs de Laravel
tail -f storage/logs/laravel.log

# Ver errores de base de datos
grep "SQLSTATE" storage/logs/laravel.log
```

## ✨ Mejoras Implementadas

1. **Sistema de notificaciones mejorado**
2. **Comando de datos de prueba**
3. **Gráfico Chart.js funcional**
4. **Documentación de testing**
5. **Verificación de estructura de BD**

El sistema está ahora completamente funcional para pruebas y desarrollo!