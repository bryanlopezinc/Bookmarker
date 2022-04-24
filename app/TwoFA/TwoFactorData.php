<?php

declare(strict_types=1);

namespace App\TwoFA;

use App\ValueObjects\UserId;
use Carbon\Carbon;

final class TwoFactorData
{
    public function __construct(
        public readonly UserId $userID,
        public readonly VerificationCode $verificationCode,
        public readonly Carbon $retryAfter
    ) {
    }
}
