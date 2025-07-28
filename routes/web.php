<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\StatisticsController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/manual-update', [HomeController::class, 'manualUpdate'])->name('home.manual-update');
Route::get('/matches', [MatchController::class, 'index'])->name('matches.index');
Route::get('/matches/{footballMatch}', [MatchController::class, 'show'])->name('matches.show');
Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');

// Statistics routes
Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');
Route::get('/statistics/chart-data', [StatisticsController::class, 'chartData'])->name('statistics.chart-data');
Route::get('/statistics/league-data', [StatisticsController::class, 'leagueData'])->name('statistics.league-data');
Route::get('/statistics/detail-data', [StatisticsController::class, 'detailData'])->name('statistics.detail-data');
Route::get('/statistics/refresh', [StatisticsController::class, 'refresh'])->name('statistics.refresh');

// API Routes for live updates
Route::prefix('api')->group(function () {
    Route::get('/live/matches', [App\Http\Controllers\Api\LiveUpdatesController::class, 'matches'])->name('api.live.matches');
    Route::get('/live/statistics', [App\Http\Controllers\Api\LiveUpdatesController::class, 'statistics'])->name('api.live.statistics');
    Route::get('/live/live-matches', [App\Http\Controllers\Api\LiveUpdatesController::class, 'liveMatches'])->name('api.live.live-matches');
    Route::get('/health', [App\Http\Controllers\Api\LiveUpdatesController::class, 'health'])->name('api.health');
});
