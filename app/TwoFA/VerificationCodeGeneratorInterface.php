<?php

declare(strict_types=1);

namespace App\TwoFA;

interface VerificationCodeGeneratorInterface
{
    public function generate(): VerificationCode;
}
