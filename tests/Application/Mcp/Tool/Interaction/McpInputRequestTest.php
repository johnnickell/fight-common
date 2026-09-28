<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool\Interaction;

use Fight\Common\Application\Mcp\Tool\Interaction\McpElicitationSchema;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequest;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputResponse;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputResponses;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpElicitationSchema::class)]
#[CoversClass(McpInputRequest::class)]
#[CoversClass(McpInputRequired::class)]
#[CoversClass(McpInputResponse::class)]
#[CoversClass(McpInputResponses::class)]
final class McpInputRequestTest extends UnitTestCase
{
    public function test_that_forms_retain_private_rules_but_emit_only_safe_protocol_requests(): void
    {
        $form = self::form();
        $wire = $form->request();
        self::assertSame('elicitation/create', $wire->get('method'));
        self::assertSame('form', $wire->get('params')->get('mode'));
        self::assertSame('Provide a label', $wire->get('params')->get('message'));
        self::assertStringNotContainsString('private-rule-label', $wire->toString());
        self::assertStringNotContainsString('not_blank', $wire->toString());
        $restored = McpInputRequest::restore(StrictJson::fromString($form->state()->toString()));
        self::assertEquals($wire, $restored->request());
        $responses = McpInputResponses::validate(StrictJson::fromObject(['label' => ['action' => 'accept', 'content' => ['label' => 'valid']]]), ['label' => $restored]);
        self::assertSame('accept', $responses->get('label')->action());
        self::assertSame(['label' => 'valid'], $responses->get('label')->content()->toArray());
        self::assertSame(['label' => $responses->get('label')], $responses->toArray());
        self::assertSame(['label' => $form], McpInputRequired::fromRequests(['label' => $form])->requests());
    }

    #[DataProvider('actions')]
    public function test_that_decline_and_cancel_have_no_content_and_skip_accept_only_rules(string $action): void
    {
        $form = self::form();
        $result = McpInputResponses::validate(['label' => ['action' => $action]], ['label' => $form]);
        self::assertSame($action, $result->get('label')->action());
        self::assertNull($result->get('label')->content());
    }

    public static function actions(): iterable
    {
        yield ['decline'];
        yield ['cancel'];
    }

    public function test_that_empty_forms_preserve_object_shape_and_require_an_object_response(): void
    {
        $form = McpInputRequest::form('Continue', ['type' => 'object', 'properties' => []]);
        self::assertSame('{"method":"elicitation\\/create","params":{"mode":"form","message":"Continue","requestedSchema":{"type":"object","properties":{}}}}', $form->request()->toString());
        self::assertSame([], $form->validate(StrictJson::fromObject())->toArray());
    }

    #[DataProvider('validFields')]
    public function test_that_supported_primitive_and_enum_forms_validate_accept_content(array $field, mixed $value): void
    {
        $form = McpInputRequest::form('Provide input', ['type' => 'object', 'properties' => ['value' => $field], 'required' => ['value']]);
        self::assertSame($value, $form->validate(StrictJson::fromObject(['value' => $value]))->get('value'));
    }

    public static function validFields(): iterable
    {
        yield [['type' => 'string', 'title' => 'Name', 'description' => 'Public name', 'minLength' => 1, 'maxLength' => 5, 'default' => 'é'], 'é'];
        yield [['type' => 'number', 'minimum' => 0, 'maximum' => 2.5, 'default' => 1.5], 2.5];
        yield [['type' => 'integer', 'minimum' => 0, 'maximum' => 3], 2];
        yield [['type' => 'boolean', 'default' => false], true];
        yield [['type' => 'string', 'enum' => ['a', 'b']], 'a'];
        yield [['type' => 'string', 'oneOf' => [['const' => 'a', 'title' => 'A'], ['const' => 'b', 'title' => 'B']]], 'b'];
        yield [['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['a', 'b']], 'minItems' => 1, 'maxItems' => 2, 'default' => ['a']], ['b']];
        yield [['type' => 'array', 'items' => ['anyOf' => [['const' => 'a', 'title' => 'A']]]], ['a']];
    }

    #[DataProvider('invalidDeclarations')]
    public function test_that_unsupported_or_invalid_form_declarations_fail_closed(array $schema): void
    {
        $this->expectException(DomainException::class);
        McpInputRequest::form('Input', $schema);
    }

    public static function invalidDeclarations(): iterable
    {
        yield 'no properties' => [['type' => 'object']];
        yield 'additional properties keyword' => [['type' => 'object', 'properties' => [], 'additionalProperties' => false]];
        yield 'undefined required field' => [['type' => 'object', 'properties' => [], 'required' => ['missing']]];
        yield 'numeric property' => [['type' => 'object', 'properties' => [0 => ['type' => 'string']]]];
        foreach ([
            true, ['type' => 'object'], ['type' => 'null'], ['type' => ['string', 'null']],
            ['type' => 'string', 'pattern' => '.*'], ['type' => 'string', 'format' => 'email'],
            ['type' => 'string', 'enumNames' => ['A'], 'enum' => ['a']],
            ['type' => 'string', 'examples' => ['a']], ['type' => 'string', 'enum' => [1]],
            ['type' => 'string', 'enum' => ['a', 'a']], ['type' => 'string', 'oneOf' => [true]],
            ['type' => 'string', 'oneOf' => [['const' => 1, 'title' => 'One']]],
            ['type' => 'string', 'oneOf' => [['const' => 'a']]],
            ['type' => 'string', 'oneOf' => [['const' => 'a', 'title' => 'A', 'description' => 'Extra']]],
            ['type' => 'string', 'default' => false], ['type' => 'array'], ['type' => 'array', 'items' => true],
            ['type' => 'array', 'items' => ['type' => 'number']],
            ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['a'], 'description' => 'Extra']],
            ['type' => 'array', 'items' => ['anyOf' => [['const' => 'a', 'title' => 'A']], 'type' => 'string']]
        ] as $field) {
            yield [['type' => 'object', 'properties' => ['value' => $field]]];
        }
    }

    #[DataProvider('invalidResponses')]
    public function test_that_response_envelopes_keys_and_schema_reject_invalid_values(mixed $value): void
    {
        $this->expectException(DomainException::class);
        McpInputResponses::validate($value, ['label' => self::form()]);
    }

    public static function invalidResponses(): iterable
    {
        yield [null];
        yield [[]];
        yield [(object) []];
        yield [['other' => ['action' => 'decline']]];
        yield [['label' => ['action' => 'decline'], 'extra' => ['action' => 'cancel']]];
        yield [['label' => []]];
        yield [['label' => ['action' => 'decline', 'content' => null]]];
        yield [['label' => ['action' => 'cancel', 'content' => []]]];
        yield [['label' => ['action' => 'accept']]];
        yield [['label' => ['action' => 'accept', 'content' => []]]];
        yield [['label' => ['action' => 'ACCEPT', 'content' => ['label' => 'okay']]]];
        yield [['label' => ['action' => 'decline', 'extra' => 'secret']]];
        yield [['label' => ['action' => 'accept', 'content' => (object) []]]];
        yield [['label' => ['action' => 'accept', 'content' => ['label' => '']]]];
        yield [['label' => ['action' => 'accept', 'content' => ['label' => 42]]]];
        yield [['label' => ['action' => 'accept', 'content' => ['label' => 'okay', 'extra' => 'no']]]];
    }

    public function test_that_runtime_rules_validate_accept_after_schema_success(): void
    {
        $this->expectException(ValidationException::class);
        McpInputResponses::validate(['label' => ['action' => 'accept', 'content' => ['label' => '   ']]], ['label' => self::form()]);
    }

    public function test_that_all_envelopes_are_checked_before_any_runtime_validation(): void
    {
        // The first response would throw ValidationException; malformed second envelope must win instead.
        $this->expectException(DomainException::class);
        McpInputResponses::validate([
            'first' => ['action' => 'accept', 'content' => ['label' => '   ']],
            'second' => ['action' => 'decline', 'content' => null]
        ], ['first' => self::form(), 'second' => self::form()]);
    }

    #[DataProvider('invalidRules')]
    public function test_that_runtime_rules_are_bounded_server_declarations(array $rules): void
    {
        $this->expectException(DomainException::class);
        McpInputRequest::form('Input', ['type' => 'object', 'properties' => ['label' => ['type' => 'string']]], $rules);
    }

    public static function invalidRules(): iterable
    {
        yield [['x' => []]];
        yield [[null]];
        yield [[['field' => 'label', 'label' => 'Label']]];
        yield [[['field' => 'unknown', 'label' => 'Label', 'rules' => 'not_blank']]];
        yield [[['field' => 'label', 'label' => null, 'rules' => 'not_blank']]];
        yield [[['field' => 'label', 'label' => 'Label', 'rules' => 'not_a_rule']]];
    }

    public function test_that_empty_messages_fail_before_issuance(): void
    {
        $this->expectException(DomainException::class);
        McpInputRequest::form(' ', ['type' => 'object', 'properties' => []]);
    }

    #[DataProvider('invalidMaps')]
    public function test_that_requests_require_a_nonempty_typed_string_key_map(array $requests): void
    {
        $this->expectException(DomainException::class);
        McpInputRequired::fromRequests($requests);
    }

    public static function invalidMaps(): iterable
    {
        yield [[]];
        yield [['label' => null]];
        yield [[self::form()]];
        yield [['' => self::form()]];
    }

    public function test_that_unknown_response_keys_are_not_silently_null(): void
    {
        $responses = McpInputResponses::validate(['label' => ['action' => 'decline']], ['label' => self::form()]);
        $this->expectException(DomainException::class);
        $responses->get('unknown');
    }

    #[DataProvider('invalidTypedResponses')]
    public function test_that_typed_response_factories_preserve_action_content_invariants(string $action, ?ApplicationData $content): void
    {
        $this->expectException(DomainException::class);
        McpInputResponse::validated($action, $content);
    }

    public static function invalidTypedResponses(): iterable
    {
        yield ['other', null];
        yield ['accept', null];
        yield ['decline', new ApplicationData([])];
    }

    private static function form(): McpInputRequest
    {
        return McpInputRequest::form('Provide a label', ['type' => 'object', 'properties' => ['label' => ['type' => 'string', 'minLength' => 1]], 'required' => ['label']], [['field' => 'label', 'label' => 'private-rule-label', 'rules' => 'not_blank']]);
    }
}
