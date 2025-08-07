<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Calcio - Predicciones de Fútbol')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="{{ route('home') }}" class="text-2xl font-bold text-blue-600">
                            ⚽ Calcio
                        </a>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="{{ route('home') }}" 
                           class="@if(request()->routeIs('home')) border-blue-500 text-gray-900 @else border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Inicio
                        </a>
                        <a href="{{ route('matches.index') }}" 
                           class="@if(request()->routeIs('matches.*')) border-blue-500 text-gray-900 @else border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Partidos
                        </a>
                        <a href="{{ route('statistics.index') }}" 
                           class="@if(request()->routeIs('statistics.*')) border-blue-500 text-gray-900 @else border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Estadísticas
                        </a>
                        <a href="{{ route('budget.index') }}" 
                           class="@if(request()->routeIs('budget.*')) border-blue-500 text-gray-900 @else border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Budget
                        </a>
                        <a href="{{ route('subscription.index') }}" 
                           class="@if(request()->routeIs('subscription.*')) border-blue-500 text-gray-900 @else border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Planes
                        </a>
                    </div>
                </div>
                
                <!-- Right side - Auth Links -->
                <div class="hidden sm:ml-6 sm:flex sm:items-center">
                    @guest
                        <a href="{{ route('login') }}" class="text-gray-500 hover:text-gray-700 px-3 py-2 text-sm font-medium">
                            Iniciar Sesión
                        </a>
                        <a href="{{ route('register') }}" class="ml-3 bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700">
                            Registrarse
                        </a>
                    @else
                        <div class="relative ml-3">
                            <div class="flex items-center space-x-4">
                                <!-- Subscription Status -->
                                <div class="flex items-center space-x-2">
                                    <span class="text-xs font-medium text-gray-600">Plan:</span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                               {{ auth()->user()->hasActiveSubscription() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ auth()->user()->getCurrentPlanName() }}
                                    </span>
                                    @if(auth()->user()->hasActiveSubscription())
                                        <span class="text-xs text-gray-500">
                                            {{ auth()->user()->getRemainingPicksToday() }}/{{ auth()->user()->getDailyPicksLimit() }} picks
                                        </span>
                                    @endif
                                </div>
                                
                                <!-- User Menu -->
                                <div class="relative">
                                    <button onclick="toggleUserMenu()" class="flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <span class="sr-only">Open user menu</span>
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-blue-600 font-medium">{{ substr(auth()->user()->name, 0, 1) }}</span>
                                        </div>
                                    </button>
                                    
                                    <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                                        <a href="{{ route('subscription.show') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Mi Suscripción
                                        </a>
                                        <a href="{{ route('subscription.plans') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Cambiar Plan
                                        </a>
                                        <div class="border-t border-gray-100"></div>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                Cerrar Sesión
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endguest
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t mt-12">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="text-center text-gray-500 text-sm">
                <p>&copy; {{ date('Y') }} Calcio - Predicciones de Fútbol con Machine Learning</p>
            </div>
        </div>
    </footer>

    <script>
        function toggleUserMenu() {
            const menu = document.getElementById('userMenu');
            menu.classList.toggle('hidden');
        }

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('userMenu');
            const button = event.target.closest('[onclick="toggleUserMenu()"]');
            
            if (!button && menu && !menu.contains(event.target)) {
                menu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>