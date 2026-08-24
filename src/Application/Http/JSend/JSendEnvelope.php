<?php

declare(strict_types=1);

namespace Fight\Common\Application\Http\JSend;

use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Type\Arrayable;
use InvalidArgumentException;
use JsonException;

/**
 * Class JSendEnvelope
 */
final readonly class JSendEnvelope implements Arrayable
{
    public const int DEFAULT_ENCODING_OPTIONS = 79;

    /**
     * Constructs JSendEnvelope
     *
     * @param JSendStatus                  $status  Semantic JSend status.
     * @param array<array-key, mixed>|null $data
     * @param string|null                  $message Error message.
     * @param integer|null                 $code    Application-specific error code.
     */
    private function __construct(
        private JSendStatus $status,
        private ?array $data = null,
        private ?string $message = null,
        private ?int $code = null
    ) {
    }

    /**
     * Creates a successful envelope
     */
    public static function success(?Arrayable $presentation = null): self
    {
        return new self(
            JSendStatus::SUCCESS,
            $presentation instanceof Arrayable ? self::present($presentation) : null
        );
    }

    /**
     * Creates a failed envelope
     */
    public static function fail(Arrayable $presentation): self
    {
        return new self(JSendStatus::FAIL, self::present($presentation));
    }

    /**
     * Creates an error envelope
     */
    public static function error(string $message, ?Arrayable $presentation = null, ?int $code = null): self
    {
        return new self(
            JSendStatus::ERROR,
            $presentation instanceof Arrayable ? self::present($presentation) : null,
            $message,
            $code
        );
    }

    /**
     * Retrieves the semantic JSend representation
     *
     * @return array{status: 'success'|'fail', data: array<array-key, mixed>|null}|array{
     *     status: 'error',
     *     message: string,
     *     data?: array<array-key, mixed>,
     *     code?: int
     * }
     */
    public function toArray(): array
    {
        if ($this->status === JSendStatus::ERROR) {
            $result = [
                'status'  => $this->status->value,
                'message' => $this->message
            ];

            if ($this->data !== null) {
                $result['data'] = $this->data;
            }

            if ($this->code !== null) {
                $result['code'] = $this->code;
            }

            return $result;
        }

        return [
            'status' => $this->status->value,
            'data'   => $this->data
        ];
    }

    /**
     * Encodes the semantic JSend representation
     *
     * @throws JsonException When the representation cannot be encoded
     */
    public function toJson(): string
    {
        return json_encode(
            $this->toArray(),
            self::DEFAULT_ENCODING_OPTIONS | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Projects presentation data into its semantic JSend representation
     *
     * @return array<array-key, mixed>
     */
    private static function present(Arrayable $presentation): array
    {
        if (!$presentation instanceof ResultSet) {
            return $presentation->toArray();
        }

        $records = [];

        foreach ($presentation->records() as $record) {
            if (!$record instanceof Arrayable) {
                throw new InvalidArgumentException('Every ResultSet record must implement Arrayable.');
            }

            $records[] = $record->toArray();
        }

        return [
            'page'          => $presentation->page(),
            'per_page'      => $presentation->perPage(),
            'total_pages'   => $presentation->totalPages(),
            'total_records' => $presentation->totalRecords(),
            'records'       => $records
        ];
    }
}
