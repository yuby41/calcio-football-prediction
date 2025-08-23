@extends('layouts.app')

@section('title', 'Inicio - Calcio')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">{{ session('success') }}</span>
    </div>
    @endif
    
    @if(session('error'))
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">{{ session('error') }}</span>
    </div>
    @endif
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Predicciones de Fútbol</h1>
                <p class="mt-2 text-gray-600">Predicciones generadas con Machine Learning para partidos de fútbol</p>
            </div>
            <div class="flex-shrink-0">
                <div class="flex items-center space-x-3">
                    <div id="auto-update-indicator" class="hidden">
                        <div class="flex items-center text-sm text-green-600">
                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-green-600 mr-2"></div>
                            Actualizando...
                        </div>
                    </div>
                    <form action="{{ route('home.manual-update') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Actualizar Ahora
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <span class="text-green-600 font-bold">%</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Precisión del Modelo</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $accuracy }}%</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <span class="text-red-600 font-bold">🔴</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">En Vivo</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $liveMatches->total() }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-blue-600 font-bold">⚽</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Hoy Programados</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $todayMatches->total() }}</dd>
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
                            <span class="text-yellow-600 font-bold">📅</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Próximos 30 Días</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $upcomingMatches->total() }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Matches -->
    @if($liveMatches->count() > 0)
    <div class="mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-900">🔴 Partidos en Vivo</h2>
            <span class="text-sm text-gray-500">Mostrando {{ $liveMatches->count() }} de {{ $liveMatches->total() }}</span>
        </div>
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <ul class="divide-y divide-gray-200">
                @foreach($liveMatches as $match)
                <li>
                    <a href="{{ route('matches.show', $match) }}" class="block hover:bg-gray-50">
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $match->homeTeam->name }} vs {{ $match->awayTeam->name }}
                                    </div>
                                    @if($match->league)
                                        <div class="ml-2 px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded">
                                            {{ $match->league }}
                                        </div>
                                    @endif
                                    <div class="ml-2 flex-shrink-0 flex">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 animate-pulse">
                                            ● EN VIVO
                                        </span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm text-gray-900">
                                        @if($match->home_goals !== null && $match->away_goals !== null)
                                            {{ $match->home_goals }} - {{ $match->away_goals }}
                                        @else
                                            {{ $match->match_date->format('H:i T') }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if($match->prediction)
                            <div class="mt-3 border-t pt-3">
                                <div class="grid grid-cols-3 gap-4 text-xs text-gray-500">
                                    <div>
                                        <div class="font-medium mb-1">Resultado</div>
                                        <div>Casa: {{ number_format($match->prediction->home_win_probability * 100, 1) }}%</div>
                                        <div>Empate: {{ number_format($match->prediction->draw_probability * 100, 1) }}%</div>
                                        <div>Visitante: {{ number_format($match->prediction->away_win_probability * 100, 1) }}%</div>
                                    </div>
                                    <div>
                                        <div class="font-medium mb-1">Ambos Anotan</div>
                                        <div>{{ $match->prediction->both_teams_score_probability > 0.5 ? 'Sí' : 'No' }}</div>
                                        <div class="text-gray-400">({{ number_format($match->prediction->both_teams_score_probability * 100, 1) }}%)</div>
                                    </div>
                                    <div>
                                        <div class="font-medium mb-1">Total Goles</div>
                                        <div>{{ $match->prediction->over_2_5_probability > 0.5 ? 'Over 2.5' : 'Under 2.5' }}</div>
                                        <div class="text-gray-400">
                                            Over: {{ number_format($match->prediction->over_2_5_probability * 100, 1) }}%
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
        
        <!-- Live Matches Pagination -->
        @if($liveMatches->hasPages())
        <div class="mt-4">
            {{ $liveMatches->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
    @endif

    <!-- Today's Scheduled Matches -->
    @if($todayMatches->count() > 0)
    <div class="mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-900">⚽ Partidos de Hoy Programados</h2>
            <span class="text-sm text-gray-500">Mostrando {{ $todayMatches->count() }} de {{ $todayMatches->total() }}</span>
        </div>
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <ul class="divide-y divide-gray-200">
                @foreach($todayMatches as $match)
                <li>
                    <a href="{{ route('matches.show', $match) }}" class="block hover:bg-gray-50">
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $match->homeTeam->name }} vs {{ $match->awayTeam->name }}
                                    </div>
                                    @if($match->league)
                                        <div class="ml-2 px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded">
                                            {{ $match->league }}
                                        </div>
                                    @endif
                                    <div class="ml-2 flex-shrink-0 flex">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($match->status === 'scheduled') bg-yellow-100 text-yellow-800
                                            @elseif($match->status === 'live') bg-red-100 text-red-800
                                            @else bg-green-100 text-green-800 @endif">
                                            @if($match->status === 'scheduled') Programado
                                            @elseif($match->status === 'live') En Vivo
                                            @else Finalizado @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $match->match_date->format('H:i') }}
                                </div>
                            </div>
                            @if($match->prediction)
                            <div class="mt-2">
                                <div class="text-sm text-gray-600">
                                    <div class="grid grid-cols-3 gap-2 text-xs">
                                        <div>Resultado: {{ ucfirst(str_replace('_', ' ', $match->prediction->predicted_outcome)) }}</div>
                                        <div>Gol: {{ $match->prediction->both_teams_score_probability > 0.5 ? 'Sí' : 'No' }} ({{ number_format($match->prediction->both_teams_score_probability * 100, 1) }}%)</div>
                                        <div>+2.5: {{ number_format($match->prediction->over_2_5_probability * 100, 1) }}%</div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
        
        <!-- Today's Matches Pagination -->
        @if($todayMatches->hasPages())
        <div class="mt-4">
            {{ $todayMatches->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
    @endif

    <!-- Upcoming Matches -->
    @if($upcomingMatches->count() > 0)
    <div class="mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-900">📅 Próximos Partidos</h2>
            <span class="text-sm text-gray-500">Mostrando {{ $upcomingMatches->count() }} de {{ $upcomingMatches->total() }}</span>
        </div>
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <ul class="divide-y divide-gray-200">
                @foreach($upcomingMatches as $match)
                <li>
                    <a href="{{ route('matches.show', $match) }}" class="block hover:bg-gray-50">
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $match->homeTeam->name }} vs {{ $match->awayTeam->name }}
                                    </div>
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $match->match_date->format('d/m/Y H:i') }}
                                </div>
                            </div>
                            @if($match->prediction)
                            <div class="mt-2">
                                <div class="text-sm text-gray-600">
                                    <div class="grid grid-cols-3 gap-2 text-xs">
                                        <div>Resultado: {{ ucfirst(str_replace('_', ' ', $match->prediction->predicted_outcome)) }}</div>
                                        <div>Gol: {{ $match->prediction->both_teams_score_probability > 0.5 ? 'Sí' : 'No' }} ({{ number_format($match->prediction->both_teams_score_probability * 100, 1) }}%)</div>
                                        <div>+2.5: {{ number_format($match->prediction->over_2_5_probability * 100, 1) }}%</div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
        
        <!-- Upcoming Matches Pagination -->
        @if($upcomingMatches->hasPages())
        <div class="mt-4">
            {{ $upcomingMatches->appends(request()->query())->links() }}
        </div>
        @endif
        
        <div class="mt-4 text-center">
            <a href="{{ route('matches.index') }}" class="text-blue-600 hover:text-blue-500">
                Ver todos los partidos →
            </a>
        </div>
    </div>
    @endif


</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let updateInterval;
    let isUpdating = false;

    // Auto-update function
    function updateLiveData() {
        if (isUpdating) return;
        isUpdating = true;

        // Show update indicator
        const indicator = document.getElementById('auto-update-indicator');
        if (indicator) {
            indicator.classList.remove('hidden');
        }

        fetch('/api/live-data')
            .then(response => response.json())
            .then(data => {
                // Add accuracy to counters object
                const counters = {
                    ...data.counters,
                    accuracy: data.accuracy
                };
                
                // Update counters
                updateCounters(counters);
                
                // Update matches sections
                updateLiveMatches(data.liveMatches);
                updateTodayMatches(data.todayMatches);
                updateUpcomingMatches(data.upcomingMatches);
                
                console.log('Live data updated at', new Date().toLocaleTimeString());
            })
            .catch(error => {
                console.error('Error updating live data:', error);
            })
            .finally(() => {
                // Hide update indicator
                if (indicator) {
                    setTimeout(() => {
                        indicator.classList.add('hidden');
                    }, 1000);
                }
                isUpdating = false;
            });
    }

    function updateCounters(counters) {
        // Get all counter elements more specifically
        const counterElements = document.querySelectorAll('.text-lg.font-medium.text-gray-900');
        
        // Update accuracy (first element)
        if (counterElements[0]) {
            counterElements[0].textContent = counters.accuracy + '%';
        }
        
        // Update live matches counter (second element)
        if (counterElements[1] && counterElements[1].textContent !== counters.live.toString()) {
            counterElements[1].textContent = counters.live;
            // Add visual feedback for updates
            counterElements[1].classList.add('animate-pulse');
            setTimeout(() => counterElements[1].classList.remove('animate-pulse'), 1000);
        }
        
        // Update today matches counter (third element)
        if (counterElements[2] && counterElements[2].textContent !== counters.today.toString()) {
            counterElements[2].textContent = counters.today;
            counterElements[2].classList.add('animate-pulse');
            setTimeout(() => counterElements[2].classList.remove('animate-pulse'), 1000);
        }
        
        // Update upcoming matches counter (fourth element)
        if (counterElements[3] && counterElements[3].textContent !== counters.upcoming.toString()) {
            counterElements[3].textContent = counters.upcoming;
            counterElements[3].classList.add('animate-pulse');
            setTimeout(() => counterElements[3].classList.remove('animate-pulse'), 1000);
        }
    }

    function updateAccuracy(accuracy) {
        // This is now handled in updateCounters
    }

    function updateLiveMatches(matches) {
        const liveSection = document.querySelector('h2:contains("🔴 En Vivo")');
        // Implementation for updating live matches section
        // This would require more complex DOM manipulation
    }

    function updateTodayMatches(matches) {
        // Implementation for updating today's matches
        // This would require more complex DOM manipulation
    }

    function updateUpcomingMatches(matches) {
        // Implementation for updating upcoming matches
        // This would require more complex DOM manipulation
    }

    // Start auto-update when page loads
    function startAutoUpdate() {
        // Update immediately
        updateLiveData();
        
        // Then update every 30 seconds
        updateInterval = setInterval(updateLiveData, 30000);
    }

    // Stop auto-update
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }

    // Start auto-update
    startAutoUpdate();

    // Stop updating when leaving the page
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // Stop updating when navigating away
    window.addEventListener('beforeunload', stopAutoUpdate);
});
</script>
@endsection