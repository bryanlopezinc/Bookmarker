<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\Exceptions\Invalid2FACodeException;
use App\ValueObjects\TwoFACode;
use Tests\TestCase;

class TwoFACodeTest extends TestCase
{
    public function testCodeCannotBeANegativeNumber(): void
    {
        $this->expectException(Invalid2FACodeException::class);

        new TwoFACode(-1234);
    }

    public function testCodeCannotBeGreaterThan_5(): void
    {
        $this->expectException(Invalid2FACodeException::class);

        new TwoFACode(412344);
    }

    public function testCodeCannotBeLessThan_5(): void
    {
        $this->expectException(Invalid2FACodeException::class);

        new TwoFACode(4123);
    }

    public function testCanGenerateCodeWithCustomGenerator(): void
    {
        $code = 11111;
        TwoFACode::useGenerator(fn () => $code);

        $this->assertEquals($code, TwoFACode::generate()->code());
        $this->assertEquals($code, TwoFACode::generate()->code());
        $this->assertEquals($code, TwoFACode::generate()->code());
        $this->assertEquals($code, TwoFACode::generate()->code());

        TwoFACode::useGenerator();
    }

    public function testWillResetGenerator(): void
    {
        $code = 11111;
        TwoFACode::useGenerator(fn () => $code);

        $this->assertEquals($code, TwoFACode::generate()->code());

        TwoFACode::useGenerator();

        $this->assertNotEquals($code, TwoFACode::generate()->code());
        $this->assertNotEquals($code, TwoFACode::generate()->code());
        $this->assertNotEquals($code, TwoFACode::generate()->code());
    }

    public function testCustomGeneratorMustReturnAValidValue(): void
    {
        $isInvalid = function (int $code): bool {
            TwoFACode::useGenerator(fn () => $code);

            try {
                TwoFACode::generate()->code();
                return false;
            } catch (Invalid2FACodeException) {
                return true;
            }
        };

        $this->assertTrue($isInvalid(12));
        $this->assertTrue($isInvalid(-12345));
        $this->assertTrue($isInvalid(123456));

        TwoFACode::useGenerator();
    }

    public function testEquals(): void
    {
        $code = TwoFACode::generate()->code();

        $this->assertTrue(
            (new TwoFACode($code))->equals(new TwoFACode($code))
        );

        $this->assertFalse(
            (new TwoFACode(45651))->equals(new TwoFACode(23859))
        );
    }

    public function testWillEncryptCodeBeforeSerialization(): void
    {
        $twoFA = TwoFACode::generate();

        $this->assertStringNotContainsString($twoFA->toString(), serialize($twoFA));
    }
}
