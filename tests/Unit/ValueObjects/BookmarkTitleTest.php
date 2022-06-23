<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use Tests\TestCase;
use App\ValueObjects\BookmarkTitle;
use Illuminate\Foundation\Testing\WithFaker;

class BookmarkTitleTest extends TestCase
{
    use WithFaker;

    public function testCanBeAUrl(): void
    {
        $this->expectNotToPerformAssertions();

        new BookmarkTitle($this->faker->url);
    }

    public function testWillThrowExceptionIfEmpty(): void
    {
        $this->expectExceptionCode(5000);

        new BookmarkTitle('');
    }

    public function testWillThrowExceptionIfMaxLengthIsExceeded(): void
    {
        $this->expectException(\LengthException::class);

        new BookmarkTitle(str_repeat('a', BookmarkTitle::MAX + 1));
    }

    public function testWillConvertSpecialCharacters(): void
    {
        $description = new BookmarkTitle($value = "<script>alert(you are fucked)</script>");

        $this->assertEquals($description->value, $value);
        $this->assertEquals($description->safe(), '&lt;script&gt;alert(you are fucked)&lt;/script&gt;');
    }
}
