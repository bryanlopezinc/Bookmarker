<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use Illuminate\Support\Str;
use Tests\TestCase;
use App\Exceptions\InvalidUsernameException;
use App\ValueObjects\Username;

class UsernameTest extends TestCase
{
    public function testWillThrowExceptionWhenUsernameExceedsMaxLength(): void
    {
        $this->expectException(InvalidUsernameException::class);
        $this->expectExceptionCode(5000);

        new Username(Str::random(Username::MAX_LENGTH + 1));
    }

    public function testWillThrowExceptionWhenUsernameLengthIsTooShort(): void
    {
        $this->expectException(InvalidUsernameException::class);
        $this->expectExceptionCode(5001);

        new Username(Str::random(Username::MIN_LENGTH - 1));
    }

    public function testWillThrowExceptionWhenUsernameContainsInvalidChars(): void
    {
        $values = '~,`,!,@,#,$,%,^,&,*,(,),-,=,+,{,[,],},:,;,",\\,|,<,>,.,?,/';

        $exceptionCode = function (string $char): int {
            try {
                new Username(Str::random(Username::MAX_LENGTH - 1) . $char);

                return 0;
            } catch (InvalidUsernameException $e) {
                return $e->getCode();
            }
        };

        foreach (explode(',', $values) as $char) {
            $this->assertEquals($exceptionCode($char), 5002, 'assertion failed for ' . $char);
        }
    }
}
