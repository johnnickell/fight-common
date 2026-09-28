<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Mcp;

use Fight\Common\Adapter\Http\Mcp\McpHeaderValidator;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpMirrorDeclaration;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\Fixture\Mcp\EndpointCapability;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpHeaderValidator::class)]
#[CoversClass(McpProtocolError::class)]
final class McpHeaderValidatorTest extends UnitTestCase
{
    #[DataProvider('matchingValues')]
    public function test_that_custom_mirrors_compare_decoded_primitives_without_rounding(mixed $value, string $header): void
    {
        $this->validate(['tenant' => $value], ['mCP-pARam-tENant' => [$header]]);
        $this->addToAssertionCount(1);
    }

    public static function matchingValues(): iterable
    {
        yield ['us-west1', 'us-west1'];
        yield ['', ''];
        yield [true, 'true'];
        yield [false, 'false'];
        yield [42, '42'];
        yield [42.0, '42.0'];
        yield [42, '42.000'];
        yield [42, '4.2e1'];
        yield [420, '42e1'];
        yield [-42, '-420e-1'];
        yield [0, '-0.0e999999'];
        yield [9007199254740991, '9007199254740991'];
        yield [-9007199254740991, '-9007199254740991'];
        foreach (["Hello, 世界", ' padded ', "line1\nline2", "\t", '=?base64?literal?=', 'plain', "a\tb"] as $text) {
            yield [$text, '=?base64?'.base64_encode($text).'?='];
        }
        yield ["a\tb", "a\tb"];
    }

    #[DataProvider('mismatchingValues')]
    public function test_that_invalid_or_different_custom_mirrors_fail_without_reflecting_input(mixed $value, array $headers): void
    {
        try {
            $this->validate(['tenant' => $value], $headers);
            self::fail('Expected a mirror failure.');
        } catch (McpProtocolException $exception) {
            self::assertSame(['code' => -32020, 'message' => 'Header mismatch.'], $exception->protocolError()->toArray());
        }
    }

    public static function mismatchingValues(): iterable
    {
        foreach ([['other'], [], ['x', 'x'], ['x,x'], ["x\n"], [' x'], ["x\t"], ['=?base64?*?='], ['=?base64?eA?='], ['=?base64?eB==?='], ['=?base64?/w==?=']] as $headers) {
            yield ['x', ['Mcp-Param-Tenant' => $headers]];
        }
        yield ['x', []];
        yield ['x', ['Mcp-Param-Tenant' => ['x'], 'mcp-param-tenant' => ['x']]];
        yield [null, ['Mcp-Param-Tenant' => ['null']]];
        foreach ([true, false, [], (object) ['x' => 'y'], 1.5, 9007199254740992] as $value) {
            yield [$value, ['Mcp-Param-Tenant' => ['1']]];
        }
        foreach (['42.000000000000000000001', '4.21e1', '42e9999999', '42e-999999', '0.42', '0.042', '4.2', '4.2e0', '+42', '042', 'true', '43', '42e-2', '42.0001', '4.20001e1'] as $header) {
            yield [42, ['Mcp-Param-Tenant' => [$header]]];
        }
        yield ['世界', ['Mcp-Param-Tenant' => ['世界']]];
        yield ['true', ['Mcp-Param-Tenant' => ['True']]];
        yield ['=?base64?eA==?=', ['Mcp-Param-Tenant' => ['=?base64?eA==?=']]];
    }

    public function test_that_absent_and_null_paths_omit_headers_and_unannotated_properties_are_not_inferred(): void
    {
        foreach ([[], ['tenant' => null], ['Tenant' => 'different-case'], ['other' => 'not-mirrored']] as $arguments) {
            $this->validate($arguments, []);
        }
        $this->validate([], ['Mcp-Param-Unrecognized' => ['opaque']]);
        $this->addToAssertionCount(5);
    }

    public function test_that_nested_object_paths_are_exact_and_arrays_are_not_traversed(): void
    {
        $declaration = new McpMirrorDeclaration('tools/call', ['arguments', 'account', '0'], 'Tenant');
        $this->validate(['account' => (object) ['0' => 'x']], ['Mcp-Param-Tenant' => ['x']], [$declaration]);
        $this->validate(['account' => ['x']], [], [$declaration]);
        $this->validate(['account' => null], [], [$declaration]);
        $this->addToAssertionCount(3);
    }

    public function test_that_optional_absence_with_a_supplied_header_is_rejected(): void
    {
        $this->expectException(McpProtocolException::class);
        $this->validate([], ['Mcp-Param-Tenant' => ['x']]);
    }

    #[DataProvider('standardMirrors')]
    public function test_that_standard_mirrors_preserve_case_sensitive_values(string $method, array $params, array $headers, bool $valid, ?string $name): void
    {
        $registry = new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [new EndpointCapability()]);
        $request = (new McpRequestDecoder())->decode($this->json($method, $params));
        if (!$valid) {
            $this->expectException(McpProtocolException::class);
        }
        self::assertSame($name, (new McpHeaderValidator($registry))->validate($request, $headers));
    }

    public static function standardMirrors(): iterable
    {
        $base = ['MCP-Protocol-Version' => ['2026-07-28'], 'Mcp-Method' => ['example/echo']];
        yield ['example/echo', [], $base, true, null];
        yield ['example/echo', [], ['mcp-protocol-version' => ['2026-07-28'], 'MCP-METHOD' => ['example/echo']], true, null];
        foreach (['MCP-Protocol-Version', 'Mcp-Method'] as $key) {
            $headers = $base;
            unset($headers[$key]);
            yield ['example/echo', [], $headers, false, null];
            foreach ([['wrong'], ['example/echo', 'example/echo'], ['=?base64?'.base64_encode($base[$key][0]).'?=']] as $values) {
                yield ['example/echo', [], array_replace($base, [$key => $values]), false, null];
            }
        }
        yield ['example/echo', [], $base + ['Mcp-Name' => ['extraneous']], false, null];
        foreach (['tools/call' => 'name', 'prompts/get' => 'name', 'resources/read' => 'uri'] as $method => $property) {
            $headers = array_replace($base, ['Mcp-Method' => [$method]]);
            yield [$method, [$property => 'x'], $headers, false, null];
            yield [$method, [], $headers + ['Mcp-Name' => ['x']], false, null];
            yield [$method, [$property => 1], $headers + ['Mcp-Name' => ['1']], false, null];
            yield [$method, [$property => 'x'], $headers + ['Mcp-Name' => ['X']], false, null];
            yield [$method, [$property => 'x'], $headers + ['Mcp-Name' => ['x']], true, 'x'];
            yield [$method, [$property => '世界'], $headers + ['Mcp-Name' => ['=?base64?'.base64_encode('世界').'?=']], true, '世界'];
        }
    }

    public function test_that_every_declaration_in_a_mirror_set_is_checked_before_dispatch(): void
    {
        $declarations = [
            new McpMirrorDeclaration('tools/call', ['arguments', 'region'], 'Region'),
            new McpMirrorDeclaration('tools/call', ['arguments', 'tenant'], 'Tenant'),
        ];
        $arguments = ['region' => 'west', 'tenant' => 'acme'];
        $headers = ['Mcp-Param-Region' => ['west'], 'Mcp-Param-Tenant' => ['acme']];
        $this->validate($arguments, $headers, $declarations);
        foreach (array_keys($headers) as $field) {
            $missing = $headers;
            unset($missing[$field]);
            try {
                $this->validate($arguments, $missing, $declarations);
                self::fail('Every declared mirror is required when its value is present.');
            } catch (McpProtocolException $exception) {
                self::assertSame(-32020, $exception->protocolError()->toArray()['code']);
            }
        }
    }

    public function test_that_case_insensitive_declaration_collisions_are_composition_errors(): void
    {
        $this->expectException(DomainException::class);
        new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [new EndpointCapability([
            new McpMirrorDeclaration('tools/call', ['arguments', 'first'], 'Tenant'),
            new McpMirrorDeclaration('tools/call', ['arguments', 'second'], 'tenant'),
        ])]);
    }

    private function validate(array $arguments, array $headers, ?array $declarations = null): void
    {
        $capability = new EndpointCapability($declarations ?? [new McpMirrorDeclaration('tools/call', ['arguments', 'tenant'], 'Tenant')]);
        $registry = new McpCapabilityRegistry(new McpServerInfo('Test', '1'), [$capability]);
        $request = (new McpRequestDecoder())->decode($this->json('tools/call', ['name' => 'test', 'arguments' => (object) $arguments]));
        self::assertSame('test', (new McpHeaderValidator($registry))->validate($request, $headers + [
            'MCP-Protocol-Version' => ['2026-07-28'], 'Mcp-Method' => ['tools/call'], 'Mcp-Name' => ['test'],
        ]));
        self::assertSame(0, $capability->validateCalls);
        self::assertSame(0, $capability->handleCalls);
    }

    private function json(string $method, array $params): string
    {
        return json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => $params + [
            '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []],
        ]], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }
}
