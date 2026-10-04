<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool\Interaction;

use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use SensitiveParameter;
use Throwable;

/**
 * Class McpToolInteraction
 */
final readonly class McpToolInteraction
{
    /**
     * Constructs McpToolInteraction
     *
     * The consumer supplies a stable neutral caller binding, never a principal or credential.
     * Each endpoint audience should use a separate key ring or an audience-qualified caller binding.
     */
    public function __construct(
        private McpStateProtector $protector,
        #[SensitiveParameter] private string $caller,
        private int $ttl = 300,
        private ?McpConfirmationStore $confirmations = null
    ) {
        if ($caller === '' || strlen($caller) > 1024 || $ttl < 1 || $ttl > 900) {
            throw new DomainException('Interactions require a bounded caller identity and a TTL of 1-900 seconds.');
        }

        StrictJson::fromData($caller);
    }

    /**
     * Creates protected input state and registers consumer-designated one-time confirmations
     *
     * @internal Used only after capability gating and selected Tool execution
     */
    public function issue(McpInputRequired $input, ApplicationData $validated, McpRequest $request): McpResult
    {
        if ($input->requiresConfirmation() && $this->confirmations === null) {
            throw new DomainException('Confirmation requires an atomic interaction store.');
        }

        $contracts = array_map(static fn(McpInputRequest $form): StrictJson => $form->state(), $input->requests());
        $state = StrictJson::fromObject([
            'mode'      => $input->requiresConfirmation() ? 'confirmation' : 'ordinary',
            'method'    => 'tools/call',
            'caller'    => $this->caller,
            'tool'      => $request->parameters()['name'],
            'arguments' => self::arguments($request),
            'validated' => StrictJson::fromObject($validated->toArray()),
            'contracts' => StrictJson::fromObject($contracts),
            'expires'   => time() + $this->ttl,
            'id'        => $request->id()
        ], 70);
        if ($input->requiresConfirmation()) {
            $state = $state->with('confirmation', bin2hex(random_bytes(32)), 70);
        }

        $plaintext = self::canonical($state);
        $token = $this->protector->seal($plaintext);
        $requests = array_map(static fn(McpInputRequest $form): StrictJson => $form->request(), $input->requests());
        $result = McpResult::inputRequired(StrictJson::fromObject($requests), $token);
        if ($input->requiresConfirmation()) {
            $this->confirmations->issue(
                $state->get('confirmation'),
                hash('sha256', $plaintext),
                $state->get('expires')
            );
        }

        return $result;
    }

    /**
     * Opens state privately only far enough to locate the candidate Tool
     *
     * @internal The invoker must recheck current availability before calling restore
     */
    public function open(mixed $token): StrictJson
    {
        try {
            if (!is_string($token) || strlen($token) > McpStateProtector::MAX_STATE_BYTES) {
                throw new DomainException('Invalid interaction state.');
            }

            $state = StrictJson::fromString($this->protector->open($token), 70);
            if (!$state->isObject() || !is_string($state->get('tool'))) {
                throw new DomainException('Invalid interaction state.');
            }

            return $state;
        } catch (DomainException) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }
    }

    /**
     * Restores bound input and validates responses only after current Tool availability
     *
     * @internal
     *
     * @return array{ApplicationData, McpInputResponses}
     */
    public function restore(StrictJson $state, McpRequest $request): array
    {
        try {
            if (
                !in_array($state->get('mode'), ['ordinary', 'confirmation'], true)
                || ($state->get('mode') === 'confirmation' && (
                    $this->confirmations === null || !is_string($state->get('confirmation'))
                    || preg_match('/^[a-f0-9]{64}$/D', $state->get('confirmation')) !== 1
                ))
                || $state->get('method') !== 'tools/call'
                || $state->get('caller') !== $this->caller || $state->get('tool') !== $request->parameters()['name']
                || !is_int($state->get('expires')) || $state->get('expires') <= time()
                || $state->get('id') === $request->id()
                || self::canonical($state->get('arguments')) !== self::canonical(self::arguments($request))
                || !$state->get('validated') instanceof StrictJson || !$state->get('contracts') instanceof StrictJson
            ) {
                throw new DomainException('Invalid interaction state.');
            }

            $forms = array_map(McpInputRequest::restore(...), $state->get('contracts')->properties());
            $input = McpInputRequired::fromRequests($forms);
            $responses = McpInputResponses::validate(
                $request->parameters()['inputResponses'] ?? null,
                $input->requests()
            );

            return [new ApplicationData($state->get('validated')->properties()), $responses];
        } catch (Throwable) {
            // State and response details share one public rejection; never reflect protected values or rule errors.
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }
    }

    /**
     * Acquires validated confirmation state before any resume or terminal refusal
     *
     * @internal Called only after restore and current availability; ordinary state never touches the store
     */
    public function consume(StrictJson $state, McpInputResponses $responses): ?McpResult
    {
        if ($state->get('mode') !== 'confirmation') {
            return null;
        }

        try {
            $outcome = $this->confirmations?->consume(
                $state->get('confirmation'),
                hash('sha256', self::canonical($state)),
                $state->get('expires')
            );
            if ($outcome !== McpConfirmationOutcome::CONSUMED) {
                throw new DomainException('Confirmation could not be acquired.');
            }
        } catch (Throwable) {
            // An uncertain write is terminal: never retry or restore potentially consumed state.
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }

        $refused = array_any(
            $responses->toArray(),
            static fn(McpInputResponse $value): bool => $value->action() !== 'accept'
        );
        if ($refused) {
            return McpResult::complete([
                'content' => [['type' => 'text', 'text' => 'Confirmation not accepted.']]
            ]);
        }

        return null;
    }

    /**
     * Returns original arguments with the same omitted-object default as ordinary invocation
     */
    private static function arguments(McpRequest $request): StrictJson
    {
        $parameters = $request->parameters();

        return StrictJson::fromData(
            array_key_exists('arguments', $parameters) ? $parameters['arguments'] : StrictJson::fromObject()
        );
    }

    /**
     * Returns deterministic JSON preserving object keys, list order and scalar types
     */
    private static function canonical(mixed $value): string
    {
        return StrictJson::fromData(self::ordered($value), 70)->toString();
    }

    /**
     * Returns an immutable tree with bytewise ordered object properties
     */
    private static function ordered(mixed $value): mixed
    {
        if ($value instanceof StrictJson) {
            if (!$value->isObject()) {
                return self::ordered($value->toData());
            }

            $properties = $value->properties();
            ksort($properties, SORT_STRING);

            return StrictJson::fromObject(array_map(self::ordered(...), $properties), 70);
        }

        return is_array($value) ? array_map(self::ordered(...), $value) : $value;
    }
}
