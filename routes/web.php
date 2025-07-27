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
