<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool\Interaction;

use Fight\Common\Application\Mcp\Tool\McpToolSchemaValidator;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Application\Validation\RulesParser;
use Fight\Common\Application\Validation\ValidationService;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpInputRequest
 */
final readonly class McpInputRequest
{
    /**
     * Constructs McpInputRequest
     *
     * @param string $message
     * @param StrictJson $schema
     * @param list<array{field: string, label: string, rules: string}> $rules
     */
    private function __construct(private string $message, private StrictJson $schema, private array $rules)
    {
    }

    /**
     * Creates a public-safe form request with separately retained runtime validation rules
     *
     * Request only non-sensitive information. Rules are server-authored declarations, not client data.
     *
     * @param string $message
     * @param array<mixed> $schema
     * @param array<mixed> $rules
     */
    public static function form(string $message, array $schema, array $rules = []): self
    {
        StrictJson::fromData($message);
        $schema = McpElicitationSchema::create($schema);
        if (trim($message) === '' || !array_is_list($rules)) {
            throw new DomainException('An input request requires a message and a list of runtime rules.');
        }

        $validatedRules = [];
        foreach ($rules as $rule) {
            if (
                !is_array($rule) || count($rule) !== 3
                || !is_string($rule['field'] ?? null) || !is_string($rule['label'] ?? null)
                || !is_string($rule['rules'] ?? null) || !$schema->get('properties')->has($rule['field'])
            ) {
                throw new DomainException('Input runtime rules must identify a declared form field.');
            }

            $validatedRules[] = ['field' => $rule['field'], 'label' => $rule['label'], 'rules' => $rule['rules']];
        }

        RulesParser::parse($validatedRules);

        return new self($message, $schema, $validatedRules);
    }

    /**
     * Restores a server-authenticated input contract without accepting arbitrary PHP objects
     *
     * @internal Used after state binding and availability checks
     */
    public static function restore(StrictJson $state): self
    {
        $rules = array_map(static fn(StrictJson $rule): array => $rule->properties(), $state->get('rules'));

        return self::form($state->get('message'), $state->get('schema')->properties(), $rules);
    }

    /**
     * Returns the safe elicitation request without runtime rules
     */
    public function request(): StrictJson
    {
        return StrictJson::fromObject([
            'method' => 'elicitation/create',
            'params' => ['mode' => 'form', 'message' => $this->message, 'requestedSchema' => $this->schema]
        ]);
    }

    /**
     * Returns the contract for private authenticated storage only
     *
     * @internal
     */
    public function state(): StrictJson
    {
        return StrictJson::fromObject([
            'message' => $this->message,
            'schema'  => $this->schema,
            'rules'   => $this->rules
        ]);
    }

    /**
     * Validates accept-only content through the retained schema and runtime rules
     *
     * @internal
     */
    public function validate(StrictJson $content): ApplicationData
    {
        if (!McpToolSchemaValidator::matches($content, $this->schema->with('additionalProperties', false))) {
            throw new DomainException('Invalid interaction response.');
        }

        // Each response gets a fresh coordinator; initial Tool rules are never rerun or accumulated.
        return new ValidationService()->validate($content->properties(), $this->rules);
    }
}
