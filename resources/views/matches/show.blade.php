@extends('layouts.app')

@section('title', $match->homeTeam->name . ' vs ' . $match->awayTeam->name . ' - Calcio')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Back Link -->
    <div class="mb-4">
        <a href="{{ route('matches.index') }}" class="text-blue-600 hover:text-blue-500 text-sm font-medium">
            ← Volver a partidos
        </a>
    </div>

    <!-- Match Header -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <div class="text-center">
            <div class="flex items-center justify-center space-x-8 mb-4">
                <!-- Home Team -->
                <div class="text-center">
                    @if($match->homeTeam->logo)
                        <img class="h-16 w-16 mx-auto mb-2" src="{{ $match->homeTeam->logo }}" alt="{{ $match->homeTeam->name }}">
                    @endif
                    <h2 class="text-xl font-bold text-gray-900">{{ $match->homeTeam->name }}</h2>
                </div>

                <!-- Score/VS -->
                <div class="text-center">
                    @if($match->status === 'finished')
                        <div class="text-4xl font-bold text-gray-900 mb-2">
                            {{ $match->home_goals }} - {{ $match->away_goals }}
                        </div>
                    @else
                        <div class="text-2xl text-gray-500 mb-2">VS</div>
                    @endif
                    
                    <div class="text-sm text-gray-600">
                        {{ $match->match_date->format('d/m/Y H:i T') }}
                    </div>
                    
                    <span class="mt-2 px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full
                        @if($match->status === 'scheduled') bg-yellow-100 text-yellow-800
                        @elseif($match->status === 'live') bg-red-100 text-red-800
                        @elseif($match->status === 'finished') bg-green-100 text-green-800
                        @else bg-gray-100 text-gray-800 @endif">
                        @if($match->status === 'scheduled') Programado
                        @elseif($match->status === 'live') En Vivo
                        @elseif($match->status === 'finished') Finalizado
                        @else {{ ucfirst($match->status) }} @endif
                    </span>
                </div>

                <!-- Away Team -->
                <div class="text-center">
                    @if($match->awayTeam->logo)
                        <img class="h-16 w-16 mx-auto mb-2" src="{{ $match->awayTeam->logo }}" alt="{{ $match->awayTeam->name }}">
                    @endif
                    <h2 class="text-xl font-bold text-gray-900">{{ $match->awayTeam->name }}</h2>
                </div>
            </div>

            @if($match->league)
                <div class="text-sm text-gray-600">
                    {{ $match->league }} @if($match->season) - Temporada {{ $match->season }} @endif
                    @if($match->round) - Jornada {{ $match->round }} @endif
                </div>
            @endif
        </div>
    </div>

    <!-- Prediction -->
    @if($match->prediction)
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Predicción de Machine Learning</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Goals Prediction -->
            <div>
                <h4 class="font-medium text-gray-900 mb-3">Predicción de Goles</h4>
                <div class="flex items-center justify-between bg-gray-50 p-4 rounded-lg">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">
                            {{ number_format($match->prediction->home_goals_prediction, 1) }}
                        </div>
                        <div class="text-sm text-gray-600">{{ $match->homeTeam->short_name ?? $match->homeTeam->name }}</div>
                    </div>
                    <div class="text-gray-400">-</div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-600">
                            {{ number_format($match->prediction->away_goals_prediction, 1) }}
                        </div>
                        <div class="text-sm text-gray-600">{{ $match->awayTeam->short_name ?? $match->awayTeam->name }}</div>
                    </div>
                </div>
                <div class="mt-2 text-sm text-gray-600">
                    Total de goles predichos: {{ number_format($match->prediction->total_goals_prediction, 1) }}
                </div>
            </div>

            <!-- Win Probabilities -->
            <div>
                <h4 class="font-medium text-gray-900 mb-3">Probabilidades de Resultado</h4>
                @php
                    $normalizedProbs = $match->prediction->normalized_win_probabilities;
                @endphp
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Victoria Local</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $normalizedProbs['home'] }}%"></div>
                            </div>
                            <span class="text-sm font-medium">{{ $normalizedProbs['home'] }}%</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Empate</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                <div class="bg-yellow-500 h-2 rounded-full" style="width: {{ $normalizedProbs['draw'] }}%"></div>
                            </div>
                            <span class="text-sm font-medium">{{ $normalizedProbs['draw'] }}%</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Victoria Visitante</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                <div class="bg-red-600 h-2 rounded-full" style="width: {{ $normalizedProbs['away'] }}%"></div>
                            </div>
                            <span class="text-sm font-medium">{{ $normalizedProbs['away'] }}%</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Resultado más probable:</span>
                        <span class="font-medium text-gray-900">
                            @if($match->prediction->predicted_outcome === 'home_win') Victoria Local
                            @elseif($match->prediction->predicted_outcome === 'away_win') Victoria Visitante
                            @else Empate @endif
                        </span>
                    </div>
                    <div class="flex justify-between items-center mt-1">
                        <span class="text-sm text-gray-600">Nivel de confianza:</span>
                        <span class="font-medium text-gray-900">{{ $match->prediction->confidence_level }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if($match->status === 'finished' && !is_null($match->prediction->is_correct))
        <div class="mt-6 p-4 rounded-lg {{ $match->prediction->is_correct ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @if($match->prediction->is_correct)
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    @else
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    @endif
                </div>
                <div class="ml-3">
                    <h4 class="text-sm font-medium {{ $match->prediction->is_correct ? 'text-green-800' : 'text-red-800' }}">
                        Predicción {{ $match->prediction->is_correct ? 'Correcta' : 'Incorrecta' }}
                    </h4>
                    <p class="text-sm {{ $match->prediction->is_correct ? 'text-green-700' : 'text-red-700' }}">
                        @if($match->prediction->is_correct)
                            El modelo predijo correctamente el resultado del partido.
                        @else
                            El modelo no predijo correctamente el resultado del partido.
                        @endif
                    </p>
                </div>
            </div>
        </div>
        @endif

        <div class="mt-4 text-xs text-gray-500">
            Predicción generada el {{ $match->prediction->predicted_at->format('d/m/Y H:i') }} 
            con modelo versión {{ $match->prediction->model_version }}
        </div>
    </div>
    @endif

    <!-- Head to Head & Recent Form -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Head to Head -->
        @if($headToHead->count() > 0)
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Enfrentamientos Directos</h3>
            <div class="space-y-3">
                @foreach($headToHead as $h2h)
                <div class="flex items-center justify-between text-sm">
                    <div class="flex-1">
                        <div class="font-medium">
                            {{ $h2h->homeTeam->short_name ?? $h2h->homeTeam->name }} 
                            {{ $h2h->home_goals }}-{{ $h2h->away_goals }} 
                            {{ $h2h->awayTeam->short_name ?? $h2h->awayTeam->name }}
                        </div>
                        <div class="text-gray-500">{{ $h2h->match_date->format('d/m/Y') }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Home Team Recent Form -->
        @if($homeTeamMatches->count() > 0)
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">{{ $match->homeTeam->name }} - Últimos Partidos</h3>
            <div class="space-y-3">
                @foreach($homeTeamMatches as $homeMatch)
                <div class="flex items-center justify-between text-sm">
                    <div class="flex-1">
                        <div class="font-medium">
                            {{ $homeMatch->homeTeam->short_name ?? $homeMatch->homeTeam->name }} 
                            {{ $homeMatch->home_goals }}-{{ $homeMatch->away_goals }} 
                            {{ $homeMatch->awayTeam->short_name ?? $homeMatch->awayTeam->name }}
                        </div>
                        <div class="text-gray-500">{{ $homeMatch->match_date->format('d/m/Y') }}</div>
                    </div>
                    <div class="ml-2">
                        @php
                            $isHome = $homeMatch->home_team_id === $match->home_team_id;
                            if ($isHome) {
                                $result = $homeMatch->home_goals > $homeMatch->away_goals ? 'W' : 
                                         ($homeMatch->home_goals < $homeMatch->away_goals ? 'L' : 'D');
                            } else {
                                $result = $homeMatch->away_goals > $homeMatch->home_goals ? 'W' : 
                                         ($homeMatch->away_goals < $homeMatch->home_goals ? 'L' : 'D');
                            }
                        @endphp
                        <span class="px-2 py-1 text-xs font-semibold rounded
                            @if($result === 'W') bg-green-100 text-green-800
                            @elseif($result === 'L') bg-red-100 text-red-800
                            @else bg-yellow-100 text-yellow-800 @endif">
                            {{ $result }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Away Team Recent Form -->
        @if($awayTeamMatches->count() > 0)
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">{{ $match->awayTeam->name }} - Últimos Partidos</h3>
            <div class="space-y-3">
                @foreach($awayTeamMatches as $awayMatch)
                <div class="flex items-center justify-between text-sm">
                    <div class="flex-1">
                        <div class="font-medium">
                            {{ $awayMatch->homeTeam->short_name ?? $awayMatch->homeTeam->name }} 
                            {{ $awayMatch->home_goals }}-{{ $awayMatch->away_goals }} 
                            {{ $awayMatch->awayTeam->short_name ?? $awayMatch->awayTeam->name }}
                        </div>
                        <div class="text-gray-500">{{ $awayMatch->match_date->format('d/m/Y') }}</div>
                    </div>
                    <div class="ml-2">
                        @php
                            $isHome = $awayMatch->home_team_id === $match->away_team_id;
                            if ($isHome) {
                                $result = $awayMatch->home_goals > $awayMatch->away_goals ? 'W' : 
                                         ($awayMatch->home_goals < $awayMatch->away_goals ? 'L' : 'D');
                            } else {
                                $result = $awayMatch->away_goals > $awayMatch->home_goals ? 'W' : 
                                         ($awayMatch->away_goals < $awayMatch->home_goals ? 'L' : 'D');
                            }
                        @endphp
                        <span class="px-2 py-1 text-xs font-semibold rounded
                            @if($result === 'W') bg-green-100 text-green-800
                            @elseif($result === 'L') bg-red-100 text-red-800
                            @else bg-yellow-100 text-yellow-800 @endif">
                            {{ $result }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection