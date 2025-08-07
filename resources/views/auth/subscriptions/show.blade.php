@extends('layouts.app')

@section('title', 'Mi Suscripción')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Mi Suscripción</h1>
        <p class="text-gray-600">Gestiona tu plan y visualiza tu historial de suscripciones</p>
    </div>

    <!-- Current Subscription Card -->
    <div class="bg-white rounded-lg shadow-lg mb-8">
        <div class="p-6">
            @if($currentSubscription)
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Plan {{ $currentSubscription->subscriptionPlan->name }}</h2>
                        <p class="text-gray-600">{{ $currentSubscription->subscriptionPlan->description }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Estado</div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                   {{ $currentSubscription->isActive() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $currentSubscription->isActive() ? 'Activo' : ucfirst($currentSubscription->status) }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="text-center p-4 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600">{{ $todayAllocation->picks_allocated }}</div>
                        <div class="text-sm text-gray-600">Picks Diarios</div>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600">{{ $todayAllocation->remaining_picks }}</div>
                        <div class="text-sm text-gray-600">Picks Restantes Hoy</div>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-purple-600">{{ $todayAllocation->usage_percentage }}%</div>
                        <div class="text-sm text-gray-600">Usado Hoy</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Características del Plan</h3>
                        <ul class="space-y-2">
                            @foreach($currentSubscription->subscriptionPlan->features as $feature)
                            <li class="flex items-center text-sm">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                {{ $feature }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-3">Detalles de Facturación</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Precio Pagado:</span>
                                <span class="font-medium">€{{ number_format($currentSubscription->price_paid, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Inicio:</span>
                                <span class="font-medium">{{ $currentSubscription->starts_at->format('d/m/Y') }}</span>
                            </div>
                            @if($currentSubscription->ends_at)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Vencimiento:</span>
                                <span class="font-medium">{{ $currentSubscription->ends_at->format('d/m/Y') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tiempo Restante:</span>
                                <span class="font-medium">{{ $currentSubscription->remaining_time }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between">
                                <span class="text-gray-600">Método de Pago:</span>
                                <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $currentSubscription->payment_method ?? 'N/A')) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('subscription.plans') }}" 
                       class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Cambiar Plan
                    </a>
                    
                    @if($currentSubscription->isActive() && $currentSubscription->subscriptionPlan->price > 0)
                    <form action="{{ route('subscription.cancel') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" 
                                onclick="return confirm('¿Estás seguro de que quieres cancelar tu suscripción?')"
                                class="px-6 py-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50 transition-colors">
                            Cancelar Suscripción
                        </button>
                    </form>
                    @endif
                </div>
            @else
                <!-- No Active Subscription -->
                <div class="text-center py-12">
                    <svg class="mx-auto h-24 w-24 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No tienes una suscripción activa</h3>
                    <p class="text-gray-600 mb-6">Estás usando el plan gratuito con acceso limitado.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ $todayAllocation->picks_allocated }}</div>
                            <div class="text-sm text-gray-600">Pick Diario</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ $todayAllocation->remaining_picks }}</div>
                            <div class="text-sm text-gray-600">Picks Restantes Hoy</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl font-bold text-gray-600">Aleatorio</div>
                            <div class="text-sm text-gray-600">Ligas Disponibles</div>
                        </div>
                    </div>
                    
                    <a href="{{ route('subscription.index') }}" 
                       class="inline-block px-8 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                        Ver Planes de Suscripción
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Subscription History -->
    @if($subscriptionHistory->count() > 0)
    <div class="bg-white rounded-lg shadow-lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Historial de Suscripciones</h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($subscriptionHistory as $subscription)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">{{ $subscription->subscriptionPlan->name }}</div>
                                <div class="text-sm text-gray-500">{{ $subscription->subscriptionPlan->billing_cycle === 'yearly' ? 'Anual' : 'Mensual' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                €{{ number_format($subscription->price_paid, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $subscription->starts_at->format('d/m/Y') }} - 
                                {{ $subscription->ends_at ? $subscription->ends_at->format('d/m/Y') : 'Indefinido' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                           {{ $subscription->status === 'active' ? 'bg-green-100 text-green-800' : 
                                              ($subscription->status === 'cancelled' ? 'bg-yellow-100 text-yellow-800' : 
                                               'bg-red-100 text-red-800') }}">
                                    {{ ucfirst($subscription->status) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            @if($subscriptionHistory->hasPages())
            <div class="mt-6">
                {{ $subscriptionHistory->links() }}
            </div>
            @endif
        </div>
    </div>
    @endif
</div>

@if(session('success'))
<div class="fixed inset-0 flex items-end justify-center px-4 py-6 pointer-events-none sm:p-6 sm:items-start sm:justify-end z-50">
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
                        <button class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500" onclick="this.parentElement.parentElement.parentElement.parentElement.parentElement.remove()">
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
// Auto-hide success message after 5 seconds
setTimeout(() => {
    const notification = document.querySelector('.fixed');
    if (notification) {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }
}, 5000);
</script>
@endif
@endsection