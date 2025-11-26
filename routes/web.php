<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\StatisticsController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/manual-update', [HomeController::class, 'manualUpdate'])->name('home.manual-update');
Route::get('/api/live-data', [HomeController::class, 'getLiveData'])->name('api.live-data')->middleware('api.rate.limit:30,1');
Route::get('/matches', [MatchController::class, 'index'])->name('matches.index');
Route::get('/matches/{footballMatch}', [MatchController::class, 'show'])->name('matches.show');
Route::get('/api/matches/filtered', [MatchController::class, 'getFilteredMatches'])->name('api.matches.filtered')->middleware('api.rate.limit:60,1');

// Statistics routes
Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');
Route::get('/statistics/chart-data', [StatisticsController::class, 'chartData'])->name('statistics.chart-data');
Route::get('/statistics/league-data', [StatisticsController::class, 'leagueData'])->name('statistics.league-data');
Route::get('/statistics/detail-data', [StatisticsController::class, 'detailData'])->name('statistics.detail-data');
Route::get('/statistics/refresh', [StatisticsController::class, 'refresh'])->name('statistics.refresh');
Route::get('/statistics/debug', [StatisticsController::class, 'debugStats'])->name('statistics.debug');
Route::get('/statistics/debug-view', [StatisticsController::class, 'debugView'])->name('statistics.debug-view');
Route::get('/statistics/card-values', [StatisticsController::class, 'cardValues'])->name('statistics.card-values');
Route::get('/debug/match/{matchId}', [HomeController::class, 'debugMatch'])->name('debug.match');

// Budget Management routes
Route::prefix('budget')->name('budget.')->group(function () {
    Route::get('/', [App\Http\Controllers\BudgetController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\BudgetController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\BudgetController::class, 'store'])->name('store');
    Route::get('/{budget}', [App\Http\Controllers\BudgetController::class, 'show'])->name('show');
    Route::get('/{budget}/edit', [App\Http\Controllers\BudgetController::class, 'edit'])->name('edit');
    Route::put('/{budget}', [App\Http\Controllers\BudgetController::class, 'update'])->name('update');
    Route::delete('/{budget}', [App\Http\Controllers\BudgetController::class, 'destroy'])->name('destroy');
    Route::get('/{budget}/recommendations', [App\Http\Controllers\BudgetController::class, 'recommendations'])->name('recommendations');
    Route::post('/{budget}/bet', [App\Http\Controllers\BudgetController::class, 'placeBet'])->name('place-bet');
    Route::post('/{budget}/resolve', [App\Http\Controllers\BudgetController::class, 'resolveBets'])->name('resolve-bets');
    Route::get('/{budget}/chart-data', [App\Http\Controllers\BudgetController::class, 'chartData'])->name('chart-data');
    Route::get('/{budget}/opportunities', [App\Http\Controllers\BudgetController::class, 'opportunities'])->name('opportunities');
    Route::delete('/{budget}/delete-bet/{bet}', [App\Http\Controllers\BudgetController::class, 'deleteBet'])->name('delete-bet');
    Route::delete('/{budget}/delete-multiple-bets', [App\Http\Controllers\BudgetController::class, 'deleteMultipleBets'])->name('delete-multiple-bets');
    Route::get('/{budget}/check-integrity', [App\Http\Controllers\BudgetController::class, 'checkIntegrity'])->name('check-integrity');
    Route::post('/{budget}/recalculate-history', [App\Http\Controllers\BudgetController::class, 'recalculateHistory'])->name('recalculate-history');
});

// API Routes for live updates
Route::prefix('api')->group(function () {
    Route::get('/live/matches', [App\Http\Controllers\Api\LiveUpdatesController::class, 'matches'])->name('api.live.matches');
    Route::get('/live/statistics', [App\Http\Controllers\Api\LiveUpdatesController::class, 'statistics'])->name('api.live.statistics');
    Route::get('/live/live-matches', [App\Http\Controllers\Api\LiveUpdatesController::class, 'liveMatches'])->name('api.live.live-matches');
    Route::get('/health', [App\Http\Controllers\Api\LiveUpdatesController::class, 'health'])->name('api.health');
});