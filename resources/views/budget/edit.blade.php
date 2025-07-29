@extends('layouts.app')

@section('title', 'Editar Configuración de Budget - ' . $budget->name . ' - Calcio')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <a href="{{ route('budget.show', $budget) }}" 
               class="inline-flex items-center text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Volver
            </a>
        </div>
        <h1 class="text-3xl font-bold text-gray-900 mt-4">Editar Configuración: {{ $budget->name }}</h1>
        <p class="mt-2 text-gray-600">Modifica los parámetros de tu estrategia de gestión de inversiones</p>
    </div>

    <div class="max-w-2xl">
        <form action="{{ route('budget.update', $budget) }}" method="POST" class="space-y-8">
            @csrf
            @method('PUT')

            <!-- Basic Information -->
            <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                <div class="md:grid md:grid-cols-3 md:gap-6">
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">Información Básica</h3>
                        <p class="mt-1 text-sm text-gray-500">Nombre y configuración básica.</p>
                        <div class="mt-4 p-3 bg-gray-50 rounded-md">
                            <p class="text-sm text-gray-600">
                                <span class="font-medium">Budget Inicial:</span> €{{ number_format($budget->initial_budget, 2) }}
                            </p>
                            <p class="text-sm text-gray-600 mt-1">
                                <span class="font-medium">Budget Actual:</span> €{{ number_format($budget->current_budget, 2) }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-5 md:mt-0 md:col-span-2">
                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6">
                                <label for="name" class="block text-sm font-medium text-gray-700">Nombre de la Configuración</label>
                                <input type="text" name="name" id="name" value="{{ old('name', $budget->name) }}" required
                                       class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                @error('name')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="col-span-6">
                                <label for="target_profit" class="block text-sm font-medium text-gray-700">Objetivo de Ganancia (€)</label>
                                <input type="number" name="target_profit" id="target_profit" value="{{ old('target_profit', $budget->target_profit) }}" 
                                       min="0" step="0.01"
                                       class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                       placeholder="Opcional">
                                @error('target_profit')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Strategy Selection -->
            <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                <div class="md:grid md:grid-cols-3 md:gap-6">
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">Estrategia</h3>
                        <p class="mt-1 text-sm text-gray-500">Método de gestión de tu bankroll.</p>
                    </div>
                    <div class="mt-5 md:mt-0 md:col-span-2">
                        <div class="space-y-4">
                            <div>
                                <label class="text-base font-medium text-gray-900">Tipo de Estrategia</label>
                                <fieldset class="mt-4">
                                    <legend class="sr-only">Estrategia de betting</legend>
                                    <div class="space-y-4">
                                        <!-- Mansaniello -->
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input id="strategy_mansaniello" name="strategy" type="radio" value="mansaniello"
                                                       {{ old('strategy', $budget->strategy) == 'mansaniello' ? 'checked' : '' }}
                                                       class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="strategy_mansaniello" class="font-medium text-gray-700">Mansaniello</label>
                                                <p class="text-gray-500">Progresión controlada con secuencia específica. Ideal para recuperación gradual.</p>
                                            </div>
                                        </div>

                                        <!-- Fibonacci -->
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input id="strategy_fibonacci" name="strategy" type="radio" value="fibonacci"
                                                       {{ old('strategy', $budget->strategy) == 'fibonacci' ? 'checked' : '' }}
                                                       class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="strategy_fibonacci" class="font-medium text-gray-700">Fibonacci</label>
                                                <p class="text-gray-500">Secuencia matemática clásica: 1, 1, 2, 3, 5, 8, 13...</p>
                                            </div>
                                        </div>

                                        <!-- Martingale -->
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input id="strategy_martingale" name="strategy" type="radio" value="martingale"
                                                       {{ old('strategy', $budget->strategy) == 'martingale' ? 'checked' : '' }}
                                                       class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="strategy_martingale" class="font-medium text-gray-700">Martingala Limitada</label>
                                                <p class="text-gray-500">Duplica la apuesta tras pérdida. <span class="text-red-600 font-medium">¡Alto riesgo!</span></p>
                                            </div>
                                        </div>

                                        <!-- Fixed -->
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input id="strategy_fixed" name="strategy" type="radio" value="fixed"
                                                       {{ old('strategy', $budget->strategy) == 'fixed' ? 'checked' : '' }}
                                                       class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="strategy_fixed" class="font-medium text-gray-700">Apuesta Fija</label>
                                                <p class="text-gray-500">Cantidad fija por apuesta. La más conservadora y simple.</p>
                                            </div>
                                        </div>

                                        <!-- Percentage -->
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input id="strategy_percentage" name="strategy" type="radio" value="percentage"
                                                       {{ old('strategy', $budget->strategy) == 'percentage' ? 'checked' : '' }}
                                                       class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="strategy_percentage" class="font-medium text-gray-700">Porcentaje Kelly</label>
                                                <p class="text-gray-500">Adapta el tamaño de apuesta según la confianza de la predicción.</p>
                                            </div>
                                        </div>
                                    </div>
                                </fieldset>
                                @error('strategy')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Risk Management -->
            <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                <div class="md:grid md:grid-cols-3 md:gap-6">
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">Gestión de Riesgo</h3>
                        <p class="mt-1 text-sm text-gray-500">Controla el riesgo de tus inversiones.</p>
                    </div>
                    <div class="mt-5 md:mt-0 md:col-span-2">
                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-3">
                                <label for="max_bet_percentage" class="block text-sm font-medium text-gray-700">Máximo por Apuesta (%)</label>
                                <input type="number" name="max_bet_percentage" id="max_bet_percentage" 
                                       value="{{ old('max_bet_percentage', $budget->max_bet_percentage) }}" min="0.1" max="50" step="0.1" required
                                       class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <p class="mt-1 text-xs text-gray-500">% máximo del budget por apuesta</p>
                                @error('max_bet_percentage')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <label for="min_confidence" class="block text-sm font-medium text-gray-700">Confianza Mínima (%)</label>
                                <input type="number" name="min_confidence" id="min_confidence" 
                                       value="{{ old('min_confidence', $budget->min_confidence) }}" min="50" max="99" step="1" required
                                       class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <p class="mt-1 text-xs text-gray-500">Solo apostar si la IA tiene esta confianza</p>
                                @error('min_confidence')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Strategy Parameters -->
            <div id="strategy-parameters" class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
                <div class="md:grid md:grid-cols-3 md:gap-6">
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">Parámetros Específicos</h3>
                        <p class="mt-1 text-sm text-gray-500">Configuración avanzada de la estrategia seleccionada.</p>
                    </div>
                    <div class="mt-5 md:mt-0 md:col-span-2">
                        <!-- Mansaniello Parameters -->
                        <div id="params-mansaniello" class="strategy-params space-y-4">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">Apuesta Base (€)</label>
                                    <input type="number" name="strategy_parameters[base_amount]" 
                                           value="{{ old('strategy_parameters.base_amount', $budget->strategy_parameters['base_amount'] ?? 10) }}" min="0.01" step="0.01"
                                           class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                </div>
                                <div class="col-span-6 sm:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">Máximo Pasos</label>
                                    <input type="number" name="strategy_parameters[max_sequence]" 
                                           value="{{ old('strategy_parameters.max_sequence', $budget->strategy_parameters['max_sequence'] ?? 10) }}" min="5" max="20"
                                           class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                </div>
                            </div>
                        </div>

                        <!-- Other strategy parameters would go here -->
                        <div id="params-other" class="strategy-params hidden">
                            <p class="text-sm text-gray-500">Los parámetros se configurarán automáticamente según la estrategia seleccionada.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-3">
                <a href="{{ route('budget.show', $budget) }}" 
                   class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancelar
                </a>
                <button type="submit" 
                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Actualizar Configuración
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const strategyRadios = document.querySelectorAll('input[name="strategy"]');
    const strategyParams = document.querySelectorAll('.strategy-params');
    
    function showStrategyParams() {
        const selectedStrategy = document.querySelector('input[name="strategy"]:checked').value;
        
        strategyParams.forEach(param => param.classList.add('hidden'));
        
        if (selectedStrategy === 'mansaniello') {
            document.getElementById('params-mansaniello').classList.remove('hidden');
        } else {
            document.getElementById('params-other').classList.remove('hidden');
        }
    }
    
    strategyRadios.forEach(radio => {
        radio.addEventListener('change', showStrategyParams);
    });
    
    // Show initial params
    showStrategyParams();
});
</script>
@endsection