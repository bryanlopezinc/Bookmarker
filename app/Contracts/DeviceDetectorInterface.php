<?php

declare(strict_types=1);

namespace App\Contracts;

use App\ValueObjects\Device;

interface DeviceDetectorInterface
{
    public function fromUserAgent(string $userAgent): Device;
}
