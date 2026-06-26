<?php

namespace App\Helpers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiResponse
{
    public static function isApiRequest(Request $request): bool
    {
        return $request->is('app/api/*') || $request->is('api/*');
    }

    public static function error(
        Throwable $exception,
        string $message = 'Unable to save. Please try again.',
        int $status = Response::HTTP_INTERNAL_SERVER_ERROR,
        ?Request $request = null,
    ): JsonResponse {
        $traceId = self::traceId();

        Log::error($message, self::logContext($exception, $traceId, $request));

        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (config('app.debug')) {
            $payload['debug'] = self::debugPayload($exception, $traceId);
        }

        return response()->json($payload, $status);
    }

    public static function fromQueryException(QueryException $exception, ?Request $request = null): JsonResponse
    {
        $message = 'Unable to save. Please try again.';

        if (str_contains($exception->getMessage(), 'Column not found')) {
            $message = 'Database update required. Run: php artisan migrate';
        }

        return self::error($exception, $message, Response::HTTP_INTERNAL_SERVER_ERROR, $request);
    }

    private static function traceId(): string
    {
        return substr(str_replace('-', '', (string) Str::uuid()), 0, 8);
    }

    /**
     * @return array<string, mixed>
     */
    private static function logContext(Throwable $exception, string $traceId, ?Request $request): array
    {
        return array_filter([
            'trace_id' => $traceId,
            'exception' => class_basename($exception),
            'message' => $exception->getMessage(),
            'type' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'method' => self::resolveMethod($exception),
            'user_id' => auth()->id(),
            'url' => $request?->fullUrl(),
            'payload' => $request?->except(['password', 'password_confirmation', '_token']),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array{
     *     exception: string,
     *     message: string,
     *     file: string,
     *     line: int,
     *     method: string|null,
     *     trace_id: string
     * }
     */
    private static function debugPayload(Throwable $exception, string $traceId): array
    {
        return [
            'exception' => class_basename($exception),
            'message' => $exception->getMessage(),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
            'method' => self::resolveMethod($exception),
            'trace_id' => $traceId,
        ];
    }

    private static function resolveMethod(Throwable $exception): ?string
    {
        foreach ($exception->getTrace() as $frame) {
            if (! isset($frame['class'], $frame['function'])) {
                continue;
            }

            if (! str_starts_with($frame['class'], 'App\\')) {
                continue;
            }

            return $frame['class'].'::'.$frame['function'];
        }

        $action = Route::currentRouteAction();

        if (is_string($action) && str_contains($action, '@')) {
            return $action;
        }

        return null;
    }
}
