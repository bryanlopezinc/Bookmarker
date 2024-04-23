<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\PublicId;

use App\Enums\IdPrefix;
use App\Exceptions\BookmarkNotFoundException;
use App\Exceptions\InvalidIdException;
use App\ValueObjects\PublicId\BookmarkPublicId;
use PHPUnit\Framework\Attributes\Test;

class BookmarkPublicIdTest extends TestCase
{
    #[Test]
    public function inValid(): void
    {
        $id = new BookmarkPublicId($this->getGenerator()->generate());

        $this->expectException(InvalidIdException::class);

        new BookmarkPublicId($id->present());
    }

    #[Test]
    public function presentMethod(): void
    {
        $prefix = IdPrefix::BOOKMARK->value;

        $id = new BookmarkPublicId($this->getGenerator()->generate());

        $this->assertEquals(
            $id->present(),
            "{$prefix}{$id->value}"
        );
    }

    #[Test]
    public function fromRequestMethod(): void
    {
        $id = new BookmarkPublicId($this->getGenerator()->generate());

        $fromRequest = BookmarkPublicId::fromRequest($id->present());

        $this->assertEquals(
            $id->value,
            $fromRequest->value
        );
    }

    #[Test]
    public function fromRequestMethodWillThrowExceptionWhenIdIsInvalid(): void
    {
        $id = new BookmarkPublicId($this->getGenerator()->generate());

        $this->expectException(BookmarkNotFoundException::class);

        BookmarkPublicId::fromRequest($id->value);
    }
}
