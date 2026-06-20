<?php

declare(strict_types=1);

namespace ZkTeco\Laravel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ZkTeco\Exceptions\ZkException;

/**
 * Turns a {@see ZkException} into a localised JSON error for API-style requests.
 *
 * Registered as a renderable callback by {@see ZkTecoServiceProvider}. Non-JSON
 * requests return null so Laravel's default exception handling (debug page,
 * error views) takes over unchanged.
 */
final class ZkExceptionRenderer
{
    public function render(ZkException $exception, Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        return new JsonResponse([
            'message' => $this->message($exception),
            'error_code' => $exception->errorCode->value,
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * The localised message for the active locale, falling back to the
     * exception's English default when no translation is published.
     */
    public function message(ZkException $exception): string
    {
        $key = 'zkteco::errors.'.$exception->errorCode->value;
        $message = trans($key, $exception->context);

        return is_string($message) && $message !== $key ? $message : $exception->getMessage();
    }
}
