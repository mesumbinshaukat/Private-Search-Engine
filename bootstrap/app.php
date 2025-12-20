<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\AuthorizeGoogleDriveCommand::class,
        \App\Console\Commands\ClearQueueCommand::class,
        \App\Console\Commands\CrawlCategoryCommand::class,
        \App\Console\Commands\CrawlDailyCommand::class,
        \App\Console\Commands\GenerateIndexCommand::class,
        \App\Console\Commands\MasterRefreshCommand::class,
        \App\Console\Commands\MigrateParsedRecordsCommand::class,
        \App\Console\Commands\MonitorCommand::class,
        \App\Console\Commands\QueueStatusCommand::class,
        \App\Console\Commands\RefreshCacheCommand::class,
        \App\Console\Commands\ScheduleCrawlCommand::class,
        \App\Console\Commands\UploadIndexCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'master_key' => \App\Http\Middleware\ApiMasterKeyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
