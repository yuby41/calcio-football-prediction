<?php

if (file_exists(__DIR__ . '/views_override.php')) {
    require_once __DIR__ . '/views_override.php';
}

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.rate.limit' => \App\Http\Middleware\ApiRateLimitMiddleware::class,
        ]);
    })
    ->withSchedule(function ($schedule) {
        // Import scheduling from Console\Kernel.php
        $kernel = new App\Console\Kernel(app(), app('events'));
        $reflection = new ReflectionClass($kernel);
        $method = $reflection->getMethod('schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
