<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureRole;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Consistent JSON error format for the API, never a stack trace.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['business_rule' => [$e->getMessage()]],
                ], 422);
            }

            return back()->withInput()->withErrors(['business_rule' => $e->getMessage()]);
        });

        $notFound = fn (Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Resource not found.'], 404)
            : null;

        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => $notFound($request));
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $notFound($request));
    })->create();
