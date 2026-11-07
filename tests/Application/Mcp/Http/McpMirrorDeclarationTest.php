<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Http;

use Fight\Common\Application\Mcp as ReleasedMcp;
use Fight\Common\Application\Mcp\Http\McpMirrorDeclaration;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ReleasedMcp\McpMirrorDeclaration::class)]
final class McpMirrorDeclarationTest extends UnitTestCase
{
    public function test_that_mirror_declaration_rejects_empty_methods_and_invalid_headers(): void
    {
        foreach ([
            ['', ['region'], 'Region'],
            ['example/echo', ['named' => 'region'], 'Region'],
            ['example/echo', ['region'], 'invalid header'],
        ] as [$method, $path, $header]) {
            try {
                new McpMirrorDeclaration($method, $path, $header);
                self::fail('Expected invalid mirror declaration to be rejected.');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_that_mirror_declaration_retains_exact_method_path_and_header_identity(): void
    {
        $declaration = new McpMirrorDeclaration('example/echo', ['region'], 'Region');

        self::assertSame('example/echo', $declaration->method());
        self::assertSame(['region'], $declaration->parameterPath());
        self::assertSame('Region', $declaration->headerName());
    }

    public function test_that_mirror_declaration_rejects_an_empty_parameter_path(): void
    {
        $this->expectException(DomainException::class);
        new McpMirrorDeclaration('example/echo', [], 'Region');
    }
}
