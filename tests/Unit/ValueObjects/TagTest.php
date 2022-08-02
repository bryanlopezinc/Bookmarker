<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\Exceptions\InvalidTagException;
use App\ValueObjects\Tag;
use Illuminate\Support\Str;
use Tests\TestCase;

class TagTest extends TestCase
{
    public function testLengthMustNotExceedMaxLength(): void
    {
        $this->expectException(InvalidTagException::class);

        new Tag(Str::random(Tag::MAX_LENGTH + 1));
    }

    public function testWillNotThrowExceptionWhenTagIsValid(): void
    {
        $this->expectNotToPerformAssertions();

        new Tag(Str::random(Tag::MAX_LENGTH));
        new Tag('123456');
        new Tag('foo bar foo');
    }

    public function testWillThrowExceptionWhenTagIsEmpty(): void
    {
        $this->expectExceptionCode(998);

        new Tag('      ');
    }

    public function testWillNotThrowExceptionWhenTagContainsSpecialCharacters(): void
    {
        $isValid = function (string $tag): bool {
            try {
                new Tag(Str::random(Tag::MAX_LENGTH - 1) . $tag);

                return true;
            } catch (InvalidTagException) {
                return false;
            }
        };

        $values = '~,`,!,@,#,$,%,^,&,*,(,),_,-,=,+,{,[,],},:,;,",\\,|,<,>,.,?,/';

        foreach (explode(',', $values) as $tag) {
            $this->assertTrue($isValid($tag), 'validation failed for ' . $tag);
        }
    }
}
