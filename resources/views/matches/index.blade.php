@extends('layouts.app')

@section('title', 'Partidos - Calcio')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Partidos</h1>
        <p class="mt-2 text-gray-600">Todos los partidos con predicciones y resultados</p>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white p-6 rounded-lg shadow">
        <form method="GET" action="{{ route('matches.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>Todos</option>
                    <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>Programados</option>
                    <option value="live" {{ request('status') == 'live' ? 'selected' : '' }}>En Vivo</option>
                    <option value="finished" {{ request('status') == 'finished' ? 'selected' : '' }}>Finalizados</option>
                    <option value="postponed" {{ request('status') == 'postponed' ? 'selected' : '' }}>Pospuestos</option>
                </select>
            </div>

            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700">Desde</label>
                <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700">Hasta</label>
                <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label for="team" class="block text-sm font-medium text-gray-700">Equipo</label>
                <select name="team" id="team" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Todos los equipos</option>
                    @foreach($teams as $team)
                        <option value="{{ $team->id }}" {{ request('team') == $team->id ? 'selected' : '' }}>
                            {{ $team->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-4 flex justify-end gap-2">
                <a href="{{ route('matches.index') }}" class="w-20 h-10 flex items-center justify-center border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                    Limpiar
                </a>
                <button type="submit" class="w-20 h-10 flex items-center justify-center border border-blue-600 rounded-lg text-sm font-medium text-blue-600 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- Matches List -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        @if($matches->count() > 0)
            <ul class="divide-y divide-gray-200">
                @foreach($matches as $match)
                <li data-match-id="{{ $match->id }}" data-status="{{ $match->status }}">
                    <a href="{{ route('matches.show', $match) }}" class="block hover:bg-gray-50">
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <div class="flex items-center space-x-2">
                                            @if($match->homeTeam->logo)
                                                <img class="h-8 w-8" src="{{ $match->homeTeam->logo }}" alt="{{ $match->homeTeam->name }}">
                                            @endif
                                            <span class="font-medium text-sm">{{ $match->homeTeam->short_name ?? $match->homeTeam->name }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center">
                                        @if($match->status === 'finished' || $match->status === 'live')
                                            <div class="text-lg font-bold text-gray-900">
                                                <span class="home-score">{{ $match->home_goals ?? 0 }}</span> - <span class="away-score">{{ $match->away_goals ?? 0 }}</span>
                                            </div>
                                        @else
                                            <div class="text-sm text-gray-500">vs</div>
                                        @endif
                                    </div>

                                    <div class="flex-shrink-0">
                                        <div class="flex items-center space-x-2">
                                            <span class="font-medium text-sm">{{ $match->awayTeam->short_name ?? $match->awayTeam->name }}</span>
                                            @if($match->awayTeam->logo)
                                                <img class="h-8 w-8" src="{{ $match->awayTeam->logo }}" alt="{{ $match->awayTeam->name }}">
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-4">
                                    <div class="text-right">
                                        <div class="text-sm text-gray-900">
                                            {{ $match->match_date->format('d/m/Y') }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $match->match_date->format('H:i') }}
                                        </div>
                                    </div>
                                    
                                    <div class="flex-shrink-0">
                                        <span class="match-status px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($match->status === 'scheduled') bg-yellow-100 text-yellow-800
                                            @elseif($match->status === 'live') bg-red-100 text-red-800 animate-pulse
                                            @elseif($match->status === 'finished') bg-green-100 text-green-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            @if($match->status === 'scheduled') Programado
                                            @elseif($match->status === 'live') 
                                                @if($match->minute) {{ $match->minute }}' @else En Vivo @endif
                                            @elseif($match->status === 'finished') Finalizado
                                            @else {{ ucfirst($match->status) }} @endif
                                        </span>
                                    </div>
                                </div>
                            </div>

                            @if($match->prediction)
                            <div class="mt-3 border-t pt-3">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-600">
                                        <span class="font-medium">Predicción:</span>
                                        {{ $match->homeTeam->short_name ?? $match->homeTeam->name }} {{ number_format($match->prediction->home_goals_prediction, 1) }} - 
                                        {{ number_format($match->prediction->away_goals_prediction, 1) }} {{ $match->awayTeam->short_name ?? $match->awayTeam->name }}
                                    </div>
                                    
                                    <div class="flex items-center space-x-4">
                                        <div class="text-xs text-gray-500">
                                            Confianza: {{ $match->prediction->confidence_level }}
                                        </div>
                                        
                                        @if($match->status === 'finished' && !is_null($match->prediction->is_correct))
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                @if($match->prediction->is_correct) bg-green-100 text-green-800
                                                @else bg-red-100 text-red-800 @endif">
                                                @if($match->prediction->is_correct) ✓ Correcto
                                                @else ✗ Incorrecto @endif
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-2 grid grid-cols-3 gap-4 text-xs text-gray-500">
                                    <div>
                                        <div class="font-medium mb-1">Resultado</div>
                                        <div>Casa: {{ number_format($match->prediction->home_win_probability * 100, 1) }}%</div>
                                        <div>Empate: {{ number_format($match->prediction->draw_probability * 100, 1) }}%</div>
                                        <div>Visitante: {{ number_format($match->prediction->away_win_probability * 100, 1) }}%</div>
                                    </div>
                                    <div>
                                        <div class="font-medium mb-1">Ambos Anotan</div>
                                        <div>{{ $match->prediction->both_teams_score_prediction }}</div>
                                        <div class="text-gray-400">({{ number_format($match->prediction->both_teams_score_probability * 100, 1) }}%)</div>
                                    </div>
                                    <div>
                                        <div class="font-medium mb-1">Total Goles</div>
                                        <div>{{ $match->prediction->over_25_prediction }}</div>
                                        <div class="text-gray-400">
                                            Over: {{ number_format($match->prediction->over_2_5_probability * 100, 1) }}%
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @else
                                <div class="mt-3 border-t pt-3">
                                    <div class="text-sm text-gray-500">
                                        Sin predicción disponible
                                    </div>
                                </div>
                            @endif
                        </div>
                    </a>
                </li>
                @endforeach
            </ul>
        @else
            <div class="text-center py-12">
                <div class="text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 00-2 2m-6 9l2 2 4-4" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay partidos</h3>
                    <p class="mt-1 text-sm text-gray-500">No se encontraron partidos con los filtros seleccionados.</p>
                </div>
            </div>
        @endif
    </div>

    <!-- Pagination -->
    @if($matches->hasPages())
        <div class="mt-6">
            {{ $matches->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection