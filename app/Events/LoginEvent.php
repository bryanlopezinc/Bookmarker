<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use App\ValueObjects\IpAddress;

final class LoginEvent
{
    public function __construct(
        public readonly User $user,
        public readonly ?string $userAgent,
        public readonly ?IpAddress $ipAddress,
    ) {
    }
}
