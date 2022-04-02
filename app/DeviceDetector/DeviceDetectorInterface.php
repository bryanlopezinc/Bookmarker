<?php

declare(strict_types=1);

namespace App\DeviceDetector;

interface DeviceDetectorInterface
{
    public function fromUserAgent(string $userAgent): Device;
}
