<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\TwoFACode;
use LengthException;
use Tests\TestCase;

class TwoFACodeTest extends TestCase
{
    public function testWillThrowExceptionWhenCodeIsANegativeNumber(): void
    {
        $this->expectExceptionMessage('Invalid 2FA code -1234');

        new TwoFACode(-1234);
    }

    public function testWillThrowExceptionWhenCodeIsGreaterThan_6(): void
    {
        $this->expectExceptionMessage('Invalid 2FA code 4123447');

        new TwoFACode(4123447);
    }

    public function testWillThrowExceptionWhenCodeIsLessThan_6(): void
    {
        $this->expectExceptionMessage('Invalid 2FA code 41235');

        new TwoFACode(41235);
    }

    public function testWillGenerateCodeWithCustomGenerator(): void
    {
        $code = 111111;
        TwoFACode::useGenerator(fn () => $code);

        $this->assertEquals($code, TwoFACode::generate()->value());
        $this->assertEquals($code, TwoFACode::generate()->value());
        $this->assertEquals($code, TwoFACode::generate()->value());
        $this->assertEquals($code, TwoFACode::generate()->value());

        TwoFACode::useGenerator();
    }

    public function testWillResetGenerator(): void
    {
        $code = 111111;
        TwoFACode::useGenerator(fn () => $code);

        $this->assertEquals($code, TwoFACode::generate()->value());

        TwoFACode::useGenerator();

        $this->assertNotEquals($code, TwoFACode::generate()->value());
        $this->assertNotEquals($code, TwoFACode::generate()->value());
        $this->assertNotEquals($code, TwoFACode::generate()->value());
    }

    public function testWillThrowExceptionWhenCustomGeneratorReturnsAnInValidValue(): void
    {
        $isInvalid = function (int $code): bool {
            TwoFACode::useGenerator(fn () => $code);

            try {
                TwoFACode::generate()->value();
                return false;
            } catch (LengthException) {
                return true;
            }
        };

        $this->assertTrue($isInvalid(12));
        $this->assertTrue($isInvalid(-12345));
        $this->assertTrue($isInvalid(1234567));

        TwoFACode::useGenerator();
    }

    public function testEqualsMethod(): void
    {
        $code = TwoFACode::generate()->value();

        $this->assertTrue(
            (new TwoFACode($code))->equals(new TwoFACode($code))
        );

        $this->assertFalse(
            (new TwoFACode(456516))->equals(new TwoFACode(238596))
        );
    }

    public function testWillEncryptCodeBeforeSerialization(): void
    {
        $twoFA = TwoFACode::generate();

        $this->assertStringNotContainsString($twoFA->toString(), serialize($twoFA));
    }
}
