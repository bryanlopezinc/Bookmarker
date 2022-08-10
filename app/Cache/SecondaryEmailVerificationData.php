<?php

declare(strict_types=1);

namespace App\Cache;

use App\ValueObjects\Email;
use App\ValueObjects\VerificationCode;

final class SecondaryEmailVerificationData
{
    public function __construct(public readonly Email $email, public readonly VerificationCode $verificationCode)
    {
    }
}
