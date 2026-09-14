<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SlidingTokenExpiry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\SetCacheHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'       => CheckRole::class,
            'permission' => CheckPermission::class,
            'active'     => EnsureUserIsActive::class,
            // Lets a route opt into ETag/Cache-Control revalidation, e.g.
            // `cache.headers:public;max_age=300;etag` on /config.
            'cache.headers' => SetCacheHeaders::class,
        ]);
        $middleware->appendToGroup('api', SlidingTokenExpiry::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                $model = class_basename($e->getModel());

                return response()->json(['message' => "{$model} not found."], 404);
            }
        });
    })->create();
