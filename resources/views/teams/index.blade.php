@extends('layouts.app')

@section('title', 'Equipos - Calcio')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Equipos</h1>
        <p class="mt-2 text-gray-600">Estadísticas y clasificación de todos los equipos</p>
    </div>

    <!-- Teams Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Pos
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Equipo
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            PJ
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            G
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            E
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            P
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            GF
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            GC
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            DG
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            PTS
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Forma
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($teams as $index => $team)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $index + 1 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                @if($team->logo)
                                    <img class="h-8 w-8 rounded-full mr-3" src="{{ $team->logo }}" alt="{{ $team->name }}">
                                @endif
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <a href="{{ route('teams.show', $team) }}" class="hover:text-blue-600">
                                            {{ $team->name }}
                                        </a>
                                    </div>
                                    @if($team->country)
                                        <div class="text-sm text-gray-500">{{ $team->country }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        
                        @php $stats = $team->statistics->first() @endphp
                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                            {{ $stats->matches_played ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                            {{ $stats->wins ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                            {{ $stats->draws ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                            {{ $stats->losses ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                            {{ $stats->goals_for ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                            {{ $stats->goals_against ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center
                            @if(($stats->goals_difference ?? 0) > 0) text-green-600
                            @elseif(($stats->goals_difference ?? 0) < 0) text-red-600
                            @else text-gray-900 @endif">
                            {{ ($stats->goals_difference ?? 0) > 0 ? '+' : '' }}{{ $stats->goals_difference ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-center">
                            {{ $stats->points ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($stats && $stats->form)
                                <div class="flex justify-center space-x-1">
                                    @foreach(array_slice($stats->form, 0, 5) as $result)
                                        <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-semibold rounded-full
                                            @if($result === 'win') bg-green-100 text-green-800
                                            @elseif($result === 'loss') bg-red-100 text-red-800
                                            @else bg-yellow-100 text-yellow-800 @endif">
                                            @if($result === 'win') W
                                            @elseif($result === 'loss') L
                                            @else D @endif
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-gray-400 text-sm">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($teams->isEmpty())
            <div class="text-center py-12">
                <div class="text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay equipos</h3>
                    <p class="mt-1 text-sm text-gray-500">No se han sincronizado equipos aún.</p>
                </div>
            </div>
        @endif
    </div>

    <!-- Legend -->
    <div class="mt-6 bg-white shadow rounded-lg p-4">
        <h3 class="text-sm font-medium text-gray-900 mb-3">Leyenda</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-xs text-gray-600">
            <div><strong>PJ:</strong> Partidos Jugados</div>
            <div><strong>G:</strong> Ganados</div>
            <div><strong>E:</strong> Empatados</div>
            <div><strong>P:</strong> Perdidos</div>
            <div><strong>GF:</strong> Goles a Favor</div>
            <div><strong>GC:</strong> Goles en Contra</div>
            <div><strong>DG:</strong> Diferencia de Goles</div>
            <div><strong>PTS:</strong> Puntos</div>
            <div><strong>Forma:</strong> Últimos 5 partidos (W=Victoria, D=Empate, L=Derrota)</div>
        </div>
    </div>
</div>
@endsection