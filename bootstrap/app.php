<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
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
            'permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);

        $middleware->priority([
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\ResolveTenant::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->redirectGuestsTo('/app/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $exception, $request) {
            if (! $request->is('app/api/*') && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Uploaded file is too large. Please upload a smaller file.',
                'errors' => [
                    'file' => ['Uploaded file is too large. Please upload a smaller file.'],
                ],
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('app/api/*') && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'This action is unauthorized.',
            ], Response::HTTP_FORBIDDEN);
        });
    })->create();
