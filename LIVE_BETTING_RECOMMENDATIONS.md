# Sistema de Recomendaciones de Apuestas en Vivo

## ✅ **Implementación Completada**

El sistema de recomendaciones de la IA ahora incluye **partidos en vivo** con características avanzadas para apuestas durante el desarrollo del partido.

## 🔴 **Características de Partidos en Vivo**

### 1. **Detección Automática**
- ✅ Incluye partidos con status `'live'`
- ✅ Filtra partidos activos de las últimas 24 horas
- ✅ Prioriza partidos en vivo sobre partidos programados
- ✅ Límite de 3 horas máximo para partidos en vivo

### 2. **Información en Tiempo Real**
```php
'live_info' => [
    'current_score' => '1-3',           // Marcador actual
    'time_elapsed' => 45,               // Minutos transcurridos
    'time_display' => "45' (2T)",       // Tiempo formateado
    'live_status' => '🔴 EN VIVO',      // Indicador visual
    'volatility' => 'MUY ALTA',         // Volatilidad del partido
    'betting_window' => 'ABIERTO'       // Ventana de apuestas
]
```

### 3. **Odds Dinámicas para Partidos en Vivo**
El sistema ajusta automáticamente las odds considerando:

#### Factores de Volatilidad
- **Tiempo transcurrido**: Más volatilidad al inicio y final del partido
- **Marcador actual**: Partidos igualados = mayor volatilidad
- **Tipo de apuesta**: Mercados de goles más volátiles que resultados

#### Ejemplos de Volatilidad por Tipo
```php
'home_win' => 20% volatilidad base
'draw' => 30% volatilidad base  
'over_2_5' => 35% volatilidad base (extrema)
'both_teams_score' => 25% volatilidad base
```

### 4. **Sistema de Priorización**
1. **🔴 Partidos EN VIVO** - Prioridad máxima
2. **⏰ Partidos próximos (< 2h)** - Urgencia alta
3. **📅 Partidos del día** - Urgencia media
4. **📋 Partidos de la semana** - Urgencia baja

### 5. **Indicadores Visuales**
- **🔴 EN VIVO** - Partido actualmente en juego
- **Marcador**: 1-3, 0-0, 2-1, etc.
- **Tiempo**: 45', 67' (2T), 90'+3'
- **Urgencia**: ALTA para todos los partidos en vivo

## 📊 **Resultados de Prueba**

### Ejemplo de Recomendaciones en Vivo:
```
Recomendación 1:
  Partido: Sutton Utd vs Millwall
  Estado: live
  En vivo: SÍ
  Marcador actual: 1-3
  Tiempo: 45' (2T)
  Volatilidad: MEDIA
  Ventana de apuestas: ABIERTO
  
  Oportunidades:
    1. Ambos Marcan
       Confianza: 90%
       Odds: 2.41 (ajustadas por volatilidad)
       Nivel: EXCELENTE
       🔴 EN VIVO
    
    2. Más de 2.5 Goles  
       Confianza: 82%
       Odds: 3.03 (ajustadas por volatilidad)
       Nivel: EXCELENTE
       🔴 EN VIVO
```

## 🛠️ **Implementación Técnica**

### Controladores Actualizados
```php
// BudgetController - getBettingOpportunities()
->whereIn('status', ['scheduled', 'live'])
->orderByRaw("CASE WHEN status = 'live' THEN 0 ELSE 1 END")

// Información adicional para partidos en vivo
'is_live' => $match->status === 'live',
'live_indicator' => '🔴 EN VIVO',
'urgency' => 'ALTA',
'current_score' => "1-3"
```

### Servicios Mejorados
```php
// BettingRecommendationService - getRecommendationsForBudget()
->whereIn('status', ['scheduled', 'live'])

// Información específica de partidos en vivo
'live_info' => $this->getLiveMatchInfo($match),
'is_live' => $match->status === 'live'
```

### Algoritmos de Volatilidad
```php
private function calculateLiveVolatilityForBetting($betType, $timeElapsed, $match)
{
    // Base: 20-35% según tipo de apuesta
    // Ajustes por tiempo: +50% volatilidad extra en final de partido
    // Ajustes por marcador: +40% si está empatado
    // Resultado: Odds más dinámicas y realistas
}
```

## 🎯 **Ventajas del Sistema**

### Para el Usuario
1. **Oportunidades en tiempo real** - No perder partidos que ya comenzaron
2. **Información completa** - Marcador, tiempo, volatilidad
3. **Priorización inteligente** - Los partidos más urgentes primero
4. **Odds ajustadas** - Reflejan la realidad del partido en curso

### Para la IA
1. **Predicciones contextuales** - Considera el estado actual del partido
2. **Volatilidad dinámica** - Odds que cambian según circunstancias
3. **Ventana de oportunidad** - Detecta cuándo cerrar apuestas (75+ min)
4. **Análisis de momentum** - Partidos igualados vs dominados

## 🔧 **Configuración Actual**

### Filtros de Partidos en Vivo
- **Tiempo máximo**: 3 horas desde inicio
- **Estado requerido**: `'live'`
- **Fecha límite**: Últimas 24 horas
- **Predicción**: Requerida para recomendación

### Límites de Apuestas en Vivo
- **Odds mínimas**: 1.01
- **Odds máximas**: 25.0 (BudgetController) / 50.0 (fallback)
- **Tiempo límite sugerido**: 75 minutos
- **Volatilidad máxima**: 35% para mercados de goles

## 📱 **Uso en la Interfaz**

### Endpoint de Oportunidades
```javascript
GET /budget/{budget}/opportunities

Response incluye:
{
  "match_name": "Real Madrid vs Barcelona",
  "is_live": true,
  "live_indicator": "🔴 EN VIVO", 
  "current_score": "2-1",
  "urgency": "ALTA",
  "match_status": "live",
  "odds": 2.85 // Ajustadas por volatilidad
}
```

### Endpoint de Recomendaciones
```javascript
GET /budget/{budget}/recommendations

Response incluye:
{
  "is_live": true,
  "live_info": {
    "current_score": "1-1",
    "time_display": "67' (2T)",
    "volatility": "MUY ALTA",
    "betting_window": "ABIERTO"
  }
}
```

## ✅ **Estado Final**

**COMPLETADO** ✅ - El sistema de recomendaciones ahora incluye:

- ✅ **Detección de partidos en vivo**
- ✅ **Priorización automática** 
- ✅ **Odds dinámicas con volatilidad**
- ✅ **Información en tiempo real** (marcador, tiempo)
- ✅ **Indicadores visuales** (🔴 EN VIVO)
- ✅ **Sistema de urgencia** contextual
- ✅ **Ventana de apuestas** inteligente
- ✅ **Análisis de volatilidad** por tipo de apuesta
- ✅ **Compatibilidad completa** con sistema existente

La IA ahora puede recomendar tanto partidos programados como **partidos en vivo**, proporcionando una experiencia de apuestas completa y en tiempo real.