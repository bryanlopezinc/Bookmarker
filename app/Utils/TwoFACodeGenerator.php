<?php

declare(strict_types=1);

namespace App\Utils;

use App\Contracts\TwoFACodeGeneratorInterface;
use App\ValueObjects\TwoFACode;

final class TwoFACodeGenerator implements TwoFACodeGeneratorInterface
{
    public function generate(): TwoFACode
    {
        return new TwoFACode(mt_rand(10_000, 99_999));
    }
}
