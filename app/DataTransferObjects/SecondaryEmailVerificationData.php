<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\ValueObjects\TwoFACode;

final class SecondaryEmailVerificationData
{
    public function __construct(public readonly string $email, public readonly TwoFACode $twoFACode)
    {
    }
}
