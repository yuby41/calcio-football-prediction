@extends('layouts.app')

@section('title', $team->name . ' - Calcio')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Back Link -->
    <div class="mb-4">
        <a href="{{ route('teams.index') }}" class="text-blue-600 hover:text-blue-500 text-sm font-medium">
            ← Volver a equipos
        </a>
    </div>

    <!-- Team Header -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <div class="flex items-center space-x-6">
            @if($team->logo)
                <img class="h-20 w-20 rounded-full" src="{{ $team->logo }}" alt="{{ $team->name }}">
            @endif
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $team->name }}</h1>
                @if($team->short_name && $team->short_name !== $team->name)
                    <p class="text-lg text-gray-600">{{ $team->short_name }}</p>
                @endif
                <div class="flex space-x-4 text-sm text-gray-500 mt-2">
                    @if($team->country)
                        <span>{{ $team->country }}</span>
                    @endif
                    @if($team->league)
                        <span>{{ $team->league }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Season Statistics -->
    @if($statistics)
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Estadísticas Temporada {{ $season }}</h2>
        
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-900">{{ $statistics->matches_played }}</div>
                <div class="text-sm text-gray-600">Partidos</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600">{{ $statistics->wins }}</div>
                <div class="text-sm text-gray-600">Victorias</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-yellow-600">{{ $statistics->draws }}</div>
                <div class="text-sm text-gray-600">Empates</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-red-600">{{ $statistics->losses }}</div>
                <div class="text-sm text-gray-600">Derrotas</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600">{{ $statistics->goals_for }}</div>
                <div class="text-sm text-gray-600">Goles a Favor</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-purple-600">{{ $statistics->goals_against }}</div> 
                <div class="text-sm text-gray-600">Goles en Contra</div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- General Stats -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-medium text-gray-900 mb-3">Estadísticas Generales</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Puntos:</span>
                        <span class="font-medium">{{ $statistics->points }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Diferencia de goles:</span>
                        <span class="font-medium {{ $statistics->goals_difference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $statistics->goals_difference >= 0 ? '+' : '' }}{{ $statistics->goals_difference }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">% Victorias:</span>
                        <span class="font-medium">{{ $statistics->win_percentage }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Promedio goles/partido:</span>
                        <span class="font-medium">{{ number_format($statistics->avg_goals_for, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Promedio goles recibidos:</span>
                        <span class="font-medium">{{ number_format($statistics->avg_goals_against, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Home Stats -->
            @if($homeStats['played'] > 0)
            <div class="bg-blue-50 p-4 rounded-lg">
                <h3 class="font-medium text-gray-900 mb-3">Como Local</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Partidos:</span>
                        <span class="font-medium">{{ $homeStats['played'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Victorias:</span>
                        <span class="font-medium text-green-600">{{ $homeStats['wins'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Empates:</span>
                        <span class="font-medium text-yellow-600">{{ $homeStats['draws'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Derrotas:</span>
                        <span class="font-medium text-red-600">{{ $homeStats['losses'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">% Victorias:</span>
                        <span class="font-medium">{{ $homeStats['win_percentage'] }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Promedio goles:</span>
                        <span class="font-medium">{{ $homeStats['avg_goals_for'] }}</span>
                    </div>
                </div>
            </div>
            @endif

            <!-- Away Stats -->
            @if($awayStats['played'] > 0)
            <div class="bg-red-50 p-4 rounded-lg">
                <h3 class="font-medium text-gray-900 mb-3">Como Visitante</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Partidos:</span>
                        <span class="font-medium">{{ $awayStats['played'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Victorias:</span>
                        <span class="font-medium text-green-600">{{ $awayStats['wins'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Empates:</span>
                        <span class="font-medium text-yellow-600">{{ $awayStats['draws'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Derrotas:</span>
                        <span class="font-medium text-red-600">{{ $awayStats['losses'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">% Victorias:</span>
                        <span class="font-medium">{{ $awayStats['win_percentage'] }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Promedio goles:</span>
                        <span class="font-medium">{{ $awayStats['avg_goals_for'] }}</span>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Form -->
        @if($statistics->form && count($statistics->form) > 0)
        <div class="mt-6">
            <h3 class="font-medium text-gray-900 mb-3">Forma Reciente</h3>
            <div class="flex space-x-2">
                @foreach(array_slice($statistics->form, 0, 10) as $result)
                    <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-semibold rounded-full
                        @if($result === 'win') bg-green-100 text-green-800
                        @elseif($result === 'loss') bg-red-100 text-red-800
                        @else bg-yellow-100 text-yellow-800 @endif">
                        @if($result === 'win') W
                        @elseif($result === 'loss') L
                        @else D @endif
                    </span>
                @endforeach
            </div>
            <div class="text-xs text-gray-500 mt-2">Últimos {{ count($statistics->form) }} partidos (más reciente a la izquierda)</div>
        </div>
        @endif
    </div>
    @endif

    <!-- Upcoming and Recent Matches -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Upcoming Matches -->
        @if($upcomingMatches->count() > 0)
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Próximos Partidos</h2>
            <div class="space-y-4">
                @foreach($upcomingMatches as $match)
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm text-gray-600">{{ $match->match_date->format('d/m/Y H:i') }}</div>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                            Programado
                        </span>
                    </div>
                    <div class="flex items-center justify-center space-x-4">
                        <div class="text-center">
                            <div class="font-medium">{{ $match->homeTeam->short_name ?? $match->homeTeam->name }}</div>
                            @if($match->home_team_id !== $team->id)
                                <div class="text-xs text-gray-500">Local</div>
                            @endif
                        </div>
                        <div class="text-gray-400">vs</div>
                        <div class="text-center">
                            <div class="font-medium">{{ $match->awayTeam->short_name ?? $match->awayTeam->name }}</div>
                            @if($match->away_team_id !== $team->id)
                                <div class="text-xs text-gray-500">Visitante</div>
                            @endif
                        </div>
                    </div>
                    @if($match->prediction)
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <div class="text-xs text-gray-600 text-center">
                            Predicción: {{ number_format($match->prediction->home_goals_prediction, 1) }} - {{ number_format($match->prediction->away_goals_prediction, 1) }}
                            ({{ $match->prediction->confidence_level }})
                        </div>
                    </div>
                    @endif
                    <div class="mt-2 text-center">
                        <a href="{{ route('matches.show', $match) }}" class="text-blue-600 hover:text-blue-500 text-sm">
                            Ver detalles →
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Recent Matches -->
        @if($recentMatches->count() > 0)
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Partidos Recientes</h2>
            <div class="space-y-4">
                @foreach($recentMatches as $match)
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm text-gray-600">{{ $match->match_date->format('d/m/Y') }}</div>
                        @php
                            $isHome = $match->home_team_id === $team->id;
                            if ($isHome) {
                                $result = $match->home_goals > $match->away_goals ? 'W' : 
                                         ($match->home_goals < $match->away_goals ? 'L' : 'D');
                            } else {
                                $result = $match->away_goals > $match->home_goals ? 'W' : 
                                         ($match->away_goals < $match->home_goals ? 'L' : 'D');
                            }
                        @endphp
                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                            @if($result === 'W') bg-green-100 text-green-800
                            @elseif($result === 'L') bg-red-100 text-red-800
                            @else bg-yellow-100 text-yellow-800 @endif">
                            @if($result === 'W') Victoria
                            @elseif($result === 'L') Derrota
                            @else Empate @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-center space-x-4">
                        <div class="text-center">
                            <div class="font-medium">{{ $match->homeTeam->short_name ?? $match->homeTeam->name }}</div>
                            @if($match->home_team_id !== $team->id)
                                <div class="text-xs text-gray-500">Local</div>
                            @endif
                        </div>
                        <div class="text-lg font-bold">{{ $match->home_goals }} - {{ $match->away_goals }}</div>
                        <div class="text-center">
                            <div class="font-medium">{{ $match->awayTeam->short_name ?? $match->awayTeam->name }}</div>
                            @if($match->away_team_id !== $team->id)
                                <div class="text-xs text-gray-500">Visitante</div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-2 text-center">
                        <a href="{{ route('matches.show', $match) }}" class="text-blue-600 hover:text-blue-500 text-sm">
                            Ver detalles →
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection