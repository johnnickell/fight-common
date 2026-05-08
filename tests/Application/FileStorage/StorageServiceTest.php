<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\FileStorage;

use Fight\Common\Application\FileStorage\Exception\DuplicateStorageException;
use Fight\Common\Application\FileStorage\Exception\StorageNotFoundException;
use Fight\Common\Application\FileStorage\FileStorage;
use Fight\Common\Application\FileStorage\StorageService;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StorageService::class)]
class StorageServiceTest extends UnitTestCase
{
    private StorageService $service;

    protected function setUp(): void
    {
        $this->service = new StorageService();
    }

    public function test_that_add_storage_registers_file_storage_under_given_name(): void
    {
        /** @var MockInterface|FileStorage $storage */
        $storage = $this->mock(FileStorage::class);

        $this->service->addStorage('s3', $storage);

        self::assertSame($storage, $this->service->getStorage('s3'));
    }

    public function test_that_add_storage_throws_duplicate_storage_exception_for_duplicate_name(): void
    {
        /** @var MockInterface|FileStorage $storage */
        $storage = $this->mock(FileStorage::class);
        $this->service->addStorage('s3', $storage);

        self::expectException(DuplicateStorageException::class);

        $this->service->addStorage('s3', $storage);
    }

    public function test_that_get_storage_returns_correct_file_storage_instance(): void
    {
        /** @var MockInterface|FileStorage $s3 */
        $s3 = $this->mock(FileStorage::class);
        /** @var MockInterface|FileStorage $local */
        $local = $this->mock(FileStorage::class);

        $this->service->addStorage('s3', $s3);
        $this->service->addStorage('local', $local);

        self::assertSame($local, $this->service->getStorage('local'));
        self::assertSame($s3, $this->service->getStorage('s3'));
    }

    public function test_that_get_storage_throws_storage_not_found_exception_for_unregistered_name(): void
    {
        self::expectException(StorageNotFoundException::class);

        $this->service->getStorage('unknown');
    }

    public function test_that_copy_storage_to_storage_delegates_to_source_and_destination(): void
    {
        $resource = 'file-resource-handle';
        /** @var MockInterface|FileStorage $source */
        $source = $this->mock(FileStorage::class);
        /** @var MockInterface|FileStorage $destination */
        $destination = $this->mock(FileStorage::class);

        $source->shouldReceive('getFileResource')
            ->once()
            ->with('images/photo.jpg')
            ->andReturn($resource);

        $destination->shouldReceive('putFile')
            ->once()
            ->with('uploads/photo.jpg', $resource);

        $this->service->addStorage('source', $source);
        $this->service->addStorage('destination', $destination);

        $this->service->copyStorageToStorage('source', 'images/photo.jpg', 'destination', 'uploads/photo.jpg');
    }

    public function test_that_move_storage_to_storage_delegates_to_source_and_destination_and_removes_source(): void
    {
        $resource = 'file-resource-handle';
        /** @var MockInterface|FileStorage $source */
        $source = $this->mock(FileStorage::class);
        /** @var MockInterface|FileStorage $destination */
        $destination = $this->mock(FileStorage::class);

        $source->shouldReceive('getFileResource')
            ->once()
            ->with('images/photo.jpg')
            ->andReturn($resource);

        $destination->shouldReceive('putFile')
            ->once()
            ->with('uploads/photo.jpg', $resource);

        $source->shouldReceive('removeFile')
            ->once()
            ->with('images/photo.jpg');

        $this->service->addStorage('source', $source);
        $this->service->addStorage('destination', $destination);

        $this->service->moveStorageToStorage('source', 'images/photo.jpg', 'destination', 'uploads/photo.jpg');
    }
}
