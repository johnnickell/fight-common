<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Error;

use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\InvalidInput;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Error\Exception\PreconditionFailed;
use Fight\Common\Application\Error\Exception\RateLimitExceeded;
use Fight\Common\Application\Error\Exception\ResourceGone;
use Fight\Common\Application\Error\Exception\ResourceNotFound;
use Fight\Common\Application\Error\Exception\RuleViolation;
use Fight\Common\Application\Error\Exception\ServerError;
use Fight\Common\Application\Error\Exception\ServiceUnavailable;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Common\Domain\Type\Arrayable;
use InvalidArgumentException;
use Throwable;

/**
 * Class SafeHttpErrorPolicy
 *
 * Shared HTTP presentation policy for explicitly selected safe adapters.
 */
final readonly class SafeHttpErrorPolicy
{
    /**
     * Constructs SafeHttpErrorPolicy
     *
     * Neutral application retry hints are not HTTP permissions by default.
     */
    public function __construct(private bool $publishApplicationRetryHints = false)
    {
    }

    /**
     * Classifies a declared failure without inspecting codes, text or causes
     */
    public function status(Throwable $failure): int
    {
        return match (true) {
            $failure instanceof SafeHttpFailure => $failure->status(),
            $failure instanceof AuthenticationRequired => 401,
            $failure instanceof PermissionDenied => 403,
            $failure instanceof ResourceNotFound => 404,
            $failure instanceof StateConflict => 409,
            $failure instanceof ResourceGone => 410,
            $failure instanceof PreconditionFailed => 412,
            $failure instanceof InvalidInput, $failure instanceof ValidationException => 400,
            $failure instanceof RuleViolation => 422,
            $failure instanceof RateLimitExceeded => 429,
            $failure instanceof ServiceUnavailable => 503,
            $failure instanceof ServerError => 500,
            default => 500,
        };
    }

    /**
     * Creates an encoded-safe semantic description for a classified failure
     *
     * @return array{int, JSendEnvelope, array<string, string>}
     */
    public function present(Throwable $failure, ?SafeHttpPresentation $projection = null): array
    {
        $status = $this->status($failure);
        $message = $this->defaultMessage($status);
        if ($projection !== null && $projection->message() !== null) {
            $message = $projection->message();
        }

        $data = $projection?->data();
        $metadata = $projection?->metadata();
        if ($metadata === null && $failure instanceof SafeHttpFailure) {
            $metadata = $failure->metadata();
        }

        $headers = $this->headers($status, $metadata, $failure);
        if ($status >= 500) {
            $publicData = $data !== null ? $this->presentation($data) : null;

            return [$status, JSendEnvelope::error($message, $publicData), $headers];
        }

        return [$status, JSendEnvelope::fail($this->presentation($data ?? ['message' => $message])), $headers];
    }

    /**
     * Returns a generic server response independent of an untrusted projector
     *
     * @return array{int, JSendEnvelope, array<string, string>}
     */
    public function fallback(): array
    {
        return [500, JSendEnvelope::error('Internal server error.'), []];
    }

    /**
     * Returns one fixed category-safe message
     */
    private function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Invalid input.',
            401 => 'Authentication required.',
            403 => 'Permission denied.',
            404 => 'Resource not found.',
            405 => 'Method not allowed.',
            406 => 'Not acceptable.',
            409 => 'State conflict.',
            410 => 'Resource gone.',
            412 => 'Precondition failed.',
            413 => 'Request too large.',
            415 => 'Unsupported media type.',
            422 => 'Rule violation.',
            429 => 'Too many requests.',
            503 => 'Service unavailable.',
            default => 'Internal server error.',
        };
    }

    /**
     * Returns only valid protocol metadata for the classified outcome
     *
     * @return array<string, string>
     */
    private function headers(int $status, ?SafeHttpMetadata $metadata, Throwable $failure): array
    {
        $challenge = $metadata?->challenge();
        $allow = $metadata?->allow();
        $retry = $metadata?->retryAfterSeconds();

        if (
            $challenge !== null && $status !== 401 || $allow !== null && $status !== 405 ||
            $retry !== null && $status !== 429 && $status !== 503
        ) {
            throw new InvalidArgumentException('HTTP metadata does not apply to this failure.');
        }

        if ($status === 401 && $challenge === null || $status === 405 && $allow === null) {
            throw new InvalidArgumentException('Required HTTP metadata is missing.');
        }

        if ($retry === null && $this->publishApplicationRetryHints) {
            if ($failure instanceof RateLimitExceeded || $failure instanceof ServiceUnavailable) {
                $retry = $failure->retryDelaySeconds();
            }
        }

        $headers = [];
        if ($challenge !== null) {
            $headers['WWW-Authenticate'] = $challenge;
        }

        if ($allow !== null) {
            $headers['Allow'] = $allow;
        }

        if ($retry !== null) {
            $headers['Retry-After'] = (string) $retry;
        }

        return $headers;
    }

    /**
     * Wraps already-validated plain presentation data for the JSend boundary
     *
     * @param array<array-key, mixed> $data
     */
    private function presentation(array $data): Arrayable
    {
        return new readonly class ($data) implements Arrayable {
            /**
             * @param array<array-key, mixed> $data
             */
            public function __construct(private array $data)
            {
            }

            /**
             * Returns the public-only data
             *
             * @return array<array-key, mixed>
             */
            public function toArray(): array
            {
                return $this->data;
            }
        };
    }
}
