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
        private int $ttl = 300
    ) {
        if ($caller === '' || strlen($caller) > 1024 || $ttl < 1 || $ttl > 900) {
            throw new DomainException('Interactions require a bounded caller identity and a TTL of 1-900 seconds.');
        }

        StrictJson::fromData($caller);
    }

    /**
     * Creates a stateless ordinary input result with protected original validated arguments
     *
     * @internal Used only after capability gating and selected Tool execution
     */
    public function issue(McpInputRequired $input, ApplicationData $validated, McpRequest $request): McpResult
    {
        $contracts = array_map(static fn(McpInputRequest $form): StrictJson => $form->state(), $input->requests());
        $state = StrictJson::fromObject([
            'mode'      => 'ordinary',
            'method'    => 'tools/call',
            'caller'    => $this->caller,
            'tool'      => $request->parameters()['name'],
            'arguments' => self::arguments($request),
            'validated' => StrictJson::fromObject($validated->toArray()),
            'contracts' => StrictJson::fromObject($contracts),
            'expires'   => time() + $this->ttl,
            'id'        => $request->id()
        ], 70);
        $token = $this->protector->seal(self::canonical($state));
        $requests = array_map(static fn(McpInputRequest $form): StrictJson => $form->request(), $input->requests());

        return McpResult::inputRequired(StrictJson::fromObject($requests), $token);
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
                $state->get('mode') !== 'ordinary' || $state->get('method') !== 'tools/call'
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
