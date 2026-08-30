<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\CodeIgniter;

use CodeIgniter\HTTP\ResponseInterface;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Domain\Type\Arrayable;

/**
 * Class JSendResponse
 */
final class JSendResponse
{
    /**
     * Applies an envelope to a native response
     *
     * @param ResponseInterface             $response   Caller-owned native response.
     * @param JSendEnvelope                  $envelope   Semantic envelope.
     * @param integer                        $statusCode Selected HTTP status.
     * @param array<string, string|string[]> $headers
     */
    public static function fromEnvelope(
        ResponseInterface $response,
        JSendEnvelope $envelope,
        int $statusCode,
        array $headers = []
    ): ResponseInterface {
        $json = $envelope->toJson();
        $response->setStatusCode($statusCode);

        foreach ($headers as $name => $value) {
            $response->setHeader($name, $value);
        }

        return $response->setJSON($json, true);
    }

    /**
     * Creates a successful native response
     *
     * @param ResponseInterface             $response     Caller-owned native response.
     * @param Arrayable|null                 $presentation Success presentation.
     * @param integer                        $statusCode   Selected HTTP status.
     * @param array<string, string|string[]> $headers
     */
    public static function success(
        ResponseInterface $response,
        ?Arrayable $presentation = null,
        int $statusCode = ResponseInterface::HTTP_OK,
        array $headers = []
    ): ResponseInterface {
        return self::fromEnvelope($response, JSendEnvelope::success($presentation), $statusCode, $headers);
    }

    /**
     * Creates a failed native response
     *
     * @param ResponseInterface             $response     Caller-owned native response.
     * @param Arrayable                      $presentation Failure presentation.
     * @param integer                        $statusCode   Selected HTTP status.
     * @param array<string, string|string[]> $headers
     */
    public static function fail(
        ResponseInterface $response,
        Arrayable $presentation,
        int $statusCode = ResponseInterface::HTTP_BAD_REQUEST,
        array $headers = []
    ): ResponseInterface {
        return self::fromEnvelope($response, JSendEnvelope::fail($presentation), $statusCode, $headers);
    }

    /**
     * Creates an error native response
     *
     * @param ResponseInterface             $response     Caller-owned native response.
     * @param string                        $message      Error message.
     * @param integer                        $statusCode   Selected HTTP status.
     * @param Arrayable|null                 $presentation Error presentation.
     * @param integer|null                   $code         Application error code.
     * @param array<string, string|string[]> $headers
     */
    public static function error(
        ResponseInterface $response,
        string $message,
        int $statusCode = ResponseInterface::HTTP_INTERNAL_SERVER_ERROR,
        ?Arrayable $presentation = null,
        ?int $code = null,
        array $headers = []
    ): ResponseInterface {
        return self::fromEnvelope(
            $response,
            JSendEnvelope::error($message, $presentation, $code),
            $statusCode,
            $headers
        );
    }
}
