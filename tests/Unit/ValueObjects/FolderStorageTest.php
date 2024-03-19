<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\FolderStorage;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class FolderStorageTest extends TestCase
{
    public function testWillThrowExceptionWhenTotal_is_HigherThan_200(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FolderStorage(201));
    }

    public function testWillThrowExceptionWhenTotalIsUnsigned(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FolderStorage(-50));
    }

    public function testSpaceAvailableMethod(): void
    {
        $this->assertEquals(1, (new FolderStorage(199))->spaceAvailable());
        $this->assertEquals(50, (new FolderStorage(150))->spaceAvailable());
        $this->assertEquals(0, (new FolderStorage(200))->spaceAvailable());
    }

    public function testIsFullMethod(): void
    {
        $this->assertFalse((new FolderStorage(150))->isFull());
        $this->assertFalse((new FolderStorage(0))->isFull());
        $this->assertFalse((new FolderStorage(199))->isFull());
        $this->assertTrue((new FolderStorage(200))->isFull());
    }

    public function testCanContainMethod(): void
    {
        $this->assertTrue((new FolderStorage(150))->canContain([]));
        $this->assertTrue((new FolderStorage(150))->canContain(range(1, 50)));
        $this->assertFalse((new FolderStorage(150))->canContain(range(1, 51)));
        $this->assertFalse((new FolderStorage(FolderStorage::MAX_ITEMS))->canContain([]));
    }

    public function testPercentageUsedMethod(): void
    {
        $this->assertEquals(0, (new FolderStorage(0))->percentageUsed());
        $this->assertEquals(0, (new FolderStorage(1))->percentageUsed());
        $this->assertEquals(1, (new FolderStorage(2))->percentageUsed());
        $this->assertEquals(3, (new FolderStorage(6))->percentageUsed());
        $this->assertEquals(25, (new FolderStorage(50))->percentageUsed());
        $this->assertEquals(45, (new FolderStorage(90))->percentageUsed());
        $this->assertEquals(50, (new FolderStorage(100))->percentageUsed());
        $this->assertEquals(99, (new FolderStorage(199))->percentageUsed());
        $this->assertEquals(100, (new FolderStorage(200))->percentageUsed());
    }
}
