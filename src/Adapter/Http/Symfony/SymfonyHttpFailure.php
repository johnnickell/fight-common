<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Symfony;

use Fight\Common\Adapter\Http\Error\SafeHttpFailure;
use Fight\Common\Adapter\Http\Error\SafeHttpMetadata;
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
 * Class SymfonyHttpFailure
 *
 * Translates only deliberate HTTP exceptions; no exception text or arbitrary headers.
 */
final class SymfonyHttpFailure
{
    /**
     * Returns a shared failure for a supported native HTTP exception
     */
    public static function translate(Throwable $failure): Throwable
    {
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
            $failure instanceof TooManyRequestsHttpException => 429,
            $failure instanceof ServiceUnavailableHttpException => 503,
            $failure::class === HttpException::class => $status,
            default => 0,
        };
        if ($status !== $expected) {
            return $failure;
        }

        $headers = $failure->getHeaders();
        $challenge = self::header($headers, 'WWW-Authenticate');
        $allow = self::header($headers, 'Allow');
        $retry = self::header($headers, 'Retry-After');
        $metadata = new SafeHttpMetadata(
            challenge: $status === 401 ? $challenge : null,
            allow: $status === 405 ? $allow : null,
            retryAfterSeconds: $status === 429 || $status === 503 ? self::seconds($retry) : null
        );

        return SafeHttpFailure::fromNative($status, $metadata);
    }

    /**
     * Returns one scalar header without merging duplicate or injected values
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
     * Parses a nonnegative integer Retry-After without casting overflow or HTTP dates
     */
    private static function seconds(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (
            preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) !== 1 ||
            strlen($value) > strlen((string) PHP_INT_MAX) ||
            strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) > 0
        ) {
            throw new InvalidArgumentException('Invalid native Retry-After.');
        }

        return (int) $value;
    }
}
