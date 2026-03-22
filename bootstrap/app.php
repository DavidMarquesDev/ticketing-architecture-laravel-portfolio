<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

if (!class_exists(Application::class)) {
    throw new RuntimeException('Dependências do Laravel não instaladas. Execute composer install.');
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
    })
    ->create();
