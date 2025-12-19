<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process queue in safe batches every minute to prevent overlaps and long processes
// This is critical for shared hosting (Hostinger/hPanel) reliability
\Illuminate\Support\Facades\Schedule::command('queue:work database --stop-when-empty --tries=3 --timeout=300')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

\Illuminate\Support\Facades\Schedule::job(new \App\Jobs\MasterRefreshJob)->daily();
