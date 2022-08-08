<?php

declare(strict_types=1);

namespace App\Utils;

use App\Contracts\VerificationCodeGeneratorInterface;
use App\ValueObjects\VerificationCode;

final class VerificationCodeGenerator implements VerificationCodeGeneratorInterface
{
    public function generate(): VerificationCode
    {
        return new VerificationCode(mt_rand(10_000, 99_999));
    }
}
