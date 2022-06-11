<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\FolderDescription;
use Tests\TestCase;

class FolderDescriptionTest extends TestCase
{
    public function testIsEmpty(): void
    {
        $description = new FolderDescription('                      ');

        $this->assertTrue($description->isEmpty());
    }

    public function testCannotExceed_150(): void
    {
        $this->expectExceptionMessage('Folder description cannot exceed 150');

        new FolderDescription(str_repeat('f', 151));
    }

    public function testWillConvertSpecialCharacters(): void
    {
        $description = new FolderDescription($value = "<script>alert(shame on you bryan :-( )</script>");

        $this->assertEquals($description->value, $value);
        $this->assertEquals($description->safe(), '&lt;script&gt;alert(shame on you bryan :-( )&lt;/script&gt;');
    }
}