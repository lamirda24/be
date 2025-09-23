<?php

use App\Http\Middleware\CustomCors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors; // <-- add

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {
        // Make CORS apply to requests (preflight + actual)
        $middleware->append(CustomCors::class);
        // If you prefer only for API group instead:
        // $middleware->appendToGroup('api', HandleCors::class);
    })->withCommands([
        \App\Console\Commands\ImportKemendikdasmenFiles::class, // <= DAFTARKAN DI SINI
    ])
    ->withExceptions(function ($exceptions) {
        //
    })->create();
