<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

use Closure;
use Fight\Common\Application\Mcp\Tool\Interaction\McpElicitationSchema;
use Fight\Common\Application\Mcp\Tool\McpToolSchema;
use Fight\Common\Application\Mcp\Tool\McpToolSchemaValidator;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class ToolTaskPayloadPolicy
 */
final readonly class ToolTaskPayloadPolicy implements TaskPayloadPolicy
{
    /**
     * Constructs ToolTaskPayloadPolicy
     *
     * @phpstan-param Closure(Task): bool $disclosure Current all-field safe disclosure decision
     * @phpstan-param array<array-key, string> $errors Explicit constant public execution-error mappings
     */
    public function __construct(private Closure $disclosure, private array $errors = [-32603 => 'Internal error'])
    {
        foreach ($errors as $code => $message) {
            if (!is_int($code) || $message === '' || strlen($message) > 1024 || preg_match('//u', $message) !== 1) {
                throw new DomainException('Invalid Task public error mapping.');
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function validate(TaskDefinition $definition, TaskState $state): void
    {
        $context = $definition->operation();
        $schema = $context->get('output_schema');
        if (
            $context->get('kind') !== 'tool' || !is_string($context->get('name')) || $context->get('name') === ''
            || !is_string($context->get('revision')) || $context->get('revision') === ''
            || !$schema instanceof StrictJson || !$schema->isObject()
        ) {
            throw new DomainException('Unsupported original Task contract.');
        }

        $schema = McpToolSchema::create($schema->properties(), false);
        $data = $state->data();
        if ($state->status() === 'completed') {
            $result = $data->get('result');
            if (
                !$result instanceof StrictJson || !is_array($result->get('content'))
                || !is_bool($result->get('isError'))
                || array_diff(array_keys($result->properties()), ['content', 'structuredContent', 'isError']) !== []
            ) {
                throw new DomainException('Invalid original Tool result.');
            }

            foreach ($result->get('content') as $item) {
                if (
                    !$item instanceof StrictJson || $item->get('type') !== 'text' || !is_string($item->get('text'))
                    || array_diff(array_keys($item->properties()), ['type', 'text']) !== []
                ) {
                    throw new DomainException('Unsupported original Tool content.');
                }
            }

            if (
                !$result->get('isError')
                && (!$result->has('structuredContent') || !McpToolSchemaValidator::matches(
                    $result->get('structuredContent'),
                    $schema
                ))
            ) {
                throw new DomainException('Original Tool output contract mismatch.');
            }
        }

        if ($state->status() === 'failed') {
            $error = $data->get('error');
            if (
                !$error instanceof StrictJson || $error->has('data')
                || ($this->errors[$error->get('code')] ?? null) !== $error->get('message')
            ) {
                throw new DomainException('Unmapped Task execution failure.');
            }
        }

        if ($state->status() === 'input_required') {
            $input = $data->get('input');
            foreach ($input->properties() as $request) {
                $form = $request->get('schema');
                if (
                    !$form instanceof StrictJson || !$form->isObject() || !is_string($request->get('message'))
                    || strlen($request->get('message')) > 1024
                ) {
                    throw new DomainException('Unsupported Task input form.');
                }

                McpElicitationSchema::create($form->properties());
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function mayDisclose(Task $task): bool
    {
        return ($this->disclosure)($task);
    }
}
