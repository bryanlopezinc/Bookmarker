<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\NanoIdGenerator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

class NanoIdGeneratorTest extends TestCase
{
    #[Test]
    public function inValid(): void
    {
        $this->assertFalse($this->isValid(''));
        $this->assertFalse($this->isValid('abc'));
        $this->assertFalse($this->isValid(Str::random(16)));
    }

    #[Test]
    #[TestWith(['BlD03cstR tDZULi8W'])]
    #[TestWith(['BlD03cstR  DZULi8W'])]
    public function noSpaces($idWithSpaces): void
    {
        $this->assertEquals(strlen($idWithSpaces), NanoIdGenerator::LENGTH);

        $this->assertFalse($this->isValid($idWithSpaces));
    }

    #[Test]
    #[DataProvider('specialCharsProvider')]
    public function cannotContainSpecialChars($symbol): void
    {
        $validId = ((new NanoIdGenerator()))->generate();

        $invalid = $validId;
        $invalid[3] = $symbol;

        $this->assertEquals(strlen($invalid), NanoIdGenerator::LENGTH);
        $this->assertFalse($this->isValid($invalid));
    }

    public static function specialCharsProvider(): array
    {
        return  [
            'Ampersand'            => ['&'],
            'At'                   => ['@'],
            'Back Tick'            => ['`'],
            'Backslash'            => ['\\'],
            'Caret'                => ['^'],
            'Colon'                => [':'],
            'Comma'                => [','],
            'Dollar'               => ['$'],
            'Dot'                  => ['.'],
            'Double Quote'         => ['"'],
            'Equal'                => ['='],
            'Exclamation'          => ['!'],
            'Forward Slash'        => ['/'],
            'Greater Than'         => ['>'],
            'Hash'                 => ['#'],
            'Hyphen'               => ['-'],
            'Left Curly Brace'     => ['{'],
            'Left Square Bracket'  => ['['],
            'Left parenthesis'     => ['('],
            'Less Than'            => ['<'],
            'Percentage'           => ['%'],
            'Pipe'                 => ['|'],
            'Plus'                 => ['+'],
            'Question Mark'        => ['?'],
            'Right Curly Brace'    => ['}'],
            'Right Square Bracket' => [']'],
            'Right parenthesis'    => [')'],
            'Semicolon'            => [';'],
            'Single Quote'         => ["'"],
            'Star'                 => ['*'],
            'Tilde'                => ['~'],
            'Underscore'           => ['_'],
        ];
    }

    private function isValid(string $id): bool
    {
        return (new NanoIdGenerator())->isValid($id);
    }

    #[Test]
    public function valid(): void
    {
        $this->assertTrue($this->isValid('BlD03cstRntDZULi8W'));
    }
}
