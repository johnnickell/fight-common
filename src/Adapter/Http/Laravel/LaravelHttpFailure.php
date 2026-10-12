<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Laravel;

use Fight\Common\Adapter\Http\Error\SafeHttpFailure;
use Fight\Common\Adapter\Http\Error\SafeHttpMetadata;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Throwable;

/**
 * Class LaravelHttpFailure
 *
 * Maps declared framework intent without running Laravel's broad exception preparation.
 */
final class LaravelHttpFailure
{
    /**
     * Returns a shared failure for a supported native Laravel/HTTP exception
     */
    public static function translate(Throwable $failure): Throwable
    {
        if ($failure instanceof AuthenticationException) {
            return SafeHttpFailure::fromNative(401);
        }

        if ($failure instanceof AuthorizationException) {
            if ($failure->hasStatus() && $failure->status() !== 403 && $failure->status() !== 404) {
                return $failure;
            }

            return SafeHttpFailure::fromNative($failure->status() ?? 403);
        }

        // Laravel's validation status is mutable; only its documented 422 is supported.
        if ($failure instanceof ValidationException) {
            return $failure->status === 422 ? SafeHttpFailure::fromNative(422) : $failure;
        }

        if (!$failure instanceof HttpExceptionInterface) {
            return $failure;
        }

        $status = $failure->getStatusCode();
        $expected = match (true) {
            $failure instanceof BadRequestHttpException => 400,
            $failure instanceof UnauthorizedHttpException => 401,
            $failure instanceof AccessDeniedHttpException => 403,
            $failure instanceof NotFoundHttpException => 404,
            $failure instanceof MethodNotAllowedHttpException => 405,
            $failure instanceof NotAcceptableHttpException => 406,
            $failure instanceof ConflictHttpException => 409,
            $failure instanceof GoneHttpException => 410,
            $failure instanceof PreconditionFailedHttpException => 412,
            $failure instanceof UnsupportedMediaTypeHttpException => 415,
            $failure instanceof UnprocessableEntityHttpException => 422,
            $failure instanceof ThrottleRequestsException, $failure instanceof TooManyRequestsHttpException => 429,
            $failure instanceof PostTooLargeException => 413,
            $failure instanceof ServiceUnavailableHttpException => 503,
            $failure::class === HttpException::class => $status,
            default => 0,
        };
        if (
            $status !== $expected || !in_array(
                $status,
                [400, 401, 403, 404, 405, 406, 409, 410, 412, 413, 415, 422, 429, 502, 503, 504],
                true
            )
        ) {
            return $failure;
        }

        $headers = $failure->getHeaders();
        $retry = null;
        if ($status === 429 || $status === 503) {
            $retry = self::seconds(self::header($headers, 'Retry-After'));
        }

        $metadata = new SafeHttpMetadata(
            challenge: $status === 401 ? self::header($headers, 'WWW-Authenticate') : null,
            allow: $status === 405 ? self::header($headers, 'Allow') : null,
            retryAfterSeconds: $retry
        );

        return SafeHttpFailure::fromNative($status, $metadata);
    }

    /**
     * Returns one scalar header without combining ambiguous duplicates
     *
     * @param array<string, mixed> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        $value = null;
        foreach ($headers as $key => $candidate) {
            if (strcasecmp($key, $name) !== 0) {
                continue;
            }

            if ($value !== null || !is_string($candidate)) {
                throw new InvalidArgumentException('Invalid native HTTP metadata.');
            }

            $value = $candidate;
        }

        return $value;
    }

    /**
     * Parses a canonical nonnegative Retry-After delta without integer overflow
     */
    private static function seconds(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $max = (string) PHP_INT_MAX;
        if (
            preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) !== 1 ||
            strlen($value) > strlen($max) ||
            strlen($value) === strlen($max) && strcmp($value, $max) > 0
        ) {
            throw new InvalidArgumentException('Invalid native Retry-After.');
        }

        return (int) $value;
    }
}
