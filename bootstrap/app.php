<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$runtimeDirs = [
    __DIR__ . '/../storage/framework',
    __DIR__ . '/../storage/framework/sessions',
    __DIR__ . '/../storage/framework/views',
    __DIR__ . '/../storage/framework/cache',
    __DIR__ . '/../storage/framework/cache/data',
    __DIR__ . '/../storage/framework/testing',
    __DIR__ . '/../storage/logs',
    __DIR__ . '/../bootstrap/cache',
];

foreach ($runtimeDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
        $middleware->alias([
            'admin.superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if (!$request->expectsJson() && in_array('admin', $e->guards())) {
                return redirect()->guest(route('admin.login'));
            }
        });
    })->create();
