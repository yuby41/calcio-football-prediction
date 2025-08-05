<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Note: All scheduling is now handled in app/Console/Kernel.php
// This file should only contain Artisan command definitions, not schedules
