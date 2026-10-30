<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequest;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputResponses;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\TaskInputBatch;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class TaskInputForms
 */
final class TaskInputForms
{
    /**
     * Creates retained supported forms without changing ordinary stateless interaction
     */
    public static function batch(McpInputRequired $input, string $caller, int $expiresAt): TaskInputBatch
    {
        $requests = [];
        foreach ($input->requests() as $key => $form) {
            $contract = $form->state();
            $requests[$key] = StrictJson::fromObject([
                'name'     => $key,
                'public'   => self::description($contract, $input->requiresConfirmation()),
                'contract' => $contract
            ], 40);
        }

        return TaskInputBatch::create(
            $caller,
            $expiresAt,
            $input->requiresConfirmation(),
            StrictJson::fromObject($requests)
        );
    }

    /**
     * Validates trusted contracts and their exact disclosed counterparts before persistence
     */
    public static function validate(TaskInputBatch $batch): void
    {
        foreach ($batch->data()->get('requests')->properties() as $request) {
            $form = McpInputRequest::restore($request->get('contract'));
            $expected = self::description($form->state(), $batch->data()->get('confirmation'));
            if ($expected->toString() !== $request->get('public')->toString()) {
                throw new DomainException('Task input public contract mismatch.');
            }
        }
    }

    /**
     * Validates every selected active entry before returning any accepted batch
     *
     * @phpstan-param array<string, StrictJson> $active Active protected contracts
     */
    public static function responses(StrictJson $submitted, array $active): StrictJson
    {
        $forms = [];
        $selected = [];
        foreach ($submitted->properties() as $key => $response) {
            if (isset($active[$key])) {
                $forms[$key] = McpInputRequest::restore($active[$key]);
                $selected[$key] = $response;
            }
        }

        // The unchanged validator still requires exactly all keys of this deliberately selected subset.
        $validated = McpInputResponses::validate(StrictJson::fromObject($selected), $forms);
        $answers = [];
        foreach ($validated->toArray() as $key => $response) {
            $answer = ['action' => $response->action()];
            if ($response->content() !== null) {
                $answer['content'] = StrictJson::fromObject($response->content()->toArray());
            }

            $answers[$key] = StrictJson::fromObject($answer);
        }

        return StrictJson::fromObject($answers, 40);
    }

    /**
     * Returns only public-safe supported form metadata
     */
    private static function description(StrictJson $contract, bool $confirmation): StrictJson
    {
        return StrictJson::fromObject([
            'type'    => $confirmation ? 'confirmation' : 'form',
            'message' => $contract->get('message'),
            'schema'  => $contract->get('schema')
        ], 40);
    }
}
