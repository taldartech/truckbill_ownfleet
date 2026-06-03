<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api-routes.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'app/api/*',
            'logout',
        ]);

        $middleware->alias([
            'tenant' => \App\Http\Middleware\ResolveTenant::class,
            'tenant.context' => \App\Http\Middleware\EnsureTenantContext::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);

        $middleware->redirectGuestsTo('/app/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $exception, $request) {
            if (! $request->is('app/api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Uploaded file is too large. Please upload a smaller file.',
                'errors' => [
                    'file' => ['Uploaded file is too large. Please upload a smaller file.'],
                ],
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        });
    })->create();
