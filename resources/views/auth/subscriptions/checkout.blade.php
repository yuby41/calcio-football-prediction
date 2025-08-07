@extends('layouts.app')

@section('title', 'Checkout - ' . $plan->name)

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-0">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Finalizar Suscripción</h1>
        <p class="text-gray-600">Estás a un paso de acceder al plan <strong>{{ $plan->name }}</strong></p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Plan Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-lg p-6 sticky top-4">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">Resumen del Plan</h3>
                
                <div class="border-b pb-4 mb-4">
                    <h4 class="font-semibold text-gray-900">{{ $plan->name }}</h4>
                    <p class="text-sm text-gray-600 mt-1">{{ $plan->description }}</p>
                </div>

                <div class="space-y-3 mb-6">
                    @foreach($plan->features as $feature)
                    <div class="flex items-center">
                        <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm text-gray-700">{{ $feature }}</span>
                    </div>
                    @endforeach
                </div>

                <div class="border-t pt-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-semibold">{{ $plan->formatted_price }}</span>
                    </div>
                    @if($plan->is_yearly)
                    <div class="flex justify-between items-center mb-2 text-sm">
                        <span class="text-green-600">Ahorro anual:</span>
                        <span class="text-green-600 font-semibold">{{ $plan->yearly_savings }}%</span>
                    </div>
                    @endif
                    <div class="flex justify-between items-center text-lg font-bold">
                        <span>Total:</span>
                        <span>{{ $plan->formatted_price }}</span>
                    </div>
                    @if($plan->is_yearly)
                    <p class="text-xs text-gray-500 mt-2">
                        Equivale a €{{ number_format($plan->monthly_price, 2) }}/mes
                    </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Checkout Form -->
        <div class="lg:col-span-2">
            <form action="{{ route('subscription.subscribe', $plan->slug) }}" method="POST" class="bg-white rounded-lg shadow-lg p-6">
                @csrf

                <!-- User Information -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Información de la Cuenta</h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                <span class="text-blue-600 font-semibold">{{ substr($user->name, 0, 1) }}</span>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900">{{ $user->name }}</div>
                                <div class="text-sm text-gray-600">{{ $user->email }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($plan->price > 0)
                <!-- Payment Method -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Método de Pago</h3>
                    
                    <div class="space-y-3">
                        <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="payment_method" value="credit_card" class="mr-3" checked>
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-100 rounded mr-3 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2h12v2H4V6zm0 4h12v4H4v-4z"></path>
                                    </svg>
                                </div>
                                <span class="font-medium">Tarjeta de Crédito/Débito</span>
                            </div>
                        </label>

                        <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="payment_method" value="paypal" class="mr-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-yellow-100 rounded mr-3 flex items-center justify-center">
                                    <span class="text-xs font-bold text-yellow-700">PP</span>
                                </div>
                                <span class="font-medium">PayPal</span>
                            </div>
                        </label>

                        <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="payment_method" value="stripe" class="mr-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-purple-100 rounded mr-3 flex items-center justify-center">
                                    <span class="text-xs font-bold text-purple-700">S</span>
                                </div>
                                <span class="font-medium">Stripe</span>
                            </div>
                        </label>
                    </div>
                </div>
                @else
                <input type="hidden" name="payment_method" value="free">
                @endif

                <!-- Terms and Conditions -->
                <div class="mb-6">
                    <label class="flex items-start">
                        <input type="checkbox" name="terms_accepted" value="1" class="mt-1 mr-3" required>
                        <span class="text-sm text-gray-600">
                            Acepto los <a href="#" class="text-blue-600 hover:underline">Términos y Condiciones</a> 
                            y la <a href="#" class="text-blue-600 hover:underline">Política de Privacidad</a>
                        </span>
                    </label>
                </div>

                @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <ul class="text-red-700 text-sm space-y-1">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <!-- Action Buttons -->
                <div class="flex space-x-4">
                    <a href="{{ route('subscription.index') }}" 
                       class="flex-1 px-6 py-3 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 text-center">
                        Volver a Planes
                    </a>
                    <button type="submit" 
                            class="flex-1 px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                        @if($plan->price > 0)
                            Suscribirse por {{ $plan->formatted_price }}
                        @else
                            Comenzar Plan Gratuito
                        @endif
                    </button>
                </div>

                @if($plan->price > 0)
                <p class="text-xs text-gray-500 text-center mt-4">
                    Pago seguro procesado con encriptación SSL. Puedes cancelar en cualquier momento.
                </p>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection