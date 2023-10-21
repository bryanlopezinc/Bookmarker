<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use App\IpGeoLocation\IpAddress;

final class LoginEvent
{
    public function __construct(
        public readonly User $user,
        public readonly ?string $userAgent,
        public readonly ?IpAddress $ipAddress,
    ) {
    }

    public function hasUserAgentInfo(): bool
    {
        return !is_null($this->userAgent);
    }

    public function hasIpAddressInfo(): bool
    {
        return !is_null($this->ipAddress);
    }
}
