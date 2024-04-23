<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\PublicId;

use App\Enums\IdPrefix;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\InvalidIdException;
use App\ValueObjects\PublicId\FolderPublicId;
use PHPUnit\Framework\Attributes\Test;

class FolderPublicIdTest extends TestCase
{
    #[Test]
    public function inValid(): void
    {
        $id = new FolderPublicId($this->getGenerator()->generate());

        $this->expectException(InvalidIdException::class);

        new FolderPublicId($id->present());
    }

    #[Test]
    public function presentMethod(): void
    {
        $prefix = IdPrefix::FOLDER->value;

        $id = new FolderPublicId($this->getGenerator()->generate());

        $this->assertEquals(
            $id->present(),
            "{$prefix}{$id->value}"
        );
    }

    #[Test]
    public function fromRequestMethod(): void
    {
        $id = new FolderPublicId($this->getGenerator()->generate());

        $fromRequest = FolderPublicId::fromRequest($id->present());

        $this->assertEquals(
            $id->value,
            $fromRequest->value
        );
    }

    #[Test]
    public function fromRequestMethodWillThrowExceptionWhenIdIsInvalid(): void
    {
        $id = new FolderPublicId($this->getGenerator()->generate());

        $this->expectException(FolderNotFoundException::class);

        FolderPublicId::fromRequest($id->value);
    }
}
