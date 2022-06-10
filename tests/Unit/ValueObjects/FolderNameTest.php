<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use Tests\TestCase;
use App\ValueObjects\FolderName;

class FolderNameTest extends TestCase
{
    public function testCannotBeEmpty(): void
    {
        $this->expectExceptionMessage('Folder name cannot be empty');

        new FolderName('                      ');
    }

    public function testCannotExceed_50(): void
    {
        $this->expectExceptionMessage('Folder name cannot exceed 50');

        new FolderName(str_repeat('f', 51));
    }

    public function testWillConvertSpecialCharacters(): void
    {
        $folder = new FolderName($value = "<script>alert(shame on you bryan :-( )</script>");

        $this->assertEquals($folder->value(), $value);
        $this->assertEquals($folder->safe(), '&lt;script&gt;alert(shame on you bryan :-( )&lt;/script&gt;');
    }
}
