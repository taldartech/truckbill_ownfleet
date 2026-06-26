<?php

use App\Helpers\ApiResponse;
use App\Support\ApiQueryExceptionMapper;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
            'branch' => \App\Http\Middleware\ResolveBranch::class,
            'tenant.context' => \App\Http\Middleware\EnsureTenantContext::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'onboarding.signature' => \App\Http\Middleware\VerifyOnboardingSignature::class,
        ]);

        $middleware->priority([
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\ResolveTenant::class,
            \App\Http\Middleware\ResolveBranch::class,
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

        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! ApiResponse::isApiRequest($request)) {
                return null;
            }

            $integrityResponse = ApiQueryExceptionMapper::toValidationResponse($exception);

            if ($integrityResponse !== null) {
                return $integrityResponse;
            }

            return ApiResponse::fromQueryException($exception, $request);
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! ApiResponse::isApiRequest($request)) {
                return null;
            }

            if (
                $exception instanceof ValidationException
                || $exception instanceof HttpException
                || $exception instanceof AuthorizationException
                || $exception instanceof QueryException
                || $exception instanceof PostTooLargeException
            ) {
                return null;
            }

            return ApiResponse::error($exception, request: $request);
        });
    })->create();
