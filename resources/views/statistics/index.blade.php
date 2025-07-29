@extends('layouts.app')

@section('title', 'Estadísticas de Predicciones - Calcio')

@section('content')
<div class="px-4 sm:px-0">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Estadísticas de Predicciones</h1>
                <p class="mt-2 text-gray-600">Análisis detallado del rendimiento de nuestros modelos de Machine Learning</p>
            </div>
            <a href="{{ route('statistics.refresh') }}" 
               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Actualizar
            </a>
        </div>
    </div>

    <!-- Overall Stats Cards -->
    <!-- First Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="group bg-white overflow-hidden shadow rounded-lg hover:shadow-xl hover:scale-105 hover:border-green-200 hover:bg-green-50 transition-all duration-300 ease-in-out border-2 border-transparent">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center transition-all duration-300 group-hover:bg-green-200 group-hover:scale-110">
                            <span class="text-green-600 font-bold text-lg transition-all duration-300 group-hover:text-green-700">📊</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate transition-colors duration-300 group-hover:text-green-600">Precisión General</dt>
                            <dd class="text-lg font-medium text-gray-900 transition-colors duration-300 group-hover:text-green-700">{{ $overallAccuracy }}%</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        @foreach(['match_outcome', 'both_teams_score_yes', 'both_teams_score_no'] as $type)
            @php
                $stat = $statistics[$type] ?? null;
                $colors = [
                    'match_outcome' => 'blue',
                    'both_teams_score_yes' => 'green', 
                    'both_teams_score_no' => 'orange',
                    'over_2_5' => 'purple',
                    'under_2_5' => 'red'
                ];
                $icons = [
                    'match_outcome' => '🏆',
                    'both_teams_score_yes' => '⚽',
                    'both_teams_score_no' => '🚫'
                ];
                $color = $colors[$type];
                $icon = $icons[$type];
            @endphp
            <div class="group bg-white overflow-hidden shadow rounded-lg cursor-pointer hover:shadow-xl hover:scale-105 hover:border-{{ $color }}-200 hover:bg-{{ $color }}-50 transition-all duration-300 ease-in-out card-clickable border-2 border-transparent" data-type="{{ $type }}" style="cursor: pointer;">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-{{ $color }}-100 rounded-full flex items-center justify-center transition-all duration-300 group-hover:bg-{{ $color }}-200 group-hover:scale-110">
                                <span class="text-{{ $color }}-600 font-bold text-lg transition-all duration-300 group-hover:text-{{ $color }}-700">{{ $icon }}</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate transition-colors duration-300 group-hover:text-{{ $color }}-600">{{ $stat->display_name ?? 'N/A' }}</dt>
                                <dd class="text-lg font-medium text-gray-900 transition-colors duration-300 group-hover:text-{{ $color }}-700">
                                    {{ $stat ? $stat->accuracy_percentage . '%' : 'N/A' }}
                                </dd>
                                <dd class="text-xs text-gray-500 transition-colors duration-300 group-hover:text-{{ $color }}-500">
                                    {{ $stat ? $stat->correct_predictions . '/' . $stat->total_predictions : '0/0' }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Second Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        @foreach(['over_2_5', 'under_2_5', 'first_half_over_0_5', 'first_half_over_1_5'] as $type)
            @php
                $stat = $statistics[$type] ?? null;
                $colors = [
                    'over_2_5' => 'purple',
                    'under_2_5' => 'red',
                    'first_half_over_0_5' => 'indigo',
                    'first_half_over_1_5' => 'pink'
                ];
                $icons = [
                    'over_2_5' => '📈',
                    'under_2_5' => '📉',
                    'first_half_over_0_5' => '🕐',
                    'first_half_over_1_5' => '⏰'
                ];
                $color = $colors[$type];
                $icon = $icons[$type];
            @endphp
            <div class="group bg-white overflow-hidden shadow rounded-lg cursor-pointer hover:shadow-xl hover:scale-105 hover:border-{{ $color }}-200 hover:bg-{{ $color }}-50 transition-all duration-300 ease-in-out card-clickable border-2 border-transparent" data-type="{{ $type }}" style="cursor: pointer;">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-{{ $color }}-100 rounded-full flex items-center justify-center transition-all duration-300 group-hover:bg-{{ $color }}-200 group-hover:scale-110">
                                <span class="text-{{ $color }}-600 font-bold text-lg transition-all duration-300 group-hover:text-{{ $color }}-700">{{ $icon }}</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate transition-colors duration-300 group-hover:text-{{ $color }}-600">{{ $stat->display_name ?? 'N/A' }}</dt>
                                <dd class="text-lg font-medium text-gray-900 transition-colors duration-300 group-hover:text-{{ $color }}-700">
                                    {{ $stat ? $stat->accuracy_percentage . '%' : 'N/A' }}
                                </dd>
                                <dd class="text-xs text-gray-500 transition-colors duration-300 group-hover:text-{{ $color }}-500">
                                    {{ $stat ? $stat->correct_predictions . '/' . $stat->total_predictions : '0/0' }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Charts Section (Hidden by default) -->
    <div id="charts-section" class="hidden">
        @foreach(['match_outcome', 'both_teams_score_yes', 'both_teams_score_no', 'over_2_5', 'under_2_5', 'first_half_over_0_5', 'first_half_over_1_5'] as $type)
            @php
                $stat = $statistics[$type] ?? null;
                if (!$stat) continue;
            @endphp
            <div id="chart-container-{{ $type }}" class="bg-white overflow-hidden shadow rounded-lg mb-8 hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ $stat->display_name }} - <span id="period-title-{{ $type }}">Evolución Mensual</span>
                        </h3>
                        <div class="flex space-x-1">
                            <button class="period-btn active px-2 py-1 text-xs bg-blue-500 text-white rounded" 
                                    data-period="daily" data-type="{{ $type }}">Diario</button>
                            <button class="period-btn px-2 py-1 text-xs bg-gray-200 text-gray-700 rounded hover:bg-gray-300" 
                                    data-period="weekly" data-type="{{ $type }}">Semanal</button>
                            <button class="period-btn px-2 py-1 text-xs bg-gray-200 text-gray-700 rounded hover:bg-gray-300" 
                                    data-period="monthly" data-type="{{ $type }}">Mensual</button>
                            <button class="period-btn px-2 py-1 text-xs bg-gray-200 text-gray-700 rounded hover:bg-gray-300" 
                                    data-period="yearly" data-type="{{ $type }}">Anual</button>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <canvas id="chart-{{ $type }}" width="400" height="200"></canvas>
                </div>
            </div>
        @endforeach
    </div>

    <!-- League Performance -->
    <div class="bg-white overflow-hidden shadow rounded-lg mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Rendimiento por Liga</h3>
        </div>
        <div class="p-6">
            <canvas id="league-chart" width="400" height="200"></canvas>
        </div>
    </div>

    <!-- Recent Predictions Details -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Predicciones Recientes</h3>
                <div class="flex space-x-2">
                    <select id="detail-type" class="text-sm border-gray-300 rounded-md">
                        <option value="match_outcome">Resultado del Partido</option>
                        <option value="both_teams_score_yes">Gol</option>
                        <option value="both_teams_score_no">No Gol</option>
                        <option value="over_2_5">Over 2.5 Goles</option>
                        <option value="under_2_5">Under 2.5 Goles</option>
                        <option value="first_half_over_0_5">Over 0.5 Goles (1T)</option>
                        <option value="first_half_over_1_5">Over 1.5 Goles (1T)</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr id="table-header">
                        <!-- Dynamic headers will be inserted here -->
                    </tr>
                </thead>
                <tbody id="table-body" class="bg-white divide-y divide-gray-200">
                    <!-- Dynamic rows will be inserted here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.card-clickable, .group {
    cursor: pointer !important;
}

/* Ensure all hover effects work properly */
.group:hover {
    cursor: pointer !important;
}

/* Fallback for dynamic colors that might not be generated by Tailwind */
.hover\:border-blue-200:hover { border-color: rgb(191 219 254) !important; }
.hover\:bg-blue-50:hover { background-color: rgb(239 246 255) !important; }
.hover\:border-green-200:hover { border-color: rgb(187 247 208) !important; }
.hover\:bg-green-50:hover { background-color: rgb(240 253 244) !important; }
.hover\:border-orange-200:hover { border-color: rgb(254 215 170) !important; }
.hover\:bg-orange-50:hover { background-color: rgb(255 247 237) !important; }
.hover\:border-purple-200:hover { border-color: rgb(196 181 253) !important; }
.hover\:bg-purple-50:hover { background-color: rgb(250 245 255) !important; }
.hover\:border-red-200:hover { border-color: rgb(254 202 202) !important; }
.hover\:bg-red-50:hover { background-color: rgb(254 242 242) !important; }
.hover\:border-indigo-200:hover { border-color: rgb(199 210 254) !important; }
.hover\:bg-indigo-50:hover { background-color: rgb(238 242 255) !important; }
.hover\:border-pink-200:hover { border-color: rgb(251 207 232) !important; }
.hover\:bg-pink-50:hover { background-color: rgb(253 242 248) !important; }

/* Group hover effects for icons and text */
.group:hover .group-hover\:bg-blue-200 { background-color: rgb(191 219 254) !important; }
.group:hover .group-hover\:text-blue-600 { color: rgb(37 99 235) !important; }
.group:hover .group-hover\:text-blue-700 { color: rgb(29 78 216) !important; }
.group:hover .group-hover\:bg-green-200 { background-color: rgb(187 247 208) !important; }
.group:hover .group-hover\:text-green-600 { color: rgb(22 163 74) !important; }
.group:hover .group-hover\:text-green-700 { color: rgb(21 128 61) !important; }
.group:hover .group-hover\:bg-orange-200 { background-color: rgb(254 215 170) !important; }
.group:hover .group-hover\:text-orange-600 { color: rgb(234 88 12) !important; }
.group:hover .group-hover\:text-orange-700 { color: rgb(194 65 12) !important; }
.group:hover .group-hover\:bg-purple-200 { background-color: rgb(196 181 253) !important; }
.group:hover .group-hover\:text-purple-600 { color: rgb(147 51 234) !important; }
.group:hover .group-hover\:text-purple-700 { color: rgb(126 34 206) !important; }
.group:hover .group-hover\:bg-red-200 { background-color: rgb(254 202 202) !important; }
.group:hover .group-hover\:text-red-600 { color: rgb(220 38 38) !important; }
.group:hover .group-hover\:text-red-700 { color: rgb(185 28 28) !important; }
.group:hover .group-hover\:bg-indigo-200 { background-color: rgb(199 210 254) !important; }
.group:hover .group-hover\:text-indigo-600 { color: rgb(79 70 229) !important; }
.group:hover .group-hover\:text-indigo-700 { color: rgb(67 56 202) !important; }
.group:hover .group-hover\:bg-pink-200 { background-color: rgb(251 207 232) !important; }
.group:hover .group-hover\:text-pink-600 { color: rgb(219 39 119) !important; }
.group:hover .group-hover\:text-pink-700 { color: rgb(190 24 93) !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart colors
    const colors = {
        match_outcome: {
            border: 'rgb(59, 130, 246)',
            background: 'rgba(59, 130, 246, 0.1)'
        },
        both_teams_score_yes: {
            border: 'rgb(34, 197, 94)',
            background: 'rgba(34, 197, 94, 0.1)'
        },
        both_teams_score_no: {
            border: 'rgb(249, 115, 22)',
            background: 'rgba(249, 115, 22, 0.1)'
        },
        over_2_5: {
            border: 'rgb(147, 51, 234)',
            background: 'rgba(147, 51, 234, 0.1)'
        },
        under_2_5: {
            border: 'rgb(239, 68, 68)',
            background: 'rgba(239, 68, 68, 0.1)'
        },
        first_half_over_0_5: {
            border: 'rgb(99, 102, 241)',
            background: 'rgba(99, 102, 241, 0.1)'
        },
        first_half_over_1_5: {
            border: 'rgb(236, 72, 153)',
            background: 'rgba(236, 72, 153, 0.1)'
        }
    };

    // Store chart instances
    const charts = {};

    // Handle card clicks to show/hide charts
    let currentActiveCard = null;
    let isChartInitialized = {};

    document.addEventListener('click', function(e) {
        const card = e.target.closest('.card-clickable');
        if (card) {
            const type = card.dataset.type;
            
            // If clicking the same card, hide chart
            if (currentActiveCard === type) {
                hideAllCharts();
                currentActiveCard = null;
                removeActiveCardStyles();
                return;
            }
            
            // Hide all charts first
            hideAllCharts();
            removeActiveCardStyles();
            
            // Show the selected chart
            showChart(type);
            currentActiveCard = type;
            
            // Add active style to clicked card
            card.classList.add('ring-2', 'ring-blue-500', 'ring-opacity-50');
            
            // Initialize chart if not already done
            if (!isChartInitialized[type]) {
                loadChart(type, 'daily');
                isChartInitialized[type] = true;
            }
        }
    });

    function showChart(type) {
        document.getElementById('charts-section').classList.remove('hidden');
        document.getElementById(`chart-container-${type}`).classList.remove('hidden');
    }

    function hideAllCharts() {
        document.getElementById('charts-section').classList.add('hidden');
        const chartContainers = document.querySelectorAll('[id^="chart-container-"]');
        chartContainers.forEach(container => {
            container.classList.add('hidden');
        });
    }

    function removeActiveCardStyles() {
        const activeCards = document.querySelectorAll('.card-clickable');
        activeCards.forEach(card => {
            card.classList.remove('ring-2', 'ring-blue-500', 'ring-opacity-50');
        });
    }

    // Function to load chart data
    function loadChart(type, period) {
        const periodTitles = {
            daily: 'Evolución Diaria (últimos 30 días)',
            weekly: 'Evolución Semanal (últimas 12 semanas)',
            monthly: 'Evolución Mensual (últimos 12 meses)',
            yearly: 'Evolución Anual'
        };

        // Update title
        document.getElementById(`period-title-${type}`).textContent = periodTitles[period];

        fetch(`/statistics/chart-data?type=${type}&period=${period}`)
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById(`chart-${type}`).getContext('2d');
                
                // Destroy existing chart if it exists
                if (charts[type]) {
                    charts[type].destroy();
                }
                
                charts[type] = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Precisión (%)',
                            data: data.data,
                            borderColor: colors[type].border,
                            backgroundColor: colors[type].background,
                            tension: 0.1,
                            fill: true,
                            spanGaps: true
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                        callback: function(value) {
                                            return value + '%';
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error loading chart:', error));
    }

    // Handle period button clicks
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('period-btn')) {
            const period = e.target.dataset.period;
            const type = e.target.dataset.type;
            
            // Update button states
            const buttons = document.querySelectorAll(`[data-type="${type}"]`);
            buttons.forEach(btn => {
                btn.classList.remove('active', 'bg-blue-500', 'text-white');
                btn.classList.add('bg-gray-200', 'text-gray-700');
            });
            
            e.target.classList.add('active', 'bg-blue-500', 'text-white');
            e.target.classList.remove('bg-gray-200', 'text-gray-700');
            
            // Load new chart data
            loadChart(type, period);
        }
    });

    // Initialize league chart
    @if($statistics['match_outcome'] ?? null)
        fetch('/statistics/league-data?type=match_outcome')
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById('league-chart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Precisión por Liga (%)',
                            data: data.data,
                            backgroundColor: data.colors || [
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(34, 197, 94, 0.8)',
                                'rgba(147, 51, 234, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(239, 68, 68, 0.8)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        indexAxis: 'y', // Horizontal bar chart for better label visibility
                        scales: {
                            x: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            });
    @endif

    // Load details table
    function loadDetailsTable(type) {
        fetch(`/statistics/detail-data?type=${type}`)
            .then(response => response.json())
            .then(data => {
                updateDetailsTable(data, type);
            });
    }

    function updateDetailsTable(data, type) {
        const headerRow = document.getElementById('table-header');
        const tableBody = document.getElementById('table-body');
        
        // Clear existing content
        headerRow.innerHTML = '';
        tableBody.innerHTML = '';
        
        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-gray-500">No hay datos disponibles</td></tr>';
            return;
        }

        // Set headers based on type
        let headers = ['Partido', 'Fecha', 'Resultado'];
        
        if (type === 'both_teams_score_yes' || type === 'both_teams_score_no') {
            headers.push('Real', 'Predicción', 'Confianza', 'Correcto');
        } else if (type === 'over_2_5' || type === 'under_2_5') {
            headers.push('Total Goles', 'Real', 'Predicción', 'Confianza', 'Correcto');
        } else if (type === 'first_half_over_0_5' || type === 'first_half_over_1_5') {
            headers.push('Real', 'Predicción', 'Confianza', 'Correcto');
        } else {
            headers.push('Real', 'Predicción', 'Confianza', 'Correcto');
        }

        // Create header
        headers.forEach(header => {
            const th = document.createElement('th');
            th.className = 'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
            th.textContent = header;
            headerRow.appendChild(th);
        });

        // Create rows
        data.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = createRowHTML(row, type);
            tableBody.appendChild(tr);
        });
    }

    function createRowHTML(row, type) {
        let cells = [
            `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${row.match}</td>`,
            `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${row.date}</td>`,
            `<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${row.score}</td>`
        ];

        if (type === 'over_2_5' || type === 'under_2_5') {
            cells.push(
                `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${row.total_goals || 'N/A'}</td>`,
                `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${row.actual}</td>`,
                `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${row.predicted}</td>`,
                `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${row.confidence || 'N/A'}</td>`
            );
        } else {
            cells.push(
                `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${row.actual}</td>`,
                `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${row.predicted}</td>`,
                `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${row.confidence || 'N/A'}</td>`
            );
        }

        // Add correct/incorrect indicator
        const correctIndicator = row.correct 
            ? '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">✓</span>'
            : '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">✗</span>';
        
        cells.push(`<td class="px-6 py-4 whitespace-nowrap text-sm">${correctIndicator}</td>`);

        return cells.join('');
    }

    // Load initial table
    loadDetailsTable('match_outcome');

    // Handle type change
    document.getElementById('detail-type').addEventListener('change', function() {
        loadDetailsTable(this.value);
    });
});
</script>
@endsection