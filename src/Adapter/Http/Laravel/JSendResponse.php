<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Laravel;

use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Domain\Type\Arrayable;
use Illuminate\Http\JsonResponse;

/**
 * Class JSendResponse
 */
final class JSendResponse extends JsonResponse
{
    /**
     * Creates a response from its semantic envelope
     *
     * @param JSendEnvelope                  $envelope   Semantic JSend envelope.
     * @param integer                        $statusCode Controller-selected HTTP status.
     * @param array<string, string|string[]> $headers    Controller-selected HTTP headers.
     */
    public static function fromEnvelope(
        JSendEnvelope $envelope,
        int $statusCode,
        array $headers = []
    ): self {
        $response = new self($envelope->toJson(), $statusCode, $headers, 0, true);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Creates a successful response
     *
     * @param Arrayable|null                 $presentation Presented success data.
     * @param integer                        $statusCode   Controller-selected HTTP status.
     * @param array<string, string|string[]> $headers      Controller-selected HTTP headers.
     */
    public static function success(
        ?Arrayable $presentation = null,
        int $statusCode = self::HTTP_OK,
        array $headers = []
    ): self {
        return self::fromEnvelope(JSendEnvelope::success($presentation), $statusCode, $headers);
    }

    /**
     * Creates a failed response
     *
     * @param Arrayable                      $presentation Presented failure data.
     * @param integer                        $statusCode   Controller-selected HTTP status.
     * @param array<string, string|string[]> $headers      Controller-selected HTTP headers.
     */
    public static function fail(
        Arrayable $presentation,
        int $statusCode = self::HTTP_BAD_REQUEST,
        array $headers = []
    ): self {
        return self::fromEnvelope(JSendEnvelope::fail($presentation), $statusCode, $headers);
    }

    /**
     * Creates an error response
     *
     * @param string                         $message      Error message.
     * @param integer                        $statusCode   Controller-selected HTTP status.
     * @param Arrayable|null                 $presentation Optional presented error data.
     * @param integer|null                   $code         Optional application error code.
     * @param array<string, string|string[]> $headers      Controller-selected HTTP headers.
     */
    public static function error(
        string $message,
        int $statusCode = self::HTTP_INTERNAL_SERVER_ERROR,
        ?Arrayable $presentation = null,
        ?int $code = null,
        array $headers = []
    ): self {
        return self::fromEnvelope(
            JSendEnvelope::error($message, $presentation, $code),
            $statusCode,
            $headers
        );
    }
}
