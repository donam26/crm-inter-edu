<?php

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
        // Trust the host reverse proxy (nginx -> 127.0.0.1:8000) so X-Forwarded-Proto
        // is honoured and Laravel generates https:// URLs behind TLS termination.
        $middleware->trustProxies(at: '*');

        // Đặt team context (branch_id) cho Spatie permission trên mọi web request.
        $middleware->web(append: [
            \App\Http\Middleware\SetPermissionsTeamFromBranch::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
