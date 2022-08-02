<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\BookmarkDescription;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class BookmarkDescriptionTest extends TestCase
{
    use WithFaker;

    public function testCanBeEmpty(): void
    {
        $this->expectNotToPerformAssertions();

        new BookmarkDescription(' ');
    }

    public function testWillThrowExceptionIfMaxLengthIsExceeded(): void
    {
        $this->expectException(\LengthException::class);

        new BookmarkDescription(str_repeat('a', BookmarkDescription::MAX_LENGTH + 1));
    }

    public function testWillConvertSpecialCharacters(): void
    {
        $description = new BookmarkDescription($value = "<script>alert(you are fucked)</script>");

        $this->assertEquals($description->value, $value);
        $this->assertEquals($description->safe(), '&lt;script&gt;alert(you are fucked)&lt;/script&gt;');
    }

    public function testWillLimitDescription(): void
    {
        $this->assertEquals(200, strlen(BookmarkDescription::limit(str_repeat('B', 201))->value));
        $this->assertEquals(str_repeat('B', 200), BookmarkDescription::limit(str_repeat('B', 200))->value);
        $this->assertEquals(str_repeat('B', 99), BookmarkDescription::limit(str_repeat('B', 99))->value);
        $this->assertEquals('', BookmarkDescription::limit('')->value);
    }
}
