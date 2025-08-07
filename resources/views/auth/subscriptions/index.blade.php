@extends('layouts.app')

@section('title', 'Planes de Suscripción')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">
            Elige tu Plan de Suscripción
        </h1>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            Accede a picks de fútbol diarios respaldados por IA avanzada y estrategias de betting profesionales.
        </p>
    </div>

    <!-- Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
        @foreach($plans as $plan)
        <div class="bg-white rounded-xl shadow-lg overflow-hidden {{ $plan->slug === 'pro' ? 'ring-2 ring-blue-500 scale-105' : '' }} 
                    {{ $plan->slug === 'vip-annual' ? 'ring-2 ring-purple-500' : '' }}">
            
            @if($plan->slug === 'pro')
            <div class="bg-blue-500 text-white text-center py-2 text-sm font-semibold">
                MÁS POPULAR
            </div>
            @elseif($plan->slug === 'vip-annual')
            <div class="bg-purple-500 text-white text-center py-2 text-sm font-semibold">
                MEJOR VALOR - 17% AHORRO
            </div>
            @endif

            <div class="p-6">
                <!-- Plan Header -->
                <div class="text-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan->name }}</h3>
                    <div class="mb-2">
                        @if($plan->price > 0)
                            <span class="text-4xl font-bold text-gray-900">€{{ number_format($plan->getMonthlyPriceAttribute(), 2) }}</span>
                            <span class="text-gray-600">/mes</span>
                        @else
                            <span class="text-4xl font-bold text-green-600">Gratis</span>
                        @endif
                    </div>
                    
                    @if($plan->is_yearly)
                    <div class="text-sm text-gray-500">
                        Facturado anualmente (€{{ number_format($plan->price, 2) }}/año)
                    </div>
                    @endif
                    
                    <p class="text-gray-600 text-sm">{{ $plan->description }}</p>
                </div>

                <!-- Features -->
                <ul class="space-y-3 mb-8">
                    @foreach($plan->features as $feature)
                    <li class="flex items-center">
                        <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-gray-700">{{ $feature }}</span>
                    </li>
                    @endforeach
                </ul>

                <!-- CTA Button -->
                <div class="text-center">
                    @if($plan->price > 0)
                        @auth
                            <a href="{{ route('subscription.checkout', $plan->slug) }}" 
                               class="w-full inline-block px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                                Elegir {{ $plan->name }}
                            </a>
                        @else
                            <a href="{{ route('register') }}" 
                               class="w-full inline-block px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                                Registrarse y Elegir
                            </a>
                        @endauth
                    @else
                        @auth
                            <a href="{{ route('subscription.checkout', $plan->slug) }}" 
                               class="w-full inline-block px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors">
                                Comenzar Gratis
                            </a>
                        @else
                            <a href="{{ route('register') }}" 
                               class="w-full inline-block px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors">
                                Comenzar Gratis
                            </a>
                        @endauth
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- FAQ Section -->
    <div class="max-w-4xl mx-auto">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-8">Preguntas Frecuentes</h2>
        
        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">¿Qué incluye el análisis IA?</h3>
                <p class="text-gray-600">Nuestro sistema IA analiza estadísticas históricas, forma actual, lesiones, y patrones de juego para generar predicciones precisas con porcentajes de confianza.</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">¿Puedo cambiar de plan en cualquier momento?</h3>
                <p class="text-gray-600">Sí, puedes actualizar o cambiar tu plan en cualquier momento. Los cambios se aplican inmediatamente con prorrateo automático.</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">¿Cómo funcionan las estrategias de budget?</h3>
                <p class="text-gray-600">Incluimos estrategias como Kelly Criterion, Martingale, Fibonacci y más, con gestión automática de tu bankroll basada en el riesgo seleccionado.</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">¿Hay garantía de devolución?</h3>
                <p class="text-gray-600">Ofrecemos garantía de satisfacción de 7 días. Si no estás satisfecho, te devolvemos tu dinero sin preguntas.</p>
            </div>
        </div>
    </div>
</div>
@endsection