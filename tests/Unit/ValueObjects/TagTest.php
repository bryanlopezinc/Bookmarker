<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Tag;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Exceptions\InvalidTagException;

class TagTest extends TestCase
{
    public function testLengthMustNotExceedMaxLength(): void
    {
        $this->expectException(InvalidTagException::class);
        $this->expectExceptionCode(InvalidTagException::INVALID_MAX_LENGHT_CODE);

        new Tag(Str::random(Tag::MAX_LENGTH + 1));
    }

    public function testWillNotThrowExceptionWhenTagIsValid(): void
    {
        $this->expectNotToPerformAssertions();

        new Tag(Str::random(Tag::MAX_LENGTH));
        new Tag(123456);
    }

    public function testWillThrowExceptionWhenTagIsEmpty(): void
    {
        $this->expectException(InvalidTagException::class);
        $this->expectExceptionCode(InvalidTagException::EMPTY_TAG_CODE);

        new Tag('   ');
    }

    public function testWillThrowExceptionWhenTagContainsSpaces(): void
    {
        $this->expectException(InvalidTagException::class);
        $this->expectExceptionCode(InvalidTagException::APLHA_NUM_CODE);

        new Tag('foo bar');
    }

    public function testWillThrowExceptionWhenTagContainsInvalidCharacter(): void
    {
        $assertInvalid = function (string $tag): bool {
            try {
                new Tag(Str::random(Tag::MAX_LENGTH - 1) . $tag);

                return true;
            } catch (InvalidTagException $e) {
                $this->assertEquals(InvalidTagException::APLHA_NUM_CODE, $e->getCode());
                return false;
            }
        };

        $values = '~,`,!,@,#,$,%,^,&,*,(,),_,-,=,+,{,[,],},:,;,",\\,|,<,>,.,?,/';

        foreach (explode(',', $values) as $tag) {
            $this->assertFalse($assertInvalid($tag), 'validation failed for ' . $tag);
        }
    }
}
