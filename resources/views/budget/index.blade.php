@extends('layouts.app')

@section('title', 'Gestión de Budget - Calcio')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Gestión de Budget</h1>
                <p class="mt-2 text-gray-600">Controla tus inversiones con estrategias como Mansaniello, Fibonacci y más</p>
            </div>
            <div class="flex-shrink-0">
                <a href="{{ route('budget.create') }}" 
                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Nueva Configuración
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Budget Total</dt>
                            <dd class="text-lg font-medium text-gray-900">€{{ number_format($totalBudget, 2) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-{{ $totalProfit >= 0 ? 'green' : 'red' }}-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-{{ $totalProfit >= 0 ? 'green' : 'red' }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                @if($totalProfit >= 0)
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                                @endif
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Beneficio Total</dt>
                            <dd class="text-lg font-medium text-{{ $totalProfit >= 0 ? 'green' : 'red' }}-600">
                                {{ $totalProfit >= 0 ? '+' : '' }}€{{ number_format($totalProfit, 2) }}
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
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Configuraciones</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $configurations->count() }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Budget Configurations -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md mb-8">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Configuraciones de Budget</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Gestiona tus diferentes estrategias de inversión</p>
        </div>
        
        @if($configurations->count() > 0)
            <ul class="divide-y divide-gray-200">
                @foreach($configurations as $config)
                <li>
                    <div class="px-4 py-4 sm:px-6 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-{{ $config->is_active ? 'green' : 'gray' }}-100 rounded-full flex items-center justify-center">
                                        <span class="text-{{ $config->is_active ? 'green' : 'gray' }}-600 font-bold text-lg">
                                            {{ substr(ucfirst($config->strategy), 0, 1) }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <h4 class="text-lg font-medium text-gray-900">{{ $config->name }}</h4>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($config->is_active) bg-green-100 text-green-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            @if($config->is_active) Activa @else Inactiva @endif
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <span class="font-medium">{{ ucfirst($config->strategy) }}</span> • 
                                        Budget: €{{ number_format($config->current_budget, 2) }} • 
                                        Win Rate: {{ $config->getWinRateAttribute() }}%
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-4">
                                <div class="text-right">
                                    <div class="text-sm font-medium text-{{ $config->getNetProfitAttribute() >= 0 ? 'green' : 'red' }}-600">
                                        {{ $config->getNetProfitAttribute() >= 0 ? '+' : '' }}€{{ number_format($config->getNetProfitAttribute(), 2) }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        ROI: {{ $config->getROIAttribute() }}%
                                    </div>
                                </div>
                                <div class="flex-shrink-0 flex space-x-2">
                                    <a href="{{ route('budget.show', $config) }}" 
                                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Ver Detalles
                                    </a>
                                    <form action="{{ route('budget.destroy', $config) }}" method="POST" class="inline"
                                          onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta configuración? Esta acción no se puede deshacer.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="inline-flex items-center px-3 py-2 border border-red-300 shadow-sm text-sm leading-4 font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
                @endforeach
            </ul>
        @else
            <div class="px-4 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay configuraciones de budget</h3>
                <p class="mt-1 text-sm text-gray-500">Comienza creando tu primera estrategia de inversión.</p>
                <div class="mt-6">
                    <a href="{{ route('budget.create') }}" 
                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Crear Configuración
                    </a>
                </div>
            </div>
        @endif
    </div>

    <!-- Recent Bets -->
    @if($recentBets->count() > 0)
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Apuestas Recientes</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Últimas 10 apuestas realizadas</p>
        </div>
        <ul class="divide-y divide-gray-200">
            @foreach($recentBets as $bet)
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
                                {{ $bet->match->homeTeam->name }} vs {{ $bet->match->awayTeam->name }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $bet->getBetTypeDisplayAttribute() }} • €{{ number_format($bet->amount, 2) }} @ {{ $bet->odds }}
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-900">
                            {{ $bet->budgetConfiguration->name }}
                        </div>
                        <div class="text-sm 
                            @if($bet->actual_profit !== null)
                                text-{{ $bet->actual_profit >= 0 ? 'green' : 'red' }}-600
                            @else
                                text-blue-600
                            @endif">
                            @if($bet->actual_profit !== null)
                                {{ $bet->actual_profit >= 0 ? '+' : '' }}€{{ number_format($bet->actual_profit, 2) }}
                            @else
                                €{{ number_format($bet->potential_profit, 2) }} pot.
                            @endif
                        </div>
                    </div>
                </div>
            </li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
@endsection