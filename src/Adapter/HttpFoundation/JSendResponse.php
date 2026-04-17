<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpFoundation;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class JSendResponse
 *
 * @link https://github.com/omniti-labs/jsend JSend specification
 */
final class JSendResponse extends JsonResponse
{
    /**
     * Default JSON encoding options
     *
     * Does not escape slashes.
     * Encodes <, >, ', &, and " characters in the JSON, making it also safe to
     * be embedded into HTML.
     * 79 === JSON_UNESCAPED_SLASHES
     *      | JSON_HEX_TAG
     *      | JSON_HEX_APOS
     *      | JSON_HEX_AMP
     *      | JSON_HEX_QUOT
     */
    public const DEFAULT_ENCODING_OPTIONS = 79;
    public const string SUCCESS = 'success';
    public const string FAIL = 'fail';
    public const string ERROR = 'error';

    protected string $statusType;

    /**
     * Creates a success response
     */
    public static function success(
        ?array $data = null,
        int $statusCode = self::HTTP_OK,
        array $headers = [],
        int $options = self::DEFAULT_ENCODING_OPTIONS
    ): static {
        $content = [
            'status' => static::SUCCESS,
            'data'   => $data
        ];

        $response = new static($content, $statusCode, $headers);
        $response->setEncodingOptions($options);
        $response->statusType = static::SUCCESS;

        return $response;
    }

    /**
     * Creates a fail response
     */
    public static function fail(
        ?array $data = null,
        int $statusCode = self::HTTP_BAD_REQUEST,
        array $headers = [],
        int $options = self::DEFAULT_ENCODING_OPTIONS
    ): static {
        $content = [
            'status' => static::FAIL,
            'data'   => $data
        ];

        $response = new static($content, $statusCode, $headers);
        $response->setEncodingOptions($options);
        $response->statusType = static::FAIL;

        return $response;
    }

    /**
     * Creates an error response
     */
    public static function error(
        string $message,
        int $statusCode = self::HTTP_INTERNAL_SERVER_ERROR,
        ?array $data = null,
        ?int $code = null,
        array $headers = [],
        int $options = self::DEFAULT_ENCODING_OPTIONS
    ): static {
        $content = [
            'status'  => static::ERROR,
            'message' => $message
        ];

        if ($data !== null) {
            $content['data'] = $data;
        }

        if ($code !== null) {
            $content['code'] = $code;
        }

        $response = new static($content, $statusCode, $headers);
        $response->setEncodingOptions($options);
        $response->statusType = static::ERROR;

        return $response;
    }

    /**
     * Retrieves response data
     */
    public function getData(bool $array = true, int $depth = 512): mixed
    {
        return json_decode((string) $this->data, $array, $depth);
    }

    /**
     * Checks if status type is success
     */
    public function isSuccess(): bool
    {
        return $this->statusType === static::SUCCESS;
    }

    /**
     * Checks if status type is fail
     */
    public function isFail(): bool
    {
        return $this->statusType === static::FAIL;
    }

    /**
     * Checks if status type is error
     */
    public function isError(): bool
    {
        return $this->statusType === static::ERROR;
    }
}
