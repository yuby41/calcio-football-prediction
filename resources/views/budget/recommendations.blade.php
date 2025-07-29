@extends('layouts.app')

@section('title', 'Recomendaciones de Apuestas - ' . $budget->name)

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4 mb-4">
            <a href="{{ route('budget.show', $budget) }}" 
               class="inline-flex items-center text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Volver al Budget
            </a>
        </div>
        
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Recomendaciones de Apuestas</h1>
                <p class="mt-2 text-gray-600">
                    Análisis inteligente para: <span class="font-medium">{{ $budget->name }}</span> •
                    Estrategia: <span class="font-medium">{{ ucfirst($budget->strategy) }}</span>
                </p>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-500">Balance actual</div>
                <div class="text-2xl font-bold text-gray-900">€{{ number_format($budget->current_budget, 2) }}</div>
            </div>
        </div>
    </div>

    <!-- Filtros y configuración -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confianza Mínima</label>
                    <div class="text-lg font-semibold text-blue-600">{{ $budget->min_confidence }}%</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Máx. por Apuesta</label>
                    <div class="text-lg font-semibold text-green-600">{{ $budget->max_bet_percentage }}%</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estrategia</label>
                    <div class="text-lg font-semibold text-purple-600">{{ ucfirst($budget->strategy) }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Partidos Analizados</label>
                    <div class="text-lg font-semibold text-gray-900" id="matches-count">Cargando...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recomendaciones -->
    <div id="recommendations-container">
        <div class="flex items-center justify-center py-12">
            <div class="text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
                <p class="text-gray-500">Analizando partidos y generando recomendaciones...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadRecommendations();
});

function loadRecommendations() {
    fetch('{{ route("budget.opportunities", $budget) }}')
        .then(response => response.json())
        .then(data => {
            document.getElementById('matches-count').textContent = data.length;
            renderRecommendations(data);
        })
        .catch(error => {
            console.error('Error loading recommendations:', error);
            showError('Error cargando recomendaciones: ' + error.message);
        });
}

function renderRecommendations(recommendations) {
    const container = document.getElementById('recommendations-container');
    
    if (recommendations.length === 0) {
        container.innerHTML = `
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay recomendaciones disponibles</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        No se encontraron partidos con predicciones que cumplan tus criterios de confianza.
                    </p>
                </div>
            </div>
        `;
        return;
    }

    let html = '';
    
    recommendations.forEach((recommendation, index) => {
        html += renderMatchRecommendation(recommendation, index);
    });
    
    container.innerHTML = html;
}

function renderMatchRecommendation(data, index) {
    const match = data.match;
    const recommendations = data.recommendations;
    const analysis = data.match_analysis;
    
    const matchDate = new Date(match.match_date);
    const isToday = matchDate.toDateString() === new Date().toDateString();
    
    return `
        <div class="bg-white shadow rounded-lg mb-6">
            <!-- Match Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="text-center">
                            <div class="text-lg font-bold text-gray-900">${match.home_team.name}</div>
                            <div class="text-sm text-gray-500">Local</div>
                        </div>
                        <div class="text-center px-4">
                            <div class="text-sm text-gray-500">vs</div>
                            <div class="text-xs text-gray-400">${analysis.expected_goals.home} - ${analysis.expected_goals.away}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-gray-900">${match.away_team.name}</div>
                            <div class="text-sm text-gray-500">Visitante</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium ${isToday ? 'text-red-600' : 'text-gray-700'}">
                            ${matchDate.toLocaleDateString('es-ES', { 
                                weekday: 'short', 
                                month: 'short', 
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            })}
                        </div>
                        <div class="text-xs text-gray-500">
                            Dificultad: <span class="font-medium">${analysis.match_difficulty}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Match Analysis -->
            <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-center">
                    <div>
                        <div class="text-sm text-gray-500">Resultado más probable</div>
                        <div class="font-medium">${analysis.most_likely_outcome}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Confianza general</div>
                        <div class="font-medium">${analysis.overall_confidence}%</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Goles esperados</div>
                        <div class="font-medium">${analysis.total_goals_expected}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Apuestas valor</div>
                        <div class="font-medium">${analysis.value_bets.length}</div>
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            <div class="px-6 py-4">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Recomendaciones (${recommendations.length})
                </h3>
                
                <div class="space-y-4">
                    ${recommendations.map(rec => renderRecommendation(rec, match.id)).join('')}
                </div>
            </div>
        </div>
    `;
}

function renderRecommendation(rec, matchId) {
    const levelColors = {
        'EXCELENTE': 'green',
        'MUY BUENA': 'blue',
        'BUENA': 'indigo',
        'ACEPTABLE': 'gray'
    };
    
    const riskColors = {
        'BAJO': 'green',
        'MEDIO': 'yellow',
        'ALTO': 'red'
    };
    
    const levelColor = levelColors[rec.recommendation_level] || 'gray';
    const riskColor = riskColors[rec.risk_level] || 'gray';
    
    return `
        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h4 class="text-lg font-semibold text-gray-900">${rec.label}</h4>
                    <p class="text-sm text-gray-600">${rec.description}</p>
                </div>
                <div class="flex space-x-2">
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-${levelColor}-100 text-${levelColor}-800">
                        ${rec.recommendation_level}
                    </span>
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-${riskColor}-100 text-${riskColor}-800">
                        Riesgo ${rec.risk_level}
                    </span>
                </div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-3">
                <div>
                    <div class="text-xs text-gray-500">Confianza IA</div>
                    <div class="font-bold text-lg text-blue-600">${rec.confidence}%</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Odds ${getOddsSourceLabel(rec.odds_source)}</div>
                    <div class="font-bold text-lg text-green-600">${rec.estimated_odds}</div>
                    <div class="text-xs text-blue-600">📊 Casa de apuestas</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Cantidad recomendada</div>
                    <div class="font-bold text-lg text-purple-600">€${rec.recommended_amount}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Ganancia potencial</div>
                    <div class="font-bold text-lg text-green-600">€${rec.potential_profit}</div>
                </div>
            </div>
            
            <!-- Strategy Fit -->
            <div class="bg-blue-50 rounded-lg p-3 mb-3">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="font-medium text-blue-900">Compatibilidad con tu estrategia</h5>
                    <span class="px-2 py-1 text-xs font-medium rounded bg-blue-200 text-blue-800">
                        ${rec.strategy_fit.compatibility}
                    </span>
                </div>
                <p class="text-sm text-blue-800">${rec.strategy_fit.reason}</p>
                <p class="text-xs text-blue-600 mt-1">Confianza ideal: ${rec.strategy_fit.ideal_confidence}</p>
            </div>
            
            <!-- Value Betting Indicator -->
            ${rec.value_rating && rec.value_rating > 5 ? `
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-3">
                    <div class="flex items-center">
                        <div class="text-yellow-600 mr-2">⭐</div>
                        <div>
                            <div class="font-medium text-yellow-900">Value Bet detectado</div>
                            <div class="text-sm text-yellow-800">+${rec.value_rating}% de valor según nuestro análisis</div>
                        </div>
                    </div>
                </div>
            ` : ''}
            
            <!-- Rationale -->
            <div class="text-sm text-gray-600 mb-4">
                <strong>Análisis:</strong> ${rec.rationale}
            </div>
            
            <!-- Action Button -->
            <div class="flex space-x-3">
                <button onclick="placeBet('${matchId}', '${rec.bet_type}', ${rec.estimated_odds}, ${rec.recommended_amount})"
                        class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Apostar €${rec.recommended_amount}
                </button>
                <button onclick="customBet('${matchId}', '${rec.bet_type}', ${rec.estimated_odds}, ${rec.confidence})"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50">
                    Personalizar
                </button>
            </div>
        </div>
    `;
}

function placeBet(matchId, betType, odds, amount) {
    // Asegurar que amount sea entero
    amount = parseInt(amount);
    
    // Implementar lógica de apuesta
    if (confirm(`¿Confirmar apuesta de €${amount} en ${getBetTypeDisplay(betType)} con odds ${odds}?`)) {
        // Llamar al endpoint de apuesta
        fetch(`{{ route('budget.place-bet', $budget) }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                match_id: matchId,
                bet_type: betType,
                odds: odds,
                amount: amount
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Apuesta realizada con éxito');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'No se pudo realizar la apuesta'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al realizar la apuesta');
        });
    }
}

function customBet(matchId, betType, originalOdds, confidence) {
    // Crear modal personalizado para la configuración
    const modalHtml = `
        <div id="customBetModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Personalizar Apuesta</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Apuesta:</label>
                        <div class="text-sm text-gray-600 bg-gray-50 p-2 rounded">${getBetTypeDisplay(betType)}</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="customOdds" class="block text-sm font-medium text-gray-700 mb-1">Cuota:</label>
                        <input type="number" id="customOdds" value="${originalOdds}" step="0.01" min="1.01" max="50" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               onchange="updateCustomAmount()" oninput="updateCustomAmount()">
                        <div id="oddsIndicator" class="text-xs mt-1"></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confianza IA:</label>
                        <div class="text-sm text-gray-600">${confidence}%</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="customAmount" class="block text-sm font-medium text-gray-700 mb-1">Cantidad a Apostar:</label>
                        <input type="number" id="customAmount" step="1" min="2" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               onchange="updatePotentialWin()">
                        <div class="text-xs text-gray-500 mt-1">Cantidad mínima: €2 (números enteros)</div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ganancia Potencial:</label>
                        <div id="potentialWin" class="text-lg font-semibold text-green-600">€0.00</div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button onclick="confirmCustomBet('${matchId}', '${betType}')" 
                                class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700">
                            Confirmar Apuesta
                        </button>
                        <button onclick="closeCustomBetModal()" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Insertar modal en el DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Calcular cantidad inicial basada en las odds originales
    calculateInitialAmount(confidence);
}

function getBetTypeDisplay(betType) {
    const types = {
        'home_win': 'Victoria Local',
        'away_win': 'Victoria Visitante',
        'draw': 'Empate',
        'over_2_5': 'Más de 2.5 Goles',
        'under_2_5': 'Menos de 2.5 Goles',
        'both_teams_score': 'Ambos Equipos Marcan'
    };
    return types[betType] || betType;
}

function getOddsSourceLabel(source) {
    if (!source) return 'bet365';
    
    const sources = {
        'bet365_real': 'bet365',
        'pinnacle_real': 'Pinnacle',
        'betfair_real': 'Betfair',
        'unibet_real': 'Unibet',
        '1xbet_real': '1xBet',
        'marathonbet_real': 'Marathon',
        'bet365_fallback': 'bet365 (equiv.)',
        'pinnacle_fallback': 'Pinnacle (equiv.)',
        'footballapi_fallback': 'Casa apuestas (equiv.)',
        'fallback_realistic': 'Casa apuestas (equiv.)'
    };
    return sources[source] || 'Casa apuestas';
}

function calculateInitialAmount(confidence) {
    // Simular cálculo de cantidad recomendada basada en confianza
    // Esto debería idealmente llamar al backend, pero por simplicidad usamos una fórmula básica
    const currentBudget = {{ $budget->current_budget }};
    const maxBetPercentage = {{ $budget->max_bet_percentage }};
    
    // Calcular cantidad base
    let baseAmount = (currentBudget * maxBetPercentage) / 100;
    
    // Ajustar por confianza (más confianza = más dinero)
    let confidenceMultiplier = confidence / 100;
    let recommendedAmount = baseAmount * confidenceMultiplier;
    
    // Aplicar límites (mínimo €2, números enteros)
    recommendedAmount = Math.max(2, Math.min(recommendedAmount, currentBudget));
    recommendedAmount = Math.floor(recommendedAmount); // Convertir a entero
    
    document.getElementById('customAmount').value = recommendedAmount;
    
    // Inicializar indicador con las odds originales
    const originalOdds = parseFloat(document.getElementById('customOdds').value);
    updateOddsIndicator(originalOdds);
    updatePotentialWin();
}

function updateCustomAmount() {
    const odds = parseFloat(document.getElementById('customOdds').value);
    
    if (odds >= 1.01 && odds <= 50) {
        // Recalcular cantidad recomendada basada en nuevas odds
        const currentBudget = {{ $budget->current_budget }};
        const maxBetPercentage = {{ $budget->max_bet_percentage }};
        
        // Calcular cantidad base
        let baseAmount = (currentBudget * maxBetPercentage) / 100;
        
        // Ajustar inversamente por odds (odds más altas = apostar menos)
        // Fórmula: cantidad = base_amount * (2.0 / odds) 
        // Esto significa que con odds 2.0 apostamos la cantidad base
        // Con odds 4.0 apostamos la mitad, con odds 1.5 apostamos más
        let adjustedAmount = baseAmount * (2.0 / odds);
        
        // Aplicar límites (mínimo €2, números enteros)
        adjustedAmount = Math.max(2, Math.min(adjustedAmount, currentBudget));
        adjustedAmount = Math.floor(adjustedAmount); // Convertir a entero
        
        document.getElementById('customAmount').value = adjustedAmount;
        
        // Actualizar indicador de riesgo
        updateOddsIndicator(odds);
        updatePotentialWin();
    }
}

function updateOddsIndicator(odds) {
    const indicator = document.getElementById('oddsIndicator');
    let message = '';
    let colorClass = '';
    
    if (odds < 1.5) {
        message = '🟢 Riesgo bajo - Cantidad aumentada automáticamente (mín. €2)';
        colorClass = 'text-green-600';
    } else if (odds < 2.5) {
        message = '🟡 Riesgo medio - Cantidad balanceada (enteros)';
        colorClass = 'text-yellow-600';
    } else if (odds < 4.0) {
        message = '🟠 Riesgo alto - Cantidad reducida automáticamente';
        colorClass = 'text-orange-600';
    } else {
        message = '🔴 Riesgo muy alto - Cantidad mínima €2';
        colorClass = 'text-red-600';
    }
    
    indicator.innerHTML = `<span class="${colorClass}">${message}</span>`;
}

function updatePotentialWin() {
    const odds = parseFloat(document.getElementById('customOdds').value) || 0;
    const amount = parseInt(document.getElementById('customAmount').value) || 0;
    
    if (odds > 0 && amount >= 2) {
        const potentialWin = (amount * odds) - amount;
        document.getElementById('potentialWin').textContent = `€${potentialWin.toFixed(2)}`;
    } else {
        document.getElementById('potentialWin').textContent = '€0.00';
    }
}

function confirmCustomBet(matchId, betType) {
    const odds = parseFloat(document.getElementById('customOdds').value);
    const amount = parseInt(document.getElementById('customAmount').value);
    
    // Validaciones
    if (!odds || odds < 1.01 || odds > 50) {
        alert('Por favor ingresa una cuota válida entre 1.01 y 50');
        return;
    }
    
    if (!amount || amount < 2 || !Number.isInteger(amount)) {
        alert('Por favor ingresa una cantidad válida de al menos €2 (números enteros)');
        return;
    }
    
    if (amount > {{ $budget->current_budget }}) {
        alert('La cantidad excede tu budget disponible');
        return;
    }
    
    // Cerrar modal
    closeCustomBetModal();
    
    // Realizar apuesta
    placeBet(matchId, betType, odds, amount);
}

function closeCustomBetModal() {
    const modal = document.getElementById('customBetModal');
    if (modal) {
        modal.remove();
    }
}

function showError(message) {
    document.getElementById('recommendations-container').innerHTML = `
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
            <svg class="mx-auto h-12 w-12 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
            <h3 class="text-lg font-medium text-red-900 mb-2">Error</h3>
            <p class="text-red-700">${message}</p>
        </div>
    `;
}
</script>
@endsection