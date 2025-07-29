@extends('layouts.app')

@section('title', $budget->name . ' - Budget')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4 mb-4">
            <a href="{{ route('budget.index') }}" 
               class="inline-flex items-center text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Volver a Budget
            </a>
        </div>
        
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $budget->name }}</h1>
                <p class="mt-2 text-gray-600">
                    Estrategia: <span class="font-medium">{{ ucfirst($budget->strategy) }}</span> • 
                    Creada: {{ $budget->created_at->format('d/m/Y') }}
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('budget.recommendations', $budget) }}" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    Recomendaciones IA
                </a>
                <form action="{{ route('budget.resolve-bets', $budget) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" 
                            class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Resolver Apuestas
                    </button>
                </form>
                <form action="{{ route('budget.destroy', $budget) }}" method="POST" class="inline"
                      onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta configuración de budget? Esta acción no se puede deshacer y eliminará todos los datos relacionados.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" 
                            class="inline-flex items-center px-4 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Budget Actual</dt>
                            <dd class="text-lg font-medium text-gray-900">€{{ number_format($budget->current_budget, 2) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-{{ $budget->getNetProfitAttribute() >= 0 ? 'green' : 'red' }}-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-{{ $budget->getNetProfitAttribute() >= 0 ? 'green' : 'red' }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                @if($budget->getNetProfitAttribute() >= 0)
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                                @endif
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Beneficio Neto</dt>
                            <dd class="text-lg font-medium text-{{ $budget->getNetProfitAttribute() >= 0 ? 'green' : 'red' }}-600">
                                {{ $budget->getNetProfitAttribute() >= 0 ? '+' : '' }}€{{ number_format($budget->getNetProfitAttribute(), 2) }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <span class="text-purple-600 font-bold text-sm">%</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Win Rate</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $budget->getWinRateAttribute() }}%</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">ROI</dt>
                            <dd class="text-lg font-medium text-{{ $budget->getROIAttribute() >= 0 ? 'green' : 'red' }}-600">
                                {{ $budget->getROIAttribute() >= 0 ? '+' : '' }}{{ $budget->getROIAttribute() }}%
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Strategy Configuration -->
    <div class="bg-white shadow rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Configuración de Estrategia</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Estrategia</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ ucfirst($budget->strategy) }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Máximo por Apuesta</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $budget->max_bet_percentage }}% del budget</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Confianza Mínima</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $budget->min_confidence }}%</dd>
                </div>
                @if($budget->target_profit)
                <div>
                    <dt class="text-sm font-medium text-gray-500">Objetivo de Ganancia</dt>
                    <dd class="mt-1 text-sm text-gray-900">€{{ number_format($budget->target_profit, 2) }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-sm font-medium text-gray-500">Apuestas Pendientes</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $budget->getPendingBetsAttribute() }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Estado</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($budget->is_active) bg-green-100 text-green-800 @else bg-gray-100 text-gray-800 @endif">
                            @if($budget->is_active) Activa @else Inactiva @endif
                        </span>
                    </dd>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Bets -->
    @if($budget->bets->count() > 0)
    <div class="bg-white shadow overflow-hidden sm:rounded-md mb-8">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Apuestas Recientes</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Últimas apuestas realizadas con esta configuración</p>
        </div>
        <ul class="divide-y divide-gray-200">
            @foreach($budget->bets->take(10) as $bet)
            <li class="px-4 py-4 sm:px-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                bg-{{ $bet->getStatusColorAttribute() }}-100 text-{{ $bet->getStatusColorAttribute() }}-800">
                                {{ $bet->getStatusDisplayAttribute() }}
                            </span>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">
                                @if($bet->match)
                                    {{ $bet->match->homeTeam->name }} vs {{ $bet->match->awayTeam->name }}
                                @else
                                    Partido eliminado
                                @endif
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $bet->getBetTypeDisplayAttribute() }} • €{{ number_format($bet->amount, 2) }} @ {{ $bet->odds }}
                                • Confianza: {{ $bet->confidence }}%
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-900">
                                {{ $bet->placed_at->format('d/m/Y H:i') }}
                            </div>
                            <div class="text-sm">
                                @if($bet->actual_profit !== null)
                                    <span class="text-{{ $bet->actual_profit >= 0 ? 'green' : 'red' }}-600">
                                        {{ $bet->actual_profit >= 0 ? '+' : '' }}€{{ number_format($bet->actual_profit, 2) }}
                                    </span>
                                @else
                                    <span class="text-blue-600">€{{ number_format($bet->potential_profit, 2) }} pot.</span>
                                @endif
                            </div>
                        </div>
                        
                        @if($bet->status === 'pending')
                        <div class="flex space-x-2">
                            <button onclick="deleteBet({{ $bet->id }})"
                                    class="inline-flex items-center p-1.5 border border-gray-300 rounded text-gray-400 hover:text-red-600 hover:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
            </li>
            @endforeach
        </ul>
    </div>
    @else
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay apuestas realizadas</h3>
            <p class="mt-1 text-sm text-gray-500">
                Cuando realices apuestas con esta configuración aparecerán aquí.
            </p>
        </div>
    </div>
    @endif

    <!-- Budget Evolution Chart -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Evolución del Budget</h3>
            <div id="chart-container" class="h-64 relative">
                <canvas id="budgetChart" class="w-full h-full"></canvas>
                <div id="chart-loading" class="absolute inset-0 flex items-center justify-center bg-gray-50 rounded">
                    <div class="text-center text-gray-500">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-2"></div>
                        <p class="text-sm">Cargando gráfico...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Bet management functions
function deleteBet(betId) {
    if (confirm('¿Estás seguro de que quieres eliminar esta apuesta?\nEl budget se restaurará al estado anterior a la apuesta.')) {
        fetch(`{{ route('budget.delete-bet', [$budget, '__BET_ID__']) }}`.replace('__BET_ID__', betId), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Delete response:', data);
            if (data.success) {
                alert('Apuesta eliminada exitosamente\nBudget restaurado: €' + data.new_balance);
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'No se pudo eliminar la apuesta'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar la apuesta: ' + error.message);
        });
    }
}
</script>
<script>
function hideLoader() {
    const loader = document.getElementById('chart-loading');
    if (loader) {
        loader.style.display = 'none';
    }
}

function showError(message) {
    hideLoader();
    const container = document.getElementById('chart-container');
    container.innerHTML = `
        <div class="h-64 bg-red-50 rounded-lg flex items-center justify-center">
            <div class="text-center text-red-600">
                <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <p class="text-sm font-medium">Error cargando el gráfico</p>
                <p class="text-xs mt-1">${message}</p>
            </div>
        </div>
    `;
}

function createChart(data) {
    console.log('Creating chart with data:', data);
    
    if (!data || !data.dates || !data.balances) {
        throw new Error('Datos inválidos recibidos');
    }
    
    if (data.dates.length === 0 || data.balances.length === 0) {
        throw new Error('No hay datos disponibles para el gráfico');
    }
    
    if (data.dates.length !== data.balances.length) {
        throw new Error('Los arrays de fechas y balances no coinciden');
    }
    
    hideLoader();
    
    const canvas = document.getElementById('budgetChart');
    const ctx = canvas.getContext('2d');
    
    try {
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.dates,
                datasets: [{
                    label: 'Balance €',
                    data: data.balances,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return '€' + Math.round(value);
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Balance: €' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
        
        console.log('Chart created successfully');
        
    } catch (chartError) {
        console.error('Error creating chart:', chartError);
        throw new Error('Error al crear el gráfico: ' + chartError.message);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        console.log('DOM loaded, initializing chart...');
        
        // Check if Chart.js is loaded
        if (typeof Chart === 'undefined') {
            console.error('Chart.js not loaded');
            showError('Chart.js no se pudo cargar');
            return;
        }
        
        console.log('Chart.js version:', Chart.version);
        
        const canvas = document.getElementById('budgetChart');
        if (!canvas) {
            console.error('Canvas element not found');
            showError('Elemento canvas no encontrado');
            return;
        }
        
        // Try with inline data first for debugging
        const inlineData = {!! json_encode($chartData ?? ['dates' => [], 'balances' => []]) !!};
        
        console.log('Inline data:', inlineData);
        
        if (inlineData && inlineData.dates && inlineData.balances && inlineData.dates.length > 0) {
            console.log('Using inline data');
            createChart(inlineData);
            return;
        }
        
        const chartUrl = '{{ route("budget.chart-data", $budget) }}';
        console.log('Fetching data from:', chartUrl);
        
        fetch(chartUrl)
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Data received from API:', data);
                createChart(data);
            })
            .catch(error => {
                console.error('Chart error:', error);
                showError(error.message);
            });
            
    } catch (error) {
        console.error('General error:', error);
        showError('Error general: ' + error.message);
    }
});
</script>
</div>

@if(session('success'))
<div id="success-notification" class="fixed inset-0 flex items-end justify-center px-4 py-6 pointer-events-none sm:p-6 sm:items-start sm:justify-end z-50">
    <div class="max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto">
        <div class="rounded-lg shadow-xs overflow-hidden">
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3 w-0 flex-1">
                        <p class="text-sm leading-5 font-medium text-gray-900">
                            {{ session('success') }}
                        </p>
                    </div>
                    <div class="ml-4 flex-shrink-0 flex">
                        <button class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" onclick="closeNotification()">
                            <span class="sr-only">Cerrar</span>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function closeNotification() {
    const notification = document.getElementById('success-notification');
    if (notification) {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }
}

// Auto-close after 5 seconds
setTimeout(() => {
    closeNotification();
}, 5000);
</script>
@endif
@endsection