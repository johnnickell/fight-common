<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class TaskState
 */
final readonly class TaskState
{
    public const int MAX_BYTES = 32768;

    /**
     * Constructs TaskState
     */
    private function __construct(private StrictJson $data)
    {
    }

    /**
     * Creates coherent bounded status-specific data without claiming public safety
     */
    public static function create(
        string $status,
        ?string $message = null,
        ?StrictJson $input = null,
        ?StrictJson $result = null,
        ?StrictJson $error = null
    ): self {
        if (!in_array($status, ['working', 'input_required', 'completed', 'failed', 'cancelled'], true)) {
            throw new DomainException('Invalid Task status.');
        }

        if ($message !== null && (strlen($message) > 1024 || preg_match('//u', $message) !== 1)) {
            throw new DomainException('Invalid Task status message.');
        }

        if (($status === 'input_required') !== ($input !== null)) {
            throw new DomainException('Task input does not match status.');
        }

        if ($input !== null) {
            if (!$input->isObject() || count($input->properties()) < 1 || count($input->properties()) > 16) {
                throw new DomainException('Invalid Task input map.');
            }

            foreach ($input->properties() as $key => $request) {
                if (
                    preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,63}\z/D', (string) $key) !== 1
                    || !$request instanceof StrictJson || !$request->isObject()
                    || !in_array($request->get('type'), ['form', 'confirmation'], true)
                ) {
                    throw new DomainException('Invalid Task input request.');
                }
            }
        }

        if (($status === 'completed') !== ($result !== null) || ($result !== null && !$result->isObject())) {
            throw new DomainException('Task result does not match status.');
        }

        if (($status === 'failed') !== ($error !== null)) {
            throw new DomainException('Task error does not match status.');
        }

        if ($error !== null) {
            if (
                !$error->isObject() || !is_int($error->get('code'))
                || $error->get('code') < -2147483648 || $error->get('code') > 2147483647
                || !is_string($error->get('message')) || $error->get('message') === ''
                || strlen($error->get('message')) > 1024
                || array_diff(array_keys($error->properties()), ['code', 'message', 'data']) !== []
            ) {
                throw new DomainException('Invalid Task execution error.');
            }
        }

        $data = StrictJson::fromObject([
            'status' => $status, 'message' => $message, 'input' => $input, 'result' => $result, 'error' => $error
        ], 32);
        if (strlen($data->toString()) > self::MAX_BYTES) {
            throw new DomainException('Task state exceeds the encoded byte limit.');
        }

        return new self($data);
    }

    /**
     * Creates validated state from persisted or message data
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (
            count($data) !== 5
            || array_diff(['status', 'message', 'input', 'result', 'error'], array_keys($data)) !== []
        ) {
            throw new DomainException('Invalid Task state representation.');
        }

        return self::create(
            $data['status'],
            $data['message'],
            $data['input'] === null ? null : StrictJson::fromData($data['input']),
            $data['result'] === null ? null : StrictJson::fromData($data['result']),
            $data['error'] === null ? null : StrictJson::fromData($data['error'])
        );
    }

    /**
     * Returns the lifecycle status
     */
    public function status(): string
    {
        return $this->data->get('status');
    }

    /**
     * Returns whether the outcome cannot be replaced
     */
    public function isTerminal(): bool
    {
        return in_array($this->status(), ['completed', 'failed', 'cancelled'], true);
    }

    /**
     * Returns immutable complete state for trusted validation and admitted projection
     */
    public function data(): StrictJson
    {
        return $this->data;
    }

    /**
     * Returns the protected message representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data->properties();
    }
}
