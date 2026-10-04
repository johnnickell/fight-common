<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Mcp;

use Fight\Common\Adapter\Http\Mcp\DenyAllMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpOrigin;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpOrigin::class)]
#[CoversClass(ExactMcpOriginPolicy::class)]
#[CoversClass(DenyAllMcpOriginPolicy::class)]
final class McpOriginTest extends UnitTestCase
{
    public function test_that_exact_origins_normalize_scheme_host_default_ports_and_ipv6_only(): void
    {
        $policy = new ExactMcpOriginPolicy(['HTTPS://EXAMPLE.COM', 'http://localhost:8080', 'http://[0:0:0:0:0:0:0:1]']);
        foreach (['https://example.com:443', 'http://localhost:8080', 'http://[::1]:80'] as $origin) {
            self::assertTrue($policy->allows(McpOrigin::fromString($origin)->toString()));
        }
        foreach (['http://example.com', 'https://example.com:444', 'https://sub.example.com', 'https://example.com.evil.test', 'https://example.com.'] as $origin) {
            self::assertFalse($policy->allows(McpOrigin::fromString($origin)->toString()));
        }
        self::assertSame('http://localhost:80', McpOrigin::fromString('http://LOCALHOST')->toString());
        self::assertFalse((new ExactMcpOriginPolicy([]))->allows('https://example.com:443'));
        self::assertFalse((new DenyAllMcpOriginPolicy())->allows('https://example.com:443'));
    }

    #[DataProvider('invalidOrigins')]
    public function test_that_invalid_origin_configuration_fails_closed(string $origin): void
    {
        $this->expectException(DomainException::class);
        new ExactMcpOriginPolicy([$origin]);
    }

    public static function invalidOrigins(): iterable
    {
        foreach (['null', '', '*', 'https://*.example.com', 'https://example.com/', 'https://example.com/path', 'https://example.com?x=1', 'https://example.com#fragment', 'https://user@example.com', 'ftp://example.com', ' https://example.com', "https://example.com\n", 'https://example.com https://other.test', 'https://[:::]', 'https://example.com:0', 'https://example.com:65536', 'https://a..b', 'https://-host.test', 'https://a_.test', 'https://'.str_repeat('a', 254), 'https://'.str_repeat('a', 64).'.test'] as $origin) {
            yield [$origin];
        }
    }
}
