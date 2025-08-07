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
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Partidos Analizados</label>
                        <div class="text-lg font-semibold text-gray-900" id="matches-count">Cargando...</div>
                    </div>
                    <button onclick="refreshRecommendations()" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            id="refresh-btn">
                        🔄 Actualizar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Prediction Types -->
    @if(count($activePredictions) > 0)
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                    🎯 Tipos de Predicción Activos
                </h3>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    {{ count($activePredictions) }} activos de {{ $predictionsSummary['total_count'] }} disponibles
                </span>
            </div>
            <p class="text-sm text-gray-600 mb-4">
                Solo se muestran predicciones con >50% de precisión basadas en estadísticas históricas. 
                Actualizado: {{ $predictionsSummary['last_updated']->format('d/m/Y H:i') }}
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($activePredictions as $prediction)
                <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-medium text-gray-900">{{ $prediction['display_name'] }}</h4>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                     @if($prediction['accuracy'] >= 70) bg-green-100 text-green-800
                                     @elseif($prediction['accuracy'] >= 60) bg-yellow-100 text-yellow-800
                                     @else bg-blue-100 text-blue-800 @endif">
                            {{ number_format($prediction['accuracy'], 1) }}%
                        </span>
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $prediction['correct_predictions'] }}/{{ $prediction['total_predictions'] }} predicciones correctas
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                        <div class="bg-gradient-to-r from-blue-500 to-green-500 h-2 rounded-full transition-all duration-300" 
                             style="width: {{ $prediction['accuracy'] }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @else
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Sin Predicciones Confiables</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Actualmente no hay tipos de predicción con precisión superior al 50%. Las recomendaciones pueden ser limitadas.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

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
    return fetch('{{ route("budget.opportunities", $budget) }}')
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
    
    // Separar partidos en vivo de programados
    const liveMatches = recommendations.filter(match => match.is_live);
    const scheduledMatches = recommendations.filter(match => !match.is_live);
    
    // Render partidos en vivo primero con encabezado especial
    if (liveMatches.length > 0) {
        html += `
            <div class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-red-600 flex items-center">
                        <span class="animate-pulse mr-2">🔴</span>
                        PARTIDOS EN VIVO (${liveMatches.length})
                    </h2>
                </div>
            </div>
        `;
        liveMatches.forEach((match, index) => {
            html += renderMatchWithRecommendations(match, index);
        });
    }
    
    // Render partidos programados con encabezado
    if (scheduledMatches.length > 0) {
        html += `
            <div class="mb-6 ${liveMatches.length > 0 ? 'mt-8' : ''}">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-700 flex items-center">
                        <span class="mr-2">📅</span>
                        PARTIDOS PROGRAMADOS (${scheduledMatches.length})
                    </h2>
                </div>
            </div>
        `;
        scheduledMatches.forEach((match, index) => {
            html += renderMatchWithRecommendations(match, index + liveMatches.length);
        });
    }
    
    container.innerHTML = html;
}

function renderMatchWithRecommendations(matchData, index) {
    const liveIndicator = matchData.is_live ? '🔴 EN VIVO' : '';
    const liveClasses = matchData.is_live ? 'border-l-4 border-red-500 bg-red-50' : 'bg-white';
    const livePulse = matchData.is_live ? 'animate-pulse' : '';
    
    return `
        <div class="${liveClasses} shadow rounded-lg mb-6 ${livePulse}">
            <!-- Match Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="text-center">
                            <div class="text-lg font-bold text-gray-900">${matchData.home_team.short_name}</div>
                            <div class="text-sm text-gray-500">Local</div>
                        </div>
                        <div class="text-center px-4">
                            <div class="text-sm text-gray-500">vs</div>
                            <div class="text-xs text-gray-400">${matchData.prediction_details.home_goals_prediction} - ${matchData.prediction_details.away_goals_prediction}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-gray-900">${matchData.away_team.short_name}</div>
                            <div class="text-sm text-gray-500">Visitante</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium ${matchData.is_live ? 'text-red-700' : 'text-gray-700'}">
                            ${matchData.match_date}
                            ${matchData.is_live ? `<span class="text-red-600 ml-2 font-bold animate-pulse">${liveIndicator}</span>` : ''}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            <span class="font-medium">${matchData.league}</span>
                            ${matchData.round ? ` • Jornada ${matchData.round}` : ''}
                        </div>
                        ${matchData.current_score ? `<div class="text-sm font-bold text-red-600 mt-1">${matchData.current_score}</div>` : ''}
                    </div>
                </div>
            </div>

            <!-- Match Analysis -->
            <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-center">
                    <div>
                        <div class="text-sm text-gray-500">Goles esperados</div>
                        <div class="font-medium">${matchData.prediction_details.total_goals_prediction.toFixed(1)}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Confianza máxima</div>
                        <div class="font-medium">${parseFloat(matchData.max_confidence).toFixed(2)}%</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Recomendaciones</div>
                        <div class="font-medium">${matchData.recommendations.length}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Modelo IA</div>
                        <div class="font-medium text-xs">${matchData.prediction_details.model_version}</div>
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            <div class="px-6 py-4">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Recomendaciones (${matchData.recommendations.length})
                </h3>
                
                <div class="space-y-4">
                    ${matchData.recommendations.map(rec => renderSingleRecommendation(rec, matchData.match_id, matchData.is_live)).join('')}
                </div>
            </div>
        </div>
    `;
}

function renderSingleRecommendation(rec, matchId, isLive = false) {
    const levelColor = getRecommendationLevelColor(rec.analysis.recommendation_level);
    const riskColor = getRiskLevelColor(rec.analysis.risk_level);
    
    return `
        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow" data-match-id="${matchId}" data-bet-type="${rec.bet_type}">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h4 class="text-lg font-semibold text-gray-900">${rec.bet_type_display}</h4>
                    <p class="text-sm text-gray-600">Confianza IA: ${parseFloat(rec.confidence).toFixed(2)}%</p>
                </div>
                <div class="flex space-x-2">
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-${levelColor}-100 text-${levelColor}-800">
                        ${rec.analysis.recommendation_level}
                    </span>
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-${riskColor}-100 text-${riskColor}-800">
                        Riesgo ${rec.analysis.risk_level}
                    </span>
                </div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-3">
                <div>
                    <div class="text-xs text-gray-500">Confianza</div>
                    <div class="font-bold text-lg text-blue-600">${parseFloat(rec.confidence).toFixed(2)}%</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Cuota</div>
                    <div class="font-bold text-lg text-green-600">${rec.odds}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Cantidad</div>
                    <div class="font-bold text-lg text-purple-600">€${parseInt(rec.recommended_amount)}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Ganancia</div>
                    <div class="font-bold text-lg text-green-600">€${parseFloat(rec.potential_profit).toFixed(2)}</div>
                </div>
            </div>
            
            <!-- Value Betting Indicator -->
            ${rec.analysis.value_rating > 5 ? `
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-3">
                    <div class="flex items-center">
                        <div class="text-yellow-600 mr-2">⭐</div>
                        <div>
                            <div class="font-medium text-yellow-900">Value Bet detectado</div>
                            <div class="text-sm text-yellow-800">+${parseFloat(rec.analysis.value_rating).toFixed(2)}% de valor según nuestro análisis</div>
                        </div>
                    </div>
                </div>
            ` : ''}
            
            <!-- Action Buttons -->
            <div class="flex space-x-3">
                <button onclick="placeBet('${matchId}', '${rec.bet_type}', ${rec.odds}, ${rec.recommended_amount})"
                        class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Apostar €${parseInt(rec.recommended_amount)}
                </button>
                <button onclick="customBet('${matchId}', '${rec.bet_type}', ${rec.odds}, ${rec.confidence}, ${isLive})"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50">
                    Personalizar ${isLive ? '🔴' : ''}
                </button>
            </div>
        </div>
    `;
}

function renderSimpleRecommendation(rec, index) {
    const liveIndicator = rec.is_live ? '🔴 EN VIVO' : '';
    const urgencyColor = rec.urgency === 'ALTA' ? 'red' : rec.urgency === 'MEDIA' ? 'yellow' : 'green';
    const levelColor = getRecommendationLevelColor(rec.analysis.recommendation_level);
    const riskColor = getRiskLevelColor(rec.analysis.risk_level);
    
    return `
        <div class="bg-white shadow rounded-lg mb-6">
            <!-- Match Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="text-center">
                            <div class="text-lg font-bold text-gray-900">${rec.home_team.short_name}</div>
                            <div class="text-sm text-gray-500">Local</div>
                        </div>
                        <div class="text-center px-4">
                            <div class="text-sm text-gray-500">vs</div>
                            <div class="text-xs text-gray-400">${rec.prediction_details.home_goals_prediction} - ${rec.prediction_details.away_goals_prediction}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-gray-900">${rec.away_team.short_name}</div>
                            <div class="text-sm text-gray-500">Visitante</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-700">
                            ${rec.match_date}
                            ${rec.is_live ? `<span class="text-red-600 ml-2">${liveIndicator}</span>` : ''}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            <span class="font-medium">${rec.league}</span>
                            ${rec.round ? ` • Jornada ${rec.round}` : ''}
                        </div>
                        ${rec.current_score ? `<div class="text-sm font-bold text-red-600 mt-1">${rec.current_score}</div>` : ''}
                    </div>
                </div>
            </div>

            <!-- Match Analysis -->
            <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-center">
                    <div>
                        <div class="text-sm text-gray-500">Goles esperados</div>
                        <div class="font-medium">${rec.prediction_details.total_goals_prediction.toFixed(1)}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Dificultad</div>
                        <div class="font-medium">${rec.analysis.difficulty_level}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Valor detectado</div>
                        <div class="font-medium ${rec.analysis.value_rating > 5 ? 'text-green-600' : 'text-gray-600'}">
                            ${rec.analysis.value_rating > 0 ? '+' + parseFloat(rec.analysis.value_rating).toFixed(2) + '%' : 'Neutro'}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Modelo IA</div>
                        <div class="font-medium text-xs">${rec.prediction_details.model_version}</div>
                    </div>
                </div>
            </div>

            <!-- Recommendation Details -->
            <div class="px-6 py-4">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900">${rec.bet_type_display}</h4>
                        <p class="text-sm text-gray-600">Recomendación de la IA para este partido</p>
                    </div>
                    <div class="flex space-x-2">
                        <span class="px-3 py-1 text-xs font-medium rounded-full bg-${levelColor}-100 text-${levelColor}-800">
                            ${rec.analysis.recommendation_level}
                        </span>
                        <span class="px-3 py-1 text-xs font-medium rounded-full bg-${riskColor}-100 text-${riskColor}-800">
                            Riesgo ${rec.analysis.risk_level}
                        </span>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div>
                        <div class="text-xs text-gray-500">Confianza IA</div>
                        <div class="font-bold text-lg text-blue-600">${rec.confidence}%</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Cuota</div>
                        <div class="font-bold text-lg text-green-600">${rec.odds}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Cantidad recomendada</div>
                        <div class="font-bold text-lg text-purple-600">€${parseInt(rec.recommended_amount)}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Ganancia potencial</div>
                        <div class="font-bold text-lg text-green-600">€${parseFloat(rec.potential_profit).toFixed(2)}</div>
                    </div>
                </div>
                
                <!-- Value Betting Indicator -->
                ${rec.analysis.value_rating > 5 ? `
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                        <div class="flex items-center">
                            <div class="text-yellow-600 mr-2">⭐</div>
                            <div>
                                <div class="font-medium text-yellow-900">Value Bet detectado</div>
                                <div class="text-sm text-yellow-800">+${parseFloat(rec.analysis.value_rating).toFixed(2)}% de valor según nuestro análisis</div>
                            </div>
                        </div>
                    </div>
                ` : ''}
                
                <div class="flex space-x-3">
                    <button onclick="placeBet('${rec.match_id}', '${rec.bet_type}', ${rec.odds}, ${rec.recommended_amount})"
                            class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Apostar €${parseInt(rec.recommended_amount)}
                    </button>
                    <button onclick="customBet('${rec.match_id}', '${rec.bet_type}', ${rec.odds}, ${rec.confidence})"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50">
                        Personalizar
                    </button>
                </div>
            </div>
        </div>
    `;
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
                    ${recommendations.map(rec => renderRecommendation(rec, match.id, data.is_live)).join('')}
                </div>
            </div>
        </div>
    `;
}

function renderRecommendation(rec, matchId, isLive = false) {
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
        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow" data-match-id="${matchId}" data-bet-type="${rec.bet_type}">
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
                    <div class="font-bold text-lg text-purple-600">€${parseInt(rec.recommended_amount)}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Ganancia potencial</div>
                    <div class="font-bold text-lg text-green-600">€${parseFloat(rec.potential_profit).toFixed(2)}</div>
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
                    Apostar €${parseInt(rec.recommended_amount)}
                </button>
                <button onclick="customBet('${matchId}', '${rec.bet_type}', ${rec.estimated_odds}, ${rec.confidence}, ${isLive})"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50">
                    Personalizar ${isLive ? '🔴' : ''}
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
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Mostrar notificación de éxito
                showSuccessNotification('✅ Apuesta exitosa: €' + parseInt(amount) + ' apostados. Nuevo balance: €' + data.new_balance.toFixed(2));
                
                // Actualizar balance mostrado
                updateDisplayedBalance(data.new_balance);
                
                // Eliminar la recomendación de la lista
                removeRecommendationFromList(matchId, betType);
                
            } else {
                // Mostrar error específico del servidor
                showErrorNotification('❌ Error: ' + (data.message || 'No se pudo realizar la apuesta'));
            }
        })
        .catch(error => {
            console.error('Error al realizar apuesta:', error);
            showErrorNotification('❌ Error de conexión: ' + error.message);
        });
    }
}

function customBet(matchId, betType, originalOdds, confidence, isLive = false) {
    // Crear modal personalizado para la configuración
    const modalHtml = `
        <div id="customBetModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Personalizar Apuesta ${isLive ? '🔴 EN VIVO' : ''}</h3>
                    
                    <div class="mb-4">
                        <label for="customBetType" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Apuesta:</label>
                        <select id="customBetType" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="updateBetTypeSelection()">
                            ${getBetTypeOptions(betType, isLive)}
                        </select>
                        <div class="text-xs text-gray-500 mt-1">${isLive ? 'Tipos disponibles para partidos en vivo' : 'Selecciona el tipo de apuesta'}</div>
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
        'over_1_5': 'Más de 1.5 Goles',
        'under_1_5': 'Menos de 1.5 Goles',
        'over_0_5': 'Más de 0.5 Goles',
        'under_0_5': 'Menos de 0.5 Goles',
        'both_teams_score': 'Ambos Equipos Marcan',
        'over_0_5_first_half': 'Over 0.5 1T'
    };
    return types[betType] || betType;
}

function getBetTypeOptions(currentBetType, isLive = false) {
    // Todas las opciones disponibles
    const allOptions = [
        { value: 'home_win', label: 'Victoria Local', group: 'resultado' },
        { value: 'away_win', label: 'Victoria Visitante', group: 'resultado' },
        { value: 'draw', label: 'Empate', group: 'resultado' },
        { value: 'over_2_5', label: 'Más de 2.5 Goles', group: 'goles' },
        { value: 'under_2_5', label: 'Menos de 2.5 Goles', group: 'goles' },
        { value: 'over_1_5', label: 'Más de 1.5 Goles', group: 'goles' },
        { value: 'under_1_5', label: 'Menos de 1.5 Goles', group: 'goles' },
        { value: 'over_0_5', label: 'Más de 0.5 Goles', group: 'goles' },
        { value: 'under_0_5', label: 'Menos de 0.5 Goles', group: 'goles' },
        { value: 'both_teams_score', label: 'Ambos Equipos Marcan', group: 'especiales' },
        { value: 'over_0_5_first_half', label: 'Over 0.5 1T', group: 'especiales' }
    ];

    // Filtrar opciones según si es partido en vivo
    let availableOptions = allOptions;
    
    if (isLive) {
        // Para partidos en vivo, ofrecer más opciones de goles alternativos
        availableOptions = allOptions.filter(option => {
            // Incluir todas las opciones para máxima flexibilidad en vivo
            return true;
        });
    }

    // Generar HTML de opciones agrupadas
    let optionsHtml = '';
    const groups = {
        'resultado': 'Resultado del Partido',
        'goles': 'Total de Goles',
        'especiales': 'Apuestas Especiales'
    };

    Object.keys(groups).forEach(groupKey => {
        const groupOptions = availableOptions.filter(option => option.group === groupKey);
        if (groupOptions.length > 0) {
            optionsHtml += `<optgroup label="${groups[groupKey]}">`;
            groupOptions.forEach(option => {
                const selected = option.value === currentBetType ? 'selected' : '';
                optionsHtml += `<option value="${option.value}" ${selected}>${option.label}</option>`;
            });
            optionsHtml += `</optgroup>`;
        }
    });

    return optionsHtml;
}

function getRecommendationLevelColor(level) {
    const colors = {
        'EXCELENTE': 'green',
        'MUY BUENA': 'blue',
        'BUENA': 'indigo',
        'ACEPTABLE': 'gray'
    };
    return colors[level] || 'gray';
}

function getRiskLevelColor(level) {
    const colors = {
        'BAJO': 'green',
        'MEDIO': 'yellow',
        'ALTO': 'red'
    };
    return colors[level] || 'gray';
}


function updateDisplayedBalance(newBalance) {
    const balanceElements = document.querySelectorAll('.budget-balance');
    balanceElements.forEach(element => {
        element.textContent = '€' + parseFloat(newBalance).toFixed(2);
    });
    
    // Update the header balance if it exists
    const headerBalance = document.querySelector('[class*="text-2xl"][class*="font-bold"]');
    if (headerBalance && headerBalance.textContent.includes('€')) {
        headerBalance.textContent = '€' + parseFloat(newBalance).toLocaleString('es-ES', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
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

function updateBetTypeSelection() {
    // Cuando cambia el tipo de apuesta, actualizar odds sugeridas
    const selectedBetType = document.getElementById('customBetType').value;
    const betTypeDisplay = getBetTypeDisplay(selectedBetType);
    
    // Actualizar odds base según el tipo de apuesta
    const suggestedOdds = getSuggestedOddsForBetType(selectedBetType);
    document.getElementById('customOdds').value = suggestedOdds;
    
    // Actualizar cantidad y ganancia potencial
    updateCustomAmount();
    
    // Mostrar información del nuevo tipo de apuesta
    console.log('Tipo de apuesta cambiado a:', betTypeDisplay);
}

function getSuggestedOddsForBetType(betType) {
    // Odds típicas por tipo de apuesta para dar una referencia
    const typicalOdds = {
        'home_win': 2.20,
        'away_win': 3.50,
        'draw': 3.20,
        'over_2_5': 1.80,
        'under_2_5': 2.10,
        'over_1_5': 1.30,
        'under_1_5': 3.50,
        'over_0_5': 1.10,
        'under_0_5': 7.00,
        'both_teams_score': 1.90,
        'over_0_5_first_half': 1.25
    };
    
    return typicalOdds[betType] || 2.00;
}

function confirmCustomBet(matchId, originalBetType) {
    const selectedBetType = document.getElementById('customBetType').value;
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
    
    // Realizar apuesta con el tipo de apuesta seleccionado (no el original)
    placeBet(matchId, selectedBetType, odds, amount);
}

function closeCustomBetModal() {
    const modal = document.getElementById('customBetModal');
    if (modal) {
        modal.remove();
    }
}

function refreshRecommendations() {
    const refreshBtn = document.getElementById('refresh-btn');
    const originalText = refreshBtn.innerHTML;
    
    // Mostrar estado de carga
    refreshBtn.innerHTML = '⏳ Actualizando...';
    refreshBtn.disabled = true;
    
    // Mostrar loading en el contenedor
    document.getElementById('recommendations-container').innerHTML = `
        <div class="flex items-center justify-center py-12">
            <div class="text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
                <p class="text-gray-500">Actualizando recomendaciones...</p>
            </div>
        </div>
    `;
    
    // Recargar recomendaciones
    loadRecommendations()
        .finally(() => {
            // Restaurar botón
            refreshBtn.innerHTML = originalText;
            refreshBtn.disabled = false;
        });
}

function showError(message) {
    document.getElementById('recommendations-container').innerHTML = `
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
            <svg class="mx-auto h-12 w-12 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
            <h3 class="text-lg font-medium text-red-900 mb-2">Error</h3>
            <p class="text-red-700">${message}</p>
            <button onclick="refreshRecommendations()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-md text-sm hover:bg-red-700">
                🔄 Reintentar
            </button>
        </div>
    `;
}

function showSuccessNotification(message) {
    // Crear notificación de éxito temporal
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50 shadow-lg max-w-md';
    notification.innerHTML = `
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-green-600 hover:text-green-800">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function showErrorNotification(message) {
    // Crear notificación de error temporal
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50 shadow-lg max-w-md';
    notification.innerHTML = `
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-red-600 hover:text-red-800">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-eliminar después de 7 segundos (más tiempo para errores)
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 7000);
}

function removeRecommendationFromList(matchId, betType) {
    // Buscar y eliminar la recomendación específica de la lista
    const allRecommendations = document.querySelectorAll('[data-match-id="' + matchId + '"][data-bet-type="' + betType + '"]');
    
    allRecommendations.forEach(element => {
        // Agregar animación de salida
        element.style.transition = 'all 0.3s ease-out';
        element.style.opacity = '0';
        element.style.transform = 'translateX(100%)';
        
        // Eliminar después de la animación
        setTimeout(() => {
            element.remove();
            checkIfMatchHasNoRecommendations(matchId);
        }, 300);
    });
    
    // Buscar por estructura alternativa si no tiene data attributes
    const recommendationButtons = document.querySelectorAll(`button[onclick*="placeBet('${matchId}', '${betType}'"]`);
    recommendationButtons.forEach(button => {
        const recommendationCard = button.closest('.border');
        if (recommendationCard) {
            recommendationCard.style.transition = 'all 0.3s ease-out';
            recommendationCard.style.opacity = '0';
            recommendationCard.style.transform = 'translateX(100%)';
            
            setTimeout(() => {
                recommendationCard.remove();
                checkIfMatchHasNoRecommendations(matchId);
            }, 300);
        }
    });
}

function checkIfMatchHasNoRecommendations(matchId) {
    // Verificar si el partido ya no tiene recomendaciones
    const matchContainer = document.querySelector(`[data-match-id="${matchId}"]`)?.closest('.bg-white');
    
    if (matchContainer) {
        const remainingRecommendations = matchContainer.querySelectorAll('.border');
        
        if (remainingRecommendations.length === 0) {
            // Agregar mensaje de que no quedan recomendaciones
            const noRecsMessage = document.createElement('div');
            noRecsMessage.className = 'text-center py-4 text-gray-500';
            noRecsMessage.innerHTML = `
                <div class="text-sm">
                    ✅ Todas las recomendaciones para este partido han sido apostadas
                </div>
            `;
            
            const recommendationsContainer = matchContainer.querySelector('.space-y-4');
            if (recommendationsContainer) {
                recommendationsContainer.appendChild(noRecsMessage);
            }
        }
    }
}
</script>
@endsection