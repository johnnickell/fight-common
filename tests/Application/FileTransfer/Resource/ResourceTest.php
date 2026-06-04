<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\FileTransfer\Resource;

use DateTimeImmutable;
use Fight\Common\Application\FileTransfer\Resource\Resource;
use Fight\Common\Application\FileTransfer\Resource\ResourceType;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Resource::class)]
class ResourceTest extends UnitTestCase
{
    private Resource $resource;

    protected function setUp(): void
    {
        $this->resource = new Resource(
            '  /var/www/file.txt  ',
            1024,
            1000,
            1001,
            0644,
            new DateTimeImmutable('2024-01-01 10:00:00'),
            new DateTimeImmutable('2024-06-01 12:00:00'),
            ResourceType::FILE
        );
    }

    public function test_that_path_is_trimmed(): void
    {
        self::assertSame('/var/www/file.txt', $this->resource->path());
    }

    public function test_that_size_is_returned(): void
    {
        self::assertSame(1024, $this->resource->size());
    }

    public function test_that_user_id_is_returned(): void
    {
        self::assertSame(1000, $this->resource->userId());
    }

    public function test_that_group_id_is_returned(): void
    {
        self::assertSame(1001, $this->resource->groupId());
    }

    public function test_that_mode_is_normalised_to_octal(): void
    {
        self::assertSame(0644, $this->resource->mode());
    }

    public function test_that_permissions_returns_octal_string(): void
    {
        self::assertSame('0644', $this->resource->permissions());
    }

    public function test_that_access_time_is_returned(): void
    {
        self::assertSame('2024-01-01 10:00:00', $this->resource->accessTime()->format('Y-m-d H:i:s'));
    }

    public function test_that_modify_time_is_returned(): void
    {
        self::assertSame('2024-06-01 12:00:00', $this->resource->modifyTime()->format('Y-m-d H:i:s'));
    }

    public function test_that_type_is_returned(): void
    {
        self::assertSame(ResourceType::FILE, $this->resource->type());
    }

    public function test_that_to_string_returns_path(): void
    {
        self::assertSame('/var/www/file.txt', (string) $this->resource);
    }

    public function test_that_directory_type_is_supported(): void
    {
        $resource = new Resource(
            '/var/www',
            0,
            0,
            0,
            0755,
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            ResourceType::DIR
        );

        self::assertSame(ResourceType::DIR, $resource->type());
        self::assertSame('0755', $resource->permissions());
    }
}
