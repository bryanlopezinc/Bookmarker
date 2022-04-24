<?php

declare(strict_types=1);

namespace App\TwoFA;

final class RandomNumberCodeGenerator implements VerificationCodeGeneratorInterface
{
    public function generate(): VerificationCode
    {
        return new VerificationCode(mt_rand(10_000, 99_999));
    }
}
