<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Tag;
use Illuminate\Support\Str;
use Tests\TestCase;

class TagTest extends TestCase
{
    public function testLengthMustNotExceedMaxLength(): void
    {
        $this->expectException(\LengthException::class);

        new Tag(Str::random(Tag::MAX_LENGTH + 1));
    }

    public function testWillNotThrowExceptionWhenTagIsValid(): void
    {
        $this->expectNotToPerformAssertions();

        new Tag(Str::random(Tag::MAX_LENGTH));
        new Tag('123456');
    }

    public function testWillThrowExceptionWhenTagIsEmpty(): void
    {
        $this->expectExceptionCode(998);

        new Tag('      ');
    }

    public function testWillThrowExceptionWhenTagContainsSpaces(): void
    {
        $this->expectException(\DomainException::class);

        new Tag('foo bar');
    }

    public function testWillThrowExceptionWhenTagContainsInvalidCharacter(): void
    {
        $assertInvalid = function (string $tag): bool {
            try {
                new Tag(Str::random(Tag::MAX_LENGTH - 1) . $tag);

                return true;
            } catch (\DomainException) {
                return false;
            }
        };

        $values = '~,`,!,@,#,$,%,^,&,*,(,),_,-,=,+,{,[,],},:,;,",\\,|,<,>,.,?,/';

        foreach (explode(',', $values) as $tag) {
            $this->assertFalse($assertInvalid($tag), 'validation failed for ' . $tag);
        }
    }
}
