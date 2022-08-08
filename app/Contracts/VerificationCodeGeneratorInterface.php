<?php

declare(strict_types=1);

namespace App\Contracts;

use App\ValueObjects\VerificationCode;

interface VerificationCodeGeneratorInterface
{
    public function generate(): VerificationCode;
}
