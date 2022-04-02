<?php

declare(strict_types=1);

namespace App\DeviceDetector;

use Jenssegers\Agent\Agent;

final class DeviceDetector implements DeviceDetectorInterface
{
    public function fromUserAgent(string $userAgent): Device
    {
        $device = new  Agent(userAgent: $userAgent);

        $deviceName = $device->device() ? $device->device() : null;

        return match ($device->deviceType()) {
            'phone'    => new Device(DeviceType::MOBILE, $deviceName),
            'tablet'     => new Device(DeviceType::TABLET, $deviceName),
            'desktop' => new Device(DeviceType::PC, $deviceName),
            default     => new Device(DeviceType::UNKNOWN, $deviceName)
        };
    }
}
