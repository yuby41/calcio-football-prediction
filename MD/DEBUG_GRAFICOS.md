# 🔧 Debug de Gráficos - Pasos para Solucionar

## ✅ Estado Actual de la Implementación

### 1. API Endpoints funcionando
- ✅ http://localhost:8000/budget/1/chart-data 
- ✅ http://localhost:8000/budget/2/chart-data
- ✅ Devuelven JSON válido con arrays `dates` y `balances`

### 2. Datos históricos disponibles
- ✅ Budget 1 (Mansaniello): 66 puntos de datos
- ✅ Budget 2 (Fibonacci): 43 puntos de datos

### 3. JavaScript implementado con debug completo
- ✅ Verificación de Chart.js cargado
- ✅ Verificación de elemento canvas
- ✅ Manejo de errores robusto
- ✅ Console.log en cada paso
- ✅ Fallback visual con mensaje de error

## 🔍 Para Diagnosticar el Problema

### Paso 1: Abrir DevTools
1. Visita: http://localhost:8000/budget/1
2. Presiona **F12** para abrir DevTools
3. Ve a la pestaña **Console**

### Paso 2: Revisar Mensajes de Console
Deberías ver estos mensajes en orden:
```javascript
DOM loaded, initializing chart...
Chart.js version: 4.x.x
Inline data: {dates: [...], balances: [...]}
Using inline data
Creating chart with data: {...}
Chart created successfully
```

### Paso 3: Si hay errores
Los mensajes de error te dirán exactamente qué está fallando:

**Posibles errores y soluciones:**

1. **"Chart.js not loaded"**
   - Problema: Chart.js no se carga desde CDN
   - Solución: Verificar conexión internet

2. **"Canvas element not found"**
   - Problema: Error en el HTML
   - Verificar que existe `<canvas id="budgetChart">`

3. **"Datos inválidos recibidos"**
   - Problema: El API no devuelve datos correctos
   - Verificar: curl http://localhost:8000/budget/1/chart-data

4. **"Error al crear el gráfico"**
   - Problema: Chart.js no puede renderizar
   - Posible causa: Datos corruptos o configuración inválida

## 🛠️ Soluciones Rápidas

### Si no ves ningún mensaje en console:
```javascript
// Ejecuta esto en la console del navegador para verificar:
console.log('Chart disponible:', typeof Chart);
console.log('Canvas disponible:', document.getElementById('budgetChart'));
```

### Si Chart.js no se carga:
- Verificar conexión internet
- Usar una versión local de Chart.js

### Si los datos llegan pero no se ve el gráfico:
```javascript
// Ejecuta esto para crear un gráfico de prueba:
const canvas = document.getElementById('budgetChart');
if (canvas) {
    const ctx = canvas.getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['A', 'B', 'C'],
            datasets: [{
                data: [1, 2, 3],
                borderColor: 'blue'
            }]
        }
    });
}
```

## 📱 URLs de Prueba
- **Mansaniello**: http://localhost:8000/budget/1
- **Fibonacci**: http://localhost:8000/budget/2
- **API Test**: http://localhost:8000/budget/1/chart-data

## 💡 Características del Gráfico Implementado
- **Tipo**: Línea temporal
- **Datos**: Evolución real del budget
- **Tooltips**: Formato en euros
- **Responsive**: Se adapta al contenedor
- **Colores**: Azul (#3B82F6) con fill transparente

El gráfico debería mostrar una línea que sube y baja siguiendo las ganancias/pérdidas de las apuestas históricas que creamos.